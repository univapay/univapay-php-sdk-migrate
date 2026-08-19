<?php

declare(strict_types=1);

namespace Univapay\Migrate\Tests\Bin;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Exercises `bin/univapay-migrate --phase2`'s exit-code precedence (same `--strict`/
 * `--allow-unsupported` semantics as UnivapayMigrateFlagsTest, now applied to
 * `@univapay-migrate:phase2-manual` markers instead of `@univapay-migrate:unsupported` ones), its
 * preflight (checking `Univapay\Compat\UnivapayClient`, not the old SDK's client), and the
 * guarantee that steps 2/6 (composer require/remove) are NEVER run under `--phase2` regardless of
 * `--skip-composer`.
 *
 * Same `--skip-composer`-style fixture-consumer scaffold as UnivapayMigrateFlagsTest/
 * GoldenMigrationTest (see those classes' own doc blocks for why: no real
 * univapay/univapay-sdk-compat install is needed, only a loadable
 * `Univapay\Compat\UnivapayClient` stub for the bin script's own preflight check) -- deliberately
 * a small, purpose-built suite, not a full golden corpus like GoldenMigrationTest: phase 2's own
 * Rector coverage already lives in tests/Rector/FixtureNative/ via CompatToNativeRectorTest; this
 * suite is scoped to the bin script's OWN phase-2-specific wiring (flags, preflight, exit codes,
 * composer-skip guarantee).
 */
final class UnivapayMigratePhase2FlagsTest extends TestCase
{
    private const PACKAGE_ROOT = __DIR__ . '/../..';

    private const CONFIRMED_FIXTURE = <<<'PHP'
        <?php

        namespace UnivapayConsumer;

        use Univapay\Compat\Resources\Charge;

        class Confirmed
        {
            public function run(Charge $charge): void
            {
                $charge->awaitResult();
            }
        }

        PHP;

    private const VERIFY_FIXTURE = <<<'PHP'
        <?php

        namespace UnivapayConsumer;

        use Univapay\Compat\UnivapayClient;

        class VerifyOnly
        {
            /**
             * @param mixed $anything untyped -- receiver type cannot be statically resolved. The
             *     `use` import above (never otherwise referenced, so never a definite Name-node
             *     flag either -- see MarkerCommentTrait's Use_/GroupUse guard) exists only to
             *     satisfy FlagCompatManualMigrationRector's file-level precision gate.
             */
            public function run($anything): void
            {
                $anything->awaitResult();
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
                // No Univapay\Compat\ reference anywhere in this file.
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
    // Exit-code matrix (mirrors UnivapayMigrateFlagsTest, phase2Manual instead of unsupported)
    // -------------------------------------------------------------------

    public function testCleanFixtureExitsZeroWithAnEmptyPhase2ManualReport(): void
    {
        $dir = $this->makeFixture(self::CLEAN_FIXTURE);
        [$exitCode, $output] = $this->runMigrate($dir, []);

        self::assertSame(0, $exitCode, $output);

        $report = $this->readReport($dir);
        self::assertSame(0, $report['exitCode']);
        self::assertSame([], $report['phase2Manual']);
        self::assertSame([], $report['unsupported']);
    }

    public function testConfirmedPhase2ManualFlagExitsTwoByDefault(): void
    {
        $dir = $this->makeFixture(self::CONFIRMED_FIXTURE);
        [$exitCode, $output] = $this->runMigrate($dir, []);

        self::assertSame(2, $exitCode, $output);

        $report = $this->readReport($dir);
        self::assertSame(2, $report['exitCode']);
        self::assertNotSame([], $report['phase2Manual']);

        $confirmed = array_filter($report['phase2Manual'], static fn (array $e): bool => $e['verified']);
        self::assertNotSame([], $confirmed, $output);
    }

    public function testAllowUnsupportedDowngradesAConfirmedPhase2ManualFlagToExitZero(): void
    {
        $dir = $this->makeFixture(self::CONFIRMED_FIXTURE);
        [$exitCode, $output] = $this->runMigrate($dir, ['--allow-unsupported']);

        self::assertSame(0, $exitCode, $output);

        // The finding itself is still fully reported -- only the exit code is downgraded.
        $report = $this->readReport($dir);
        self::assertSame(0, $report['exitCode']);
        self::assertNotSame([], $report['phase2Manual']);
    }

    public function testVerifyOnlyPhase2ManualFlagDoesNotFailWithoutStrict(): void
    {
        $dir = $this->makeFixture(self::VERIFY_FIXTURE);
        [$exitCode, $output] = $this->runMigrate($dir, []);

        self::assertSame(0, $exitCode, $output);

        $report = $this->readReport($dir);
        self::assertSame(0, $report['exitCode']);
        self::assertNotSame([], $report['phase2Manual'], $output);
        foreach ($report['phase2Manual'] as $entry) {
            self::assertFalse($entry['verified'], $output);
        }
    }

    public function testStrictPromotesAVerifyOnlyPhase2ManualFlagToExitTwo(): void
    {
        $dir = $this->makeFixture(self::VERIFY_FIXTURE);
        [$exitCode, $output] = $this->runMigrate($dir, ['--strict']);

        self::assertSame(2, $exitCode, $output);

        $report = $this->readReport($dir);
        self::assertSame(2, $report['exitCode']);
    }

    public function testAllowUnsupportedDowngradesAStrictPromotedVerifyPhase2ManualFlagToo(): void
    {
        $dir = $this->makeFixture(self::VERIFY_FIXTURE);
        [$exitCode, $output] = $this->runMigrate($dir, ['--strict', '--allow-unsupported']);

        self::assertSame(0, $exitCode, $output);

        $report = $this->readReport($dir);
        self::assertSame(0, $report['exitCode']);
    }

    // -------------------------------------------------------------------
    // Phase-2-specific wiring
    // -------------------------------------------------------------------

    public function testPhase2NeverPrintsOrRunsComposerRequireOrRemove(): void
    {
        $dir = $this->makeFixture(self::CLEAN_FIXTURE);
        [, $output] = $this->runMigrate($dir, []);

        self::assertStringContainsString(
            '[phase2] never run automatically',
            $output,
            "phase2 must never attempt composer require/remove:\n{$output}"
        );
        self::assertStringContainsString('composer require univapay/client-sdk', $output, $output);
        self::assertStringContainsString('composer remove univapay/univapay-sdk-compat', $output, $output);
        // The two REAL mutating commands from the phase-1 path must never appear as something
        // actually run (only mentioned as a next step, matched above).
        self::assertStringNotContainsString('composer require univapay/univapay-sdk-compat:^1.0', $output, $output);
        self::assertStringNotContainsString('$ composer remove univapay/php-sdk', $output, $output);
    }

    public function testPreflightFailsWhenCompatIsNotAutoloadable(): void
    {
        $dir = sys_get_temp_dir() . '/univapay-migrate-phase2-flags-' . bin2hex(random_bytes(8));
        $this->tempDirs[] = $dir;

        mkdir($dir, 0777, true);
        mkdir($dir . '/src', 0777, true);
        file_put_contents($dir . '/src/Fixture.php', self::CLEAN_FIXTURE);

        file_put_contents(
            $dir . '/composer.json',
            json_encode(
                [
                    'name' => 'e2e/fixture-consumer-phase2-no-compat',
                    'require' => ['php' => '^7.2'],
                    'autoload' => ['psr-4' => ['UnivapayConsumer\\' => 'src/']],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ) . "\n"
        );

        // No vendor/autoload.php stubbing Univapay\Compat\UnivapayClient at all -- deliberately,
        // to exercise the preflight failure path.
        mkdir($dir . '/vendor', 0777, true);
        file_put_contents($dir . '/vendor/autoload.php', "<?php\n");

        [$exitCode, $output] = $this->runMigrate($dir, []);

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString(
            'Univapay\\Compat\\UnivapayClient is not autoloadable',
            $output,
            $output
        );
    }

    public function testReportJsonIncludesThePhase2ManualKeyWithFileLineFeatureAndVerified(): void
    {
        $dir = $this->makeFixture(self::CONFIRMED_FIXTURE);
        [, $output] = $this->runMigrate($dir, []);

        $report = $this->readReport($dir);
        self::assertArrayHasKey('phase2Manual', $report, $output);
        self::assertNotSame([], $report['phase2Manual']);

        $entry = $report['phase2Manual'][0];
        self::assertArrayHasKey('file', $entry);
        self::assertArrayHasKey('line', $entry);
        self::assertArrayHasKey('feature', $entry);
        self::assertArrayHasKey('verified', $entry);
    }

    // -------------------------------------------------------------------
    // Fixture consumer-project scaffold (phase2: stubs Univapay\Compat\UnivapayClient, not
    // Univapay\UnivapayClient)
    // -------------------------------------------------------------------

    private function makeFixture(string $sourceFileContents): string
    {
        $dir = sys_get_temp_dir() . '/univapay-migrate-phase2-flags-' . bin2hex(random_bytes(8));
        $this->tempDirs[] = $dir;

        mkdir($dir, 0777, true);
        mkdir($dir . '/src', 0777, true);
        file_put_contents($dir . '/src/Fixture.php', $sourceFileContents);

        file_put_contents(
            $dir . '/composer.json',
            json_encode(
                [
                    'name' => 'e2e/fixture-consumer-phase2',
                    'require' => [
                        'php' => '^7.2',
                        'univapay/univapay-sdk-compat' => '^1.0',
                    ],
                    'autoload' => [
                        'psr-4' => ['UnivapayConsumer\\' => 'src/'],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ) . "\n"
        );

        mkdir($dir . '/vendor', 0777, true);
        // Only the bin script's OWN preflight check needs Univapay\Compat\UnivapayClient to be
        // genuinely loadable; Rector's rules resolve types structurally, not via reflection (same
        // reasoning as GoldenMigrationTest/UnivapayMigrateFlagsTest's phase-1 stub).
        file_put_contents(
            $dir . '/vendor/autoload.php',
            <<<'PHP'
            <?php

            namespace Univapay\Compat {
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
    // Running the real bin/univapay-migrate entry point with --phase2
    // -------------------------------------------------------------------

    /**
     * @param string[] $extraArgs
     * @return array{0: int, 1: string} [exit code, combined stdout+stderr]
     */
    private function runMigrate(string $cwd, array $extraArgs): array
    {
        $bin = self::PACKAGE_ROOT . '/bin/univapay-migrate';
        self::assertFileExists($bin, 'bin/univapay-migrate must exist in this package');

        $command = array_merge(
            [PHP_BINARY, $bin, '--phase2', '--skip-composer', '--paths=src'],
            $extraArgs
        );

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $cwd);
        if (!is_resource($process)) {
            self::fail('failed to start bin/univapay-migrate subprocess');
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
     *     deadImports: array<int, array{file: string, line: int, import: string}>,
     *     phase2Manual: array<int, array{file: string, line: int, feature: string, verified: bool}>
     * }
     */
    private function readReport(string $cwd): array
    {
        $path = $cwd . '/univapay-migrate-report.json';
        self::assertFileExists($path, 'univapay-migrate-report.json was not written');

        $raw = file_get_contents($path);
        self::assertIsString($raw);

        $decoded = json_decode($raw, true);
        self::assertIsArray($decoded, 'univapay-migrate-report.json did not contain a JSON object');

        foreach (
            [
                'version', 'exitCode', 'unsupported', 'internalApi', 'networkException',
                'residualReferences', 'deadImports', 'phase2Manual',
            ] as $key
        ) {
            self::assertArrayHasKey($key, $decoded, "report is missing the \"{$key}\" key");
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
