<?php

/**
 * Runs INSIDE the PHP 7.2 execution container (see tests/E2e/docker/Dockerfile and
 * scripts/e2e-execution.sh) -- iterates the fixed list of tests/E2e/executable/*.php scripts,
 * runs each as its OWN subprocess (so one script's fatal error/uncaught exception can never take
 * down the others or this runner itself), and writes a single machine-readable
 * execution-results.json to the consumer project root for tests/E2e/ExecutionTest.php (running
 * back on the HOST, outside any container) to read and assert against.
 *
 * Pass/fail contract per script (see executable/_bootstrap.php's e2eAssert()/e2eAssertThrows()):
 * exit code 0 = every assertion passed and nothing fatal'd; any other exit code (an uncaught
 * exception's fatal error, or an e2eAssert() failure's RuntimeException) = failed. stdout/stderr
 * are captured for diagnostics either way.
 */

declare(strict_types=1);

const E2E_SCRIPTS = [
    'create_charge_and_refund.php',
    'create_subscription.php',
    'parse_webhook_data.php',
    'fetch_data.php',
    'synthetic_execution_checks.php',
];

function main(): int
{
    $scriptsDir = __DIR__ . '/executable';
    $consumerRoot = getenv('E2E_CONSUMER_ROOT');
    if ($consumerRoot === false || trim($consumerRoot) === '') {
        $consumerRoot = getcwd();
    }

    $results = [];
    $overallOk = true;

    foreach (E2E_SCRIPTS as $script) {
        $path = $scriptsDir . '/' . $script;
        if (!is_file($path)) {
            $results[$script] = [
                'exitCode' => -1,
                'ok' => false,
                'stdout' => '',
                'stderr' => "script not found: $path",
            ];
            $overallOk = false;
            echo "FAIL $script (not found)\n";
            continue;
        }

        [$exitCode, $stdout, $stderr] = runScript($path, $consumerRoot);
        $ok = $exitCode === 0;
        $overallOk = $overallOk && $ok;

        $results[$script] = [
            'exitCode' => $exitCode,
            'ok' => $ok,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];

        echo ($ok ? 'PASS' : 'FAIL') . " $script (exit $exitCode)\n";
        if (!$ok) {
            echo "--- stdout ---\n$stdout\n--- stderr ---\n$stderr\n";
        }
    }

    $payload = [
        'ok' => $overallOk,
        'phpVersion' => PHP_VERSION,
        'results' => $results,
    ];

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        fwrite(STDERR, "Could not JSON-encode execution results.\n");
        return 1;
    }

    file_put_contents($consumerRoot . '/execution-results.json', $json . "\n");

    return $overallOk ? 0 : 1;
}

/**
 * @return array{0: int, 1: string, 2: string}
 */
function runScript(string $path, string $cwd): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    // Array-form proc_open() command (no shell interpolation needed) is PHP 7.4+ only -- this
    // runner itself executes under PHP 7.2 (the whole point of this suite), so build a plain
    // shell command string instead, each argument individually escaped.
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path);
    $process = proc_open($command, $descriptorSpec, $pipes, $cwd);
    if (!is_resource($process)) {
        return [1, '', "failed to start subprocess for $path"];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return [$exitCode, $stdout, $stderr];
}

exit(main());
