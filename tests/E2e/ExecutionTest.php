<?php

declare(strict_types=1);

namespace Univapay\Migrate\Tests\E2e;

use PHPUnit\Framework\TestCase;

/**
 * @group e2e-execution
 *
 * The end-to-end proof of the whole migration project. tests/E2e/GoldenMigrationTest.php only
 * proves the STATIC rewrite is byte-identical and the post-scan report/exit code are
 * correct; it never installs a real univapay/univapay-sdk-compat or executes a single line of the
 * migrated output. This suite does: it shells out to scripts/e2e-execution.sh, which (see that
 * script's own doc comment for the full mechanics) builds a throwaway consumer project pulling in
 * REAL sibling checkouts of univapay/univapay-sdk-compat and univapay_docs's generated
 * univapay/client-sdk via Composer path repositories, starts a real Prism mock from the docs
 * repo's own OpenAPI spec, and executes every script under tests/E2e/executable/ for real, under
 * PHP 7.2 (the new SDK's actual floor -- see tests/E2e/docker/Dockerfile).
 *
 * ## Skips (does not fail) unless ALL of the following hold
 *
 *   - `UNIVAPAY_COMPAT_PATH` env var set, pointing at a real univapay-php-sdk-compat checkout.
 *   - `UNIVAPAY_DOCS_PATH` env var set, pointing at a real univapay_docs checkout (needs
 *     `src/spec/openapi.yaml` and the committed `sdk/php/` generated client SDK).
 *   - A container engine (`docker` or `podman`) is on `PATH`.
 *
 * Same skip contract as tests/MapIntegrityTest.php for the same underlying reason:
 * neither sibling repo is guaranteed to exist as a checkout on every machine/CI runner this
 * package's own test suite runs on, and this suite additionally needs a container engine + network
 * access to pull `docker.io/stoplight/prism:4`/`docker.io/library/php:7.2-cli`/
 * `docker.io/library/composer:2` the first time it runs anywhere. `.github/workflows/ci.yml`'s
 * dedicated `e2e-execution` job sets both env vars and has Docker available by default -- see that
 * job's own comment for why it is `continue-on-error: true` (non-required) until the sibling
 * repos and CI secrets exist.
 *
 * This test class deliberately does NOT re-implement any part of scripts/e2e-execution.sh's own
 * orchestration (Docker network/Prism lifecycle, the throwaway consumer project's composer.json,
 * the two path-repository entries) -- that logic needs to be runnable standalone by a human too
 * (see this package's README), so it lives in one place and this test just drives it, the same
 * relationship tests/E2e/GoldenMigrationTest.php has with `bin/univapay-migrate` itself.
 */
final class ExecutionTest extends TestCase
{
    private const PACKAGE_ROOT = __DIR__ . '/../..';

    /**
     * Fixed list of every script tests/E2e/execution-runner.php runs, and what each one is this
     * suite's regression guard FOR -- kept here (not just in the runner) so a script silently
     * missing from execution-results.json (e.g. a future runner refactor that drops one) fails
     * this test with a clear, specific message rather than only affecting the aggregate ok flag.
     */
    private const EXPECTED_SCRIPTS = [
        // Two-step create flows, token->charge->refund->capture, all against a real compat
        // facade + real Prism.
        'create_charge_and_refund.php',
        // Subscription creation + SubscriptionStatus enum identity.
        'create_subscription.php',
        // Offline webhook hydration (no Prism call at all) -- also the "undefined $client"
        // upstream doc-example bug fix (see the script's own doc comment).
        'parse_webhook_data.php',
        // Merchant-wide survey script; a dedicated UnivapayUnsupportedFeatureError regression
        // guard (listTransfers()/getTransfer()) plus a NoMoreItems getNext() call.
        'fetch_data.php',
        // New (no golden-fixture counterpart): enum identity round-trip, Paginated::getNext()
        // NoMoreItems, a real 400 through the compat facade, and a forced-404-via-Prefer-header
        // proof that Support\ExceptionMapper really maps 404 -> UnivapayNotFoundError.
        'synthetic_execution_checks.php',
    ];

    private string $resultsPath;

    protected function setUp(): void
    {
        $compatPath = self::envOrNull('UNIVAPAY_COMPAT_PATH');
        if ($compatPath === null) {
            $this->markTestSkipped(
                'UNIVAPAY_COMPAT_PATH is not set -- e2e-execution needs a sibling checkout of '
                    . 'univapay/univapay-sdk-compat. Set it to a local clone to run this suite '
                    . '(e.g. UNIVAPAY_COMPAT_PATH=/path/to/univapay-php-sdk-compat).'
            );
        }
        if (!is_dir($compatPath) || !is_file($compatPath . '/composer.json')) {
            $this->markTestSkipped("UNIVAPAY_COMPAT_PATH ($compatPath) does not look like a compat checkout.");
        }

        $docsPath = self::envOrNull('UNIVAPAY_DOCS_PATH');
        if ($docsPath === null) {
            $this->markTestSkipped(
                'UNIVAPAY_DOCS_PATH is not set -- e2e-execution needs a sibling checkout of '
                    . 'univapay_docs (Prism mock source + the committed sdk/php/ generated '
                    . 'client). Set it to a local clone to run this suite (e.g. '
                    . 'UNIVAPAY_DOCS_PATH=/path/to/univapay_docs).'
            );
        }
        if (!is_file($docsPath . '/src/spec/openapi.yaml') || !is_dir($docsPath . '/sdk/php')) {
            $this->markTestSkipped("UNIVAPAY_DOCS_PATH ($docsPath) does not look like a univapay_docs checkout.");
        }

        if (!self::commandExists('docker') && !self::commandExists('podman')) {
            $this->markTestSkipped('Neither `docker` nor `podman` is on PATH -- required to run scripts/e2e-execution.sh.');
        }

        $this->resultsPath = sys_get_temp_dir() . '/univapay-migrate-e2e-execution-' . bin2hex(random_bytes(8)) . '.json';
    }

