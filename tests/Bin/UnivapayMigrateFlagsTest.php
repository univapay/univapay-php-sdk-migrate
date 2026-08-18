<?php

declare(strict_types=1);

namespace Univapay\Migrate\Tests\Bin;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Exercises `bin/univapay-migrate`'s exit-code precedence between `--strict` and
 * `--allow-unsupported`, and the `univapay-migrate-report.json` file it writes (plan parity --
 * both were specified in the original plan but never implemented, caught while writing the
 * README's "Exit codes + report" section).
 *
 * Unlike tests/E2e/GoldenMigrationTest.php (the full ~20-file corpus, byte-for-byte diff against
 * a hand-reviewed expected/ tree), this suite runs the REAL bin/univapay-migrate entry point
 * against small, purpose-built single-file fixtures so each exit-code/flag combination is
 * isolated and its cause is obvious from the fixture alone:
 *
 * - CONFIRMED_FIXTURE: one *definite* unsupported-feature flag (a correctly-typed
 *   `UnivapayClient` receiver calling `getTransfer()`) and nothing else -- confirmed=1, verify=0.
 * - VERIFY_FIXTURE: one *unresolved-receiver* `(verify)` flag only (an untyped parameter calling
 *   `getTransfer()`, in a file that still references `Univapay\` elsewhere to satisfy
 *   FlagUnsupportedFeatureRector's precision gate) -- confirmed=0, verify=1.
 * - CLEAN_FIXTURE: no Univapay references at all -- every count is 0.
 *
 * Same `--skip-composer` fixture-consumer scaffold as GoldenMigrationTest (see that class's own
 * doc block for why: no real `univapay/php-sdk`/`univapay/univapay-sdk-compat` install is needed,
 * only a loadable `Univapay\UnivapayClient` stub for the bin script's own preflight check).
 */
final class UnivapayMigrateFlagsTest extends TestCase
{
    private const PACKAGE_ROOT = __DIR__ . '/../..';

    private const CONFIRMED_FIXTURE = <<<'PHP'
        <?php

        namespace UnivapayConsumer;

        use Univapay\UnivapayClient;

        class Confirmed
        {
            public function run(UnivapayClient $client, string $transferId): void
            {
                $client->getTransfer($transferId);
            }
        }

        PHP;

    private const VERIFY_FIXTURE = <<<'PHP'
        <?php

        namespace UnivapayConsumer;

        use Univapay\UnivapayClient;

        class VerifyOnly
        {
            /**
             * @param mixed $anything an untyped result from some other layer -- Rector cannot
             *     resolve this receiver's type statically, so a call to an unsupported-feature
             *     method name here can only be an unresolved-receiver `(verify)` flag, never a
             *     confirmed one. The `use` import above (never otherwise referenced) exists only
             *     to satisfy FlagUnsupportedFeatureRector's file-level precision gate.
             */
            public function run($anything): void
            {
                $anything->getTransfer('some-id');
            }
        }

        PHP;

    private const CLEAN_FIXTURE = <<<'PHP'
        <?php

        namespace UnivapayConsumer;

        class Clean
        {
            public function run(): void
            {
                // No Univapay reference anywhere in this file.
            }
        }

        PHP;

    /** @var string[] */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }
        $this->tempDirs = [];
    }

    // -------------------------------------------------------------------
    // Exit-code matrix
    // -------------------------------------------------------------------

    public function testCleanFixtureExitsZeroWithAnEmptyReport(): void
    {
        $dir = $this->makeFixture(self::CLEAN_FIXTURE);
        [$exitCode, $output] = $this->runMigrate($dir, []);

        $this->assertSame(0, $exitCode, $output);

        $report = $this->readReport($dir);
        $this->assertSame(1, $report['version']);
        $this->assertSame(0, $report['exitCode']);
        $this->assertSame([], $report['unsupported']);
        $this->assertSame([], $report['internalApi']);
        $this->assertSame([], $report['networkException']);
        $this->assertSame([], $report['residualReferences']);
        $this->assertSame([], $report['deadImports']);
    }

    public function testConfirmedUnsupportedFlagExitsTwoByDefault(): void
    {
        $dir = $this->makeFixture(self::CONFIRMED_FIXTURE);
        [$exitCode, $output] = $this->runMigrate($dir, []);

        $this->assertSame(2, $exitCode, $output);

        $report = $this->readReport($dir);
        $this->assertSame(2, $report['exitCode']);
        $this->assertCount(1, $report['unsupported']);
        $this->assertSame('getTransfer', $report['unsupported'][0]['feature']);
        $this->assertTrue($report['unsupported'][0]['verified']);
    }

    public function testAllowUnsupportedDowngradesAConfirmedFlagToExitZero(): void
    {
        $dir = $this->makeFixture(self::CONFIRMED_FIXTURE);
        [$exitCode, $output] = $this->runMigrate($dir, ['--allow-unsupported']);

        $this->assertSame(0, $exitCode, $output);

        // The finding itself is still fully reported -- only the exit code is downgraded.
        $report = $this->readReport($dir);
        $this->assertSame(0, $report['exitCode']);
        $this->assertCount(1, $report['unsupported']);
        $this->assertTrue($report['unsupported'][0]['verified']);
    }

    public function testVerifyOnlyFlagDoesNotFailWithoutStrict(): void
    {
        $dir = $this->makeFixture(self::VERIFY_FIXTURE);
        [$exitCode, $output] = $this->runMigrate($dir, []);

        $this->assertSame(0, $exitCode, $output);

        $report = $this->readReport($dir);
        $this->assertSame(0, $report['exitCode']);
        $this->assertCount(1, $report['unsupported']);
        $this->assertSame('getTransfer', $report['unsupported'][0]['feature']);
        $this->assertFalse($report['unsupported'][0]['verified']);
    }

    public function testStrictPromotesAVerifyOnlyFlagToExitTwo(): void
    {
        $dir = $this->makeFixture(self::VERIFY_FIXTURE);
        [$exitCode, $output] = $this->runMigrate($dir, ['--strict']);

        $this->assertSame(2, $exitCode, $output);

        $report = $this->readReport($dir);
        $this->assertSame(2, $report['exitCode']);
    }

    /**
     * The precedence rule this task exists to nail down: `--allow-unsupported` downgrades BOTH a
     * confirmed unsupported-feature exit (covered above) AND a `--strict`-promoted `(verify)`
     * exit -- it is evaluated LAST, so it is never shadowed by `--strict`. Passing both flags
     * together always nets out to exit 0 for unsupported-feature-only findings.
     */
    public function testAllowUnsupportedDowngradesAStrictPromotedVerifyFlagToo(): void
    {
        $dir = $this->makeFixture(self::VERIFY_FIXTURE);
        [$exitCode, $output] = $this->runMigrate($dir, ['--strict', '--allow-unsupported']);

        $this->assertSame(0, $exitCode, $output);

        $report = $this->readReport($dir);
        $this->assertSame(0, $report['exitCode']);
        $this->assertCount(1, $report['unsupported']);
        $this->assertFalse($report['unsupported'][0]['verified']);
    }

    // -------------------------------------------------------------------
    // --no-report / report-file shape
    // -------------------------------------------------------------------

    public function testReportFileIsWrittenByDefault(): void
    {
        $dir = $this->makeFixture(self::CLEAN_FIXTURE);
        $this->runMigrate($dir, []);

        $this->assertFileExists($dir . '/univapay-migrate-report.json');
    }

    public function testNoReportFlagSuppressesTheReportFileWithoutChangingTheExitCode(): void
    {
        $dir = $this->makeFixture(self::CONFIRMED_FIXTURE);
        [$exitCode, $output] = $this->runMigrate($dir, ['--no-report']);

        $this->assertSame(2, $exitCode, $output);
        $this->assertFileDoesNotExist($dir . '/univapay-migrate-report.json');
    }

    public function testReportJsonIsPrettyPrintedWithUnescapedSlashes(): void
    {
        $dir = $this->makeFixture(self::CONFIRMED_FIXTURE);
        $this->runMigrate($dir, []);

        $raw = file_get_contents($dir . '/univapay-migrate-report.json');
        $this->assertIsString($raw);
        $this->assertStringNotContainsString(
            '\\/',
            $raw,
            'JSON_UNESCAPED_SLASHES must be set -- feature/file paths containing "/" or "\\\\" ' .
                'should not come out as escaped "\\/"'
        );
        $this->assertStringContainsString(
            "\n",
            $raw,
            'JSON_PRETTY_PRINT must be set -- a single-line minified file is not "machine-readable ' .
                'AND reviewable" as specified'
        );
    }

    public function testInternalApiAndNetworkExceptionEntriesCarryFileLineAndFeature(): void
    {
        $dir = $this->makeFixture(<<<'PHP'
            <?php

            namespace UnivapayConsumer;

            use WpOrg\Requests\Exception as RequestsException;

            class Handler
            {
                public function build(): void
                {
                    // Referenced exactly once (fully-qualified, no `use` import, no return type
                    // hint) so FlagInternalApiUsageRector's Name-node match fires exactly once --
                    // a `use` import and a type hint for the same FQCN are each their OWN `Name`
                    // node and would each get their own marker.
                    new \Univapay\Requests\HttpRequester();
                }

                public function retry(): void
                {
                    try {
                        // no-op
                    } catch (RequestsException $e) {
                        // handled
                    }
                }
            }

            PHP);

        [, $output] = $this->runMigrate($dir, []);
        $report = $this->readReport($dir);

        $this->assertCount(1, $report['internalApi'], $output);
        $this->assertSame('Requests\\HttpRequester', $report['internalApi'][0]['feature']);
        $this->assertArrayHasKey('file', $report['internalApi'][0]);
        $this->assertArrayHasKey('line', $report['internalApi'][0]);

        $this->assertCount(1, $report['networkException'], $output);
        $this->assertSame('WpOrg\\Requests\\Exception', $report['networkException'][0]['feature']);
        $this->assertArrayHasKey('file', $report['networkException'][0]);
        $this->assertArrayHasKey('line', $report['networkException'][0]);
    }

    // -------------------------------------------------------------------
    // Fixture consumer-project scaffold (same shape as GoldenMigrationTest's)
    // -------------------------------------------------------------------

    private function makeFixture(string $sourceFileContents): string
    {
        $dir = sys_get_temp_dir() . '/univapay-migrate-flags-' . bin2hex(random_bytes(8));
        $this->tempDirs[] = $dir;

        mkdir($dir, 0777, true);
        mkdir($dir . '/src', 0777, true);
        file_put_contents($dir . '/src/Fixture.php', $sourceFileContents);

        file_put_contents(
            $dir . '/composer.json',
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

        mkdir($dir . '/vendor', 0777, true);
        // See GoldenMigrationTest's own doc block for why a bare stub class is enough here: only
        // the bin script's OWN preflight check needs Univapay\UnivapayClient to be genuinely
        // loadable; Rector's rules resolve types structurally, not via reflection.
        file_put_contents(
            $dir . '/vendor/autoload.php',
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

        return $dir;
    }

    // -------------------------------------------------------------------
    // Running the real bin/univapay-migrate entry point
    // -------------------------------------------------------------------

    /**
     * @param string[] $extraArgs
     * @return array{0: int, 1: string} [exit code, combined stdout+stderr]
     */
    private function runMigrate(string $cwd, array $extraArgs): array
    {
        $bin = self::PACKAGE_ROOT . '/bin/univapay-migrate';
        $this->assertFileExists($bin, 'bin/univapay-migrate must exist in this package');

        $command = array_merge(
            [PHP_BINARY, $bin, '--skip-composer', '--paths=src'],
            $extraArgs
        );

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $cwd);
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

    /**
     * @return array{
     *     version: int,
     *     exitCode: int,
     *     unsupported: array<int, array{file: string, line: int, feature: string, verified: bool}>,
     *     internalApi: array<int, array{file: string, line: int, feature: string}>,
     *     networkException: array<int, array{file: string, line: int, feature: string}>,
     *     residualReferences: array<int, array{file: string, line: int, text: string}>,
     *     deadImports: array<int, array{file: string, line: int, import: string}>
     * }
     */
    private function readReport(string $cwd): array
    {
        $path = $cwd . '/univapay-migrate-report.json';
        $this->assertFileExists($path, 'univapay-migrate-report.json was not written');

        $raw = file_get_contents($path);
        $this->assertIsString($raw);

        $decoded = json_decode($raw, true);
        $this->assertIsArray($decoded, 'univapay-migrate-report.json did not contain a JSON object');

        foreach (['version', 'exitCode', 'unsupported', 'internalApi', 'networkException', 'residualReferences', 'deadImports'] as $key) {
            $this->assertArrayHasKey($key, $decoded, "report is missing the \"{$key}\" key");
        }

        return $decoded;
    }

    // -------------------------------------------------------------------
    // Filesystem helpers
    // -------------------------------------------------------------------

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
}
