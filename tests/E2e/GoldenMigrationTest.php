<?php

declare(strict_types=1);

namespace Univapay\Migrate\Tests\E2e;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The E2E golden migration test. Copies tests/E2e/input/ (verbatim old-SDK
 * `examples/`, one file consolidated from real README code blocks, and synthetic fixtures
 * covering surface the real examples/README miss) into a fresh temp "consumer project", runs the
 * REAL `bin/univapay-migrate` entry point against it (not a parallel Rector-only test harness --
 * this exercises the whole fixed step order: preflight, materialize config, run Rector, post-scan,
 * report, exit code), and asserts every output file is byte-identical to the hand-reviewed
 * tests/E2e/expected/ tree.
 *
 * `--skip-composer` (see bin/univapay-migrate's own doc comment): the two mutating Composer
 * calls (require univapay/univapay-sdk-compat, remove univapay/php-sdk) are skipped -- the compat
 * package does not exist on Packagist yet (that's the sibling univapay-php-sdk-compat repo, not
 * yet built), and this test must not depend on network access at all. Rector itself (step 4)
 * still runs for real, via THIS package's OWN `vendor/bin/rector` (bin/univapay-migrate's
 * rector-binary resolution falls back to `$packageRoot/vendor/bin/rector` when the consumer
 * project has none of its own -- see that file's `resolveRectorBin()`), so the fixture "consumer
 * project" here needs no old-SDK or Rector install of its own beyond a minimal stub satisfying
 * preflight's `class_exists('Univapay\UnivapayClient')` check.
 *
 * No `--dry-run` is passed -- that IS the real, non-dry-run invocation (`--dry-run=false` isn't a
 * real flag this script accepts; omitting `--dry-run` is the same thing, since it defaults to
 * false).
 *
 * Why no stub old-SDK class hierarchy beyond one bare class: verified empirically (both here and
 * by this package's own Rector fixture suite, none of which installs a real old SDK either) that
 * PHPStan's receiver-type resolution for FlagUnsupportedFeatureRector/RenameClassRector works
 * from explicit type hints and `new X()` expressions structurally, without needing the referenced
 * class to actually be reflectable. Only the bin script's OWN preflight check needs a real,
 * loadable `Univapay\UnivapayClient` class to exist.
 *
 * NOT covered here (blocked on the compat package existing): actually EXECUTING the migrated
 * fixture files against Prism to assert runtime behavior (e.g. that unsupported-feature call
 * sites really do throw `UnivapayUnsupportedFeatureError`) -- see tests/E2e/ExecutionTest.php for
 * that. This test only proves the STATIC rewrite + reporting are correct and stable.
 */
final class GoldenMigrationTest extends TestCase
{
    // Siblings of this test class within tests/E2e/, mirroring the tests/Rector/ convention
    // (PhpSdkToCompatRectorTest.php sits alongside its own Fixture/ and config/ subdirectories in
    // the same directory, not a separately-cased sibling) -- NOT `__DIR__ . '/../e2e/...'`: this
    // filesystem is case-preserving-but-case-insensitive on macOS, so a lowercase path silently
    // resolves to this same directory locally but would 404 on a case-sensitive filesystem (every
    // real CI runner and most consumer machines).
    private const INPUT_DIR = __DIR__ . '/input';
    private const EXPECTED_DIR = __DIR__ . '/expected';
    private const PACKAGE_ROOT = __DIR__ . '/../..';

    /**
     * Expected post-scan counts for the full corpus, hand-verified against tests/E2e/expected/.
     * Kept as one place so a corpus change that shifts a count is a single, obvious failure
     * rather than several unrelated-looking assertion diffs.
     */
    private const EXPECTED_COUNTS = [
        // Bank accounts are permanently unsupported. +7 over the prior 20 --
        // examples/fetch_data.php's listBankAccounts()/getBankAccount() (+2) and
        // synthetic/unsupported_features_showcase.php's exhaustive BankAccount coverage (+5: the
        // BankAccount type-hint class reference, the GetBankAccounts trait `use`, and the
        // getBankAccount/listBankAccounts/listBankAccountContextsByOptions method-call trio).
        'unsupportedConfirmed' => 27,
        'unsupportedVerify' => 2,
        'internalApi' => 3,
        'networkException' => 2,
        // Residual (b): 4 dead-import lines (readme_usage_snippets.php) + 3 permanently-unrenamed
        // internal-api `use` lines (internal_api_usage.php, no compat target ever exists for
        // these) + 1 deliberately-untouched pre-existing comment (laravel_config.php).
        'residual' => 8,
        'deadImports' => 4,
    ];

    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/univapay-migrate-e2e-' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0777, true);
        mkdir($this->tempDir . '/src', 0777, true);
        $this->copyDirectory(self::INPUT_DIR, $this->tempDir . '/src');
        $this->writeFixtureConsumerScaffold($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testGoldenMigrationMatchesExpectedOutputByteForByte(): void
    {
        [$exitCode, $output] = $this->runMigrate();

        $this->assertSame(
            2,
            $exitCode,
            "expected exit code 2 (confirmed unsupported-feature flags present in the corpus " .
                "-- examples/fetch_data.php's listTransfers/getTransfer alone guarantee this); " .
                "got {$exitCode}.\n\n--- univapay-migrate output ---\n{$output}"
        );

        $this->assertDirectoryMatchesExpected(self::EXPECTED_DIR, $this->tempDir . '/src');
        $this->assertReportMatchesExpectedCounts($output);
        $this->assertReportListsIntendedResidualAndDeadImportEntries($output);
        $this->assertJsonReportMatchesExpectedCounts($exitCode);
    }

    /**
     * `univapay-migrate-report.json` is written to the cwd by default -- assert it
     * exists and its section sizes agree with the same EXPECTED_COUNTS baseline the stdout report
     * is checked against above, tying the two representations together instead of only ever
     * testing them in isolation (see UnivapayMigrateFlagsTest for the dedicated exit-code-matrix
     * and JSON-shape coverage; this is just the full-corpus cross-check).
     */
    private function assertJsonReportMatchesExpectedCounts(int $exitCode): void
    {
        $reportPath = $this->tempDir . '/univapay-migrate-report.json';
        $this->assertFileExists($reportPath, 'univapay-migrate-report.json must be written by default');

        $raw = file_get_contents($reportPath);
        $this->assertIsString($raw);

        $report = json_decode($raw, true);
        $this->assertIsArray($report, 'univapay-migrate-report.json must contain a JSON object');

        $this->assertSame(1, $report['version']);
        $this->assertSame($exitCode, $report['exitCode']);

        $unsupportedConfirmed = 0;
        $unsupportedVerify = 0;
        foreach ($report['unsupported'] as $entry) {
            if ($entry['verified']) {
                $unsupportedConfirmed++;
            } else {
                $unsupportedVerify++;
            }
        }

        $this->assertSame(self::EXPECTED_COUNTS['unsupportedConfirmed'], $unsupportedConfirmed);
        $this->assertSame(self::EXPECTED_COUNTS['unsupportedVerify'], $unsupportedVerify);
        $this->assertSame(self::EXPECTED_COUNTS['internalApi'], count($report['internalApi']));
        $this->assertSame(self::EXPECTED_COUNTS['networkException'], count($report['networkException']));
        $this->assertSame(self::EXPECTED_COUNTS['residual'], count($report['residualReferences']));
        $this->assertSame(self::EXPECTED_COUNTS['deadImports'], count($report['deadImports']));
    }

    /**
     * Idempotency: running migrate a SECOND time over already-migrated output
     * must produce zero further diffs, and the exit code / report counts must reflect only the
     * markers that persist by design (unsupported/internal-api/network-exception markers are
     * idempotent -- MarkerCommentTrait skips re-inserting an identical comment; dead
     * imports/residual references have no rename target at all, so they are expected to persist
     * forever until a human deletes/fixes them -- see tests/Rector/Fixture/idempotent_rerun.php.inc
     * for the same property at the single-rule level).
     */
    public function testSecondRunOverMigratedOutputIsIdempotent(): void
    {
        [$firstExitCode, $firstOutput] = $this->runMigrate();
        $this->assertSame(2, $firstExitCode, "first pass must succeed with the expected exit code before re-running");

        $snapshotDir = $this->tempDir . '-post-first-run';
        $this->copyDirectory($this->tempDir . '/src', $snapshotDir);

        try {
            [$secondExitCode, $secondOutput] = $this->runMigrate();

            $this->assertSame(
                $firstExitCode,
                $secondExitCode,
                "exit code must be identical across an idempotent re-run"
            );
            $this->assertDirectoryMatchesExpected($snapshotDir, $this->tempDir . '/src');
            $this->assertSame(
                $this->extractReportCounts($firstOutput),
                $this->extractReportCounts($secondOutput),
                "post-scan counts must be identical across an idempotent re-run -- persisting " .
                    "markers/residual references, never duplicated or newly introduced"
            );
        } finally {
            $this->removeDirectory($snapshotDir);
        }
    }

    // -------------------------------------------------------------------
    // Fixture consumer-project scaffold
    // -------------------------------------------------------------------

    private function writeFixtureConsumerScaffold(string $projectDir): void
    {
        file_put_contents(
            $projectDir . '/composer.json',
            json_encode(
                [
                    'name' => 'e2e/fixture-consumer',
                    'require' => [
                        'php' => '^7.2',
                        'univapay/php-sdk' => '^1.0',
                    ],
                    'autoload' => [
                        'psr-4' => ['UnivapayConsumer\\' => 'src/'],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ) . "\n"
        );

        mkdir($projectDir . '/vendor', 0777, true);
        // Hand-authored stand-in for a real Composer autoloader -- see this class's own doc
        // block for why no real `composer install` of univapay/php-sdk is needed at all: only
        // bin/univapay-migrate's OWN preflight check (`class_exists('Univapay\UnivapayClient')`)
        // needs this class to be genuinely loadable; Rector's rename/flag rules resolve types
        // structurally from type hints and `new X()` expressions, not via reflection on a real
        // installed old SDK.
        file_put_contents(
            $projectDir . '/vendor/autoload.php',
            <<<'PHP'
            <?php

            namespace Univapay {
                if (!class_exists(__NAMESPACE__ . '\\UnivapayClient', false)) {
                    class UnivapayClient
                    {
                    }
                }
            }

            PHP
        );
    }

    // -------------------------------------------------------------------
    // Running the real bin/univapay-migrate entry point
    // -------------------------------------------------------------------

    /**
     * @return array{0: int, 1: string} [exit code, combined stdout+stderr]
     */
    private function runMigrate(): array
    {
        $bin = self::PACKAGE_ROOT . '/bin/univapay-migrate';
        $this->assertFileExists($bin, 'bin/univapay-migrate must exist in this package');

        // No --dry-run: this IS the real, non-dry-run invocation ("--dry-run=false" isn't a real
        // flag this script accepts -- omitting --dry-run is the same thing, since it defaults to
        // false).
        $command = [PHP_BINARY, $bin, '--skip-composer', '--paths=src'];

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $this->tempDir);
        if (!is_resource($process)) {
            $this->fail('failed to start bin/univapay-migrate subprocess');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [$exitCode, $stdout . $stderr];
    }

    // -------------------------------------------------------------------
    // Assertions
    // -------------------------------------------------------------------

    private function assertDirectoryMatchesExpected(string $expectedDir, string $actualDir): void
    {
        $expectedFiles = $this->listFilesRelative($expectedDir);
        $actualFiles = $this->listFilesRelative($actualDir);

        sort($expectedFiles);
        sort($actualFiles);

        $this->assertSame(
            $expectedFiles,
            $actualFiles,
            "the set of files must match exactly (no file gained/lost by the migration)"
        );

        foreach ($expectedFiles as $relativePath) {
            $expectedContent = file_get_contents($expectedDir . '/' . $relativePath);
            $actualContent = file_get_contents($actualDir . '/' . $relativePath);

            $this->assertSame(
                $expectedContent,
                $actualContent,
                "byte-for-byte mismatch in {$relativePath}"
            );
        }
    }

    private function assertReportMatchesExpectedCounts(string $output): void
    {
        $counts = $this->extractReportCounts($output);

        $this->assertSame(
            self::EXPECTED_COUNTS,
            $counts,
            "post-scan report counts diverged from the hand-reviewed baseline.\n\n" .
                "--- univapay-migrate output ---\n{$output}"
        );
    }

    /**
     * @return array{unsupportedConfirmed: int, unsupportedVerify: int, internalApi: int, networkException: int, residual: int, deadImports: int}
     */
    private function extractReportCounts(string $output): array
    {
        return [
            'unsupportedConfirmed' => $this->extractInt('/unsupported \(confirmed\): (\d+)/', $output),
            'unsupportedVerify' => $this->extractInt('/unsupported \(verify[^)]*\): (\d+)/', $output),
            'internalApi' => $this->extractInt('/internal-api usages flagged: (\d+)/', $output),
            'networkException' => $this->extractInt('/network-exception \(WpOrg\\\\Requests\\\\\*\) usages flagged: (\d+)/', $output),
            'residual' => $this->extractInt('/(\d+) residual reference\(s\)/', $output),
            'deadImports' => substr_count($output, '-- dead import, safe to delete'),
        ];
    }

    private function extractInt(string $pattern, string $subject): int
    {
        if (preg_match($pattern, $subject, $matches) !== 1) {
            $this->fail("pattern {$pattern} not found in univapay-migrate output:\n{$subject}");
        }

        return (int) $matches[1];
    }

    /**
     * Spot-checks the report's prose against the specific fixtures this corpus was built to
     * exercise (the residual-reference section lists the Laravel config + dead imports), rather
     * than only asserting on aggregate counts.
     */
    private function assertReportListsIntendedResidualAndDeadImportEntries(string $output): void
    {
        $this->assertStringContainsString(
            'src/synthetic/laravel_config.php:28: // legacy note: this binding used to point at Univapay\RequestsHandlers\RateLimitHandler',
            $output,
            'the Laravel-config fixture\'s deliberately-untouched legacy comment must surface in the residual-reference section (b)'
        );

        foreach (
            [
                'src/examples/readme_usage_snippets.php:47: `Univapay\Client` -- dead import, safe to delete',
                'src/examples/readme_usage_snippets.php:48: `Univapay\RequestsHandlers` -- dead import, safe to delete',
                'src/examples/readme_usage_snippets.php:64: `Univapay\PaymentMethod\CardPayment` -- dead import, safe to delete',
                'src/examples/readme_usage_snippets.php:129: `Univapay\RequestsHandlers` -- dead import, safe to delete',
            ] as $expectedLine
        ) {
            $this->assertStringContainsString(
                $expectedLine,
                $output,
                'every dead README import must be reported in section (c), never silently renamed'
            );
        }

        // The bare-AppJWT bug (examples/fetch_data.php) and its README-snippet twin must NOT be
        // rewritten -- confirmed indirectly here: the migrated file content assertion
        // (assertDirectoryMatchesExpected) already proves `AppJWT::createToken(...)` is
        // byte-identical to the un-migrated input in both files, since ClassMap::SUPPORTED has no
        // entry for a bare, unimported `AppJWT` reference.
    }

    // -------------------------------------------------------------------
    // Filesystem helpers
    // -------------------------------------------------------------------

    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $targetPath = $destination . '/' . $relative;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }
                continue;
            }

            copy($item->getPathname(), $targetPath);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }

    /**
     * @return string[] paths relative to $directory, forward-slash separated
     */
    private function listFilesRelative(string $directory): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $relative = substr($item->getPathname(), strlen($directory) + 1);
            $files[] = str_replace('\\', '/', $relative);
        }

        return $files;
    }
}