    protected function tearDown(): void
    {
        if (isset($this->resultsPath) && is_file($this->resultsPath)) {
            unlink($this->resultsPath);
        }
    }

    public function testMigratedExecutableScriptsRunSuccessfullyAgainstPrismUnderPhp72(): void
    {
        [$exitCode, $output] = $this->runE2eExecutionScript();

        $this->assertFileExists(
            $this->resultsPath,
            "scripts/e2e-execution.sh did not produce a results file at {$this->resultsPath}.\n\n"
                . "--- output ---\n{$output}"
        );

        $raw = file_get_contents($this->resultsPath);
        $this->assertIsString($raw);
        $report = json_decode($raw, true);
        $this->assertIsArray($report, "execution-results.json must contain a JSON object.\n\n--- output ---\n{$output}");

        $this->assertSame(
            '7.2',
            $this->majorMinor((string) $report['phpVersion']),
            'the executable scripts must run under PHP 7.2 (the new SDK\'s actual floor) -- got '
                . $report['phpVersion'] . '. See tests/E2e/docker/Dockerfile.'
        );

        $results = $report['results'];
        foreach (self::EXPECTED_SCRIPTS as $script) {
            $this->assertArrayHasKey(
                $script,
                $results,
                "execution-results.json has no entry for $script (see execution-runner.php's own script list)."
            );

            $entry = $results[$script];
            $this->assertSame(
                0,
                $entry['exitCode'],
                "$script exited non-zero ({$entry['exitCode']}).\n\n--- stdout ---\n{$entry['stdout']}\n--- stderr ---\n{$entry['stderr']}"
            );
            $this->assertTrue($entry['ok'], "$script's own \"ok\" flag is false despite exit code 0 -- inconsistent runner state.");
        }

        $this->assertTrue(
            $report['ok'],
            "execution-results.json's aggregate \"ok\" flag is false even though every individual script exited 0 -- "
                . "inconsistent runner state.\n\n--- output ---\n{$output}"
        );
        $this->assertSame(
            0,
            $exitCode,
            "scripts/e2e-execution.sh itself exited non-zero ({$exitCode}) despite every script passing.\n\n"
                . "--- output ---\n{$output}"
        );

        // Spot-check specific runtime behaviors rather than only asserting on aggregate exit
        // codes -- ties this test to the actual regression guards (the ExceptionMapper 404 fix,
        // FlagUnsupportedFeatureRector's runtime counterpart) instead of merely "nothing crashed".
        $this->assertStringContainsString(
            'UnivapayUnsupportedFeatureError',
            $results['fetch_data.php']['stdout'],
            'fetch_data.php must actually observe UnivapayUnsupportedFeatureError being thrown for listTransfers()/getTransfer().'
        );
        $syntheticOut = $results['synthetic_execution_checks.php']['stdout'];
        $this->assertStringContainsString('IDENTICAL (===) ChargeStatus::SUCCESSFUL()', $syntheticOut, 'enum identity round-trip must be asserted for real.');
        $this->assertStringContainsString('UnivapayNoMoreItemsError', $syntheticOut, 'Paginated::getNext() NoMoreItems branch must be asserted for real.');
        $this->assertStringContainsString(
            'REAL Prism 404 response maps to UnivapayNotFoundError',
            $syntheticOut,
            'the forced-404 -> UnivapayNotFoundError mapping must be asserted for real.'
        );
    }

    /**
     * @return array{0: int, 1: string} [exit code, combined stdout+stderr]
     */
    private function runE2eExecutionScript(): array
    {
        $script = self::PACKAGE_ROOT . '/scripts/e2e-execution.sh';
        $this->assertFileExists($script, 'scripts/e2e-execution.sh must exist in this package');

        $env = array_merge($_SERVER, [
            'E2E_RESULTS_PATH' => $this->resultsPath,
        ]);
        // Filter down to plain scalar env values proc_open() accepts (superglobals like
        // $_SERVER carry some non-scalar entries, e.g. 'argv').
        $env = array_filter($env, static fn ($value): bool => is_string($value) || is_int($value) || is_float($value));

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(['bash', $script], $descriptorSpec, $pipes, self::PACKAGE_ROOT, $env);
        if (!is_resource($process)) {
            $this->fail('failed to start scripts/e2e-execution.sh subprocess');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [$exitCode, $stdout . $stderr];
    }

    private function majorMinor(string $version): string
    {
        $parts = explode('.', $version);
        return $parts[0] . '.' . ($parts[1] ?? '0');
    }

    private static function envOrNull(string $name): ?string
    {
        $value = getenv($name);
        if ($value === false || trim($value) === '') {
            return null;
        }
        return $value;
    }

    private static function commandExists(string $command): bool
    {
        $result = shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null');
        return is_string($result) && trim($result) !== '';
    }
}
