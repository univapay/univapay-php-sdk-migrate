<?php

declare(strict_types=1);

namespace Univapay\Migrate\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Univapay\Migrate\ClassMap;

/**
 * @group map-integrity
 *
 * `tests/ClassMapTest.php` only checks that `ClassMap::SUPPORTED` is internally well-formed
 * (regex-valid FQCNs, no dupes, pure prefix swap, etc.) -- it deliberately does NOT check that
 * either side of the map corresponds to a REAL class, since that requires an actual sibling
 * `univapay/univapay-sdk-compat` checkout. This suite does that real check, against actual
 * sibling checkouts:
 *
 *   1. Every VALUE in ClassMap::SUPPORTED (a `Univapay\Compat\...` FQCN) must exist as a real
 *      class, trait, or interface in a `univapay/univapay-sdk-compat` checkout.
 *   2. Every KEY in ClassMap::SUPPORTED (a `Univapay\...` FQCN) must exist as a real class,
 *      trait, or interface in a `univapay/php-sdk` checkout.
 *   3. (Reverse guard) every public class/trait/interface under `Univapay\Compat\` in that same
 *      compat checkout must appear as a VALUE somewhere in ClassMap::SUPPORTED -- catching newly
 *      added compat surface that nobody added a map entry for. A small, explicit allowlist of
 *      known compat-only additions (things that were never in the old SDK and never will be
 *      renamed FROM anything) is excluded -- see EXCLUDED_ADDITION_PREFIXES /
 *      EXCLUDED_ADDITION_CLASSES below.
 *
 * ## Locating the sibling checkouts
 *
 * Two env vars, both required:
 *   - `UNIVAPAY_COMPAT_PATH` -- path to a `univapay/univapay-sdk-compat` checkout.
 *   - `UNIVAPAY_OLD_SDK_PATH` -- path to a `univapay/php-sdk` checkout.
 *
 * Both repos are public on GitHub, but neither is a dependency of this package, so this suite is
 * marked `@group map-integrity` and skips gracefully with a clear message whenever
 * either env var is unset, the path doesn't exist, or (for compat) its own Composer dependencies
 * haven't been installed yet. This keeps `vendor/bin/phpunit -c tests/phpunit.xml` green in the
 * ordinary `unit-tests` CI job (which runs every `*Test.php` under `tests/`, this one included,
 * with neither env var set) -- only the dedicated `map-integrity` CI job (see
 * `.github/workflows/ci.yml`) sets both and expects this suite to actually run.
 *
 * ## How compat is loaded (by design choice, not composer path-repository)
 *
 * Two options exist: a dev-only Composer path-repository (added to THIS package's
 * `composer.json`) or a direct classmap autoload of the compat checkout's `src/`. Neither is
 * quite right on its own: a path-repository would mean editing this package's `composer.json`
 * and `composer.lock` just to point at a filesystem path that only exists on a machine running
 * this specific suite (wrong for every consumer who installs this package for real -- and
 * `composer.json`'s own doc comment on `require` already promises "no code dependency" on
 * compat, see ClassMap's own doc block); a naive classmap of only `src/*.php` would fail to
 * declare classes that `extend`/`implement`
 * compat's own real dependencies (`moneyphp/money`, the generated APIMatic engine SDK) since PHP
 * resolves `extends`/`implements`/trait `use` eagerly at class-declaration time, not lazily like
 * plain type hints.
 *
 * So instead: this suite `require_once`s the COMPAT CHECKOUT'S OWN already-installed
 * `vendor/autoload.php` (i.e. `composer install` must have already been run inside
 * `UNIVAPAY_COMPAT_PATH` -- true both for local dev, where a human/agent ran it once, and for CI,
 * where the map-integrity job runs it as a step before phpunit). That autoloader already knows
 * the real PSR-4 mapping (`Univapay\Compat\` -> compat's `src/`) AND has every real transitive
 * dependency compat's classes need to declare correctly. Booting a second Composer-generated
 * autoloader inside the same PHP process as this package's own (loaded by
 * `tests/phpunit.xml`'s `bootstrap`) is safe: PHP only ever consults a `spl_autoload_register`
 * callback for a class that isn't ALREADY declared, so there is no risk of double-declaring
 * anything both vendor trees happen to share (e.g. phpunit/phpunit itself, which compat also
 * requires in its own `require-dev`) -- whichever autoloader's class gets requested first wins,
 * and the second is simply never consulted for it. This does NOT add compat as a dependency of
 * this package: nothing changes in this package's own `composer.json`/`composer.lock`, and the
 * `require_once` only happens at test-run time, guarded by the env-var/skip logic above.
 *
 * The old SDK side does NOT need any autoloading at all -- this suite never instantiates or even
 * references an old-SDK class, so real class/trait/interface declarations are confirmed with a
 * cheap, dependency-free static check instead (PSR-0 FQCN -> file path, per the old SDK's own
 * `composer.json` `autoload.psr-0` map, plus a regex confirming the expected `class`/`interface`/
 * `trait` keyword with the right basename appears in that file). This avoids requiring the old
 * SDK's own dependencies (`rmccue/requests`, `moneyphp/money`) to be installed just to run this
 * suite, and avoids any risk of a class name collision with compat's own (very much still
 * installed) copy of `moneyphp/money`.
 *
 * ## Why this suite must run as its own `--group map-integrity` phpunit invocation
 *
 * Confirmed empirically while writing this suite: `require_once`-ing compat's `vendor/autoload.php`
 * in the SAME PHP process as the rest of `tests/phpunit.xml`'s run is not as harmless as "an
 * autoloader only fires for not-yet-declared classes" suggests. With this suite's env vars set,
 * `tests/Rector/Fixture/enums.php.inc` started failing (printing the bare short name
 * `ChargeStatus` in a renamed `@var` docblock tag instead of the fully-qualified
 * `\Univapay\Compat\Enums\ChargeStatus` the fixture expects) purely because
 * `PhpSdkToCompatRectorTest`'s data-provider tests happen to run, in the same process, AFTER this
 * class -- once `Univapay\Compat\Enums\ChargeStatus` is a real, reflectable class, Rector's
 * PHPStan-backed docblock-type printer resolves and formats that type differently than when it is
 * merely a string nobody can resolve. (A class-level `@runTestsInSeparateProcesses` annotation
 * does NOT fix this, despite looking like the obvious tool: `setUpBeforeClass()` -- where the
 * `require_once` lives -- always runs once in the SUITE's process, before PHPUnit decides whether
 * to fork any individual test method into a subprocess, so the pollution happens before isolation
 * would even apply.)
 *
 * The actual fix lives in how this suite is invoked, not in this file: the ordinary `unit-tests`
 * CI job (and any plain local `vendor/bin/phpunit -c tests/phpunit.xml` run) runs with
 * `--exclude-group map-integrity`, so this class's `setUpBeforeClass()` never executes in the same
 * process as the Rector fixture suite at all (env vars unset or not, it's excluded outright); the
 * dedicated `map-integrity` CI job (and the equivalent local command in this package's README)
 * instead runs `--group map-integrity` on its own, which selects ONLY this class -- nothing else
 * shares that process for it to affect. Do not remove `--exclude-group map-integrity` from the
 * `unit-tests` job, and do not merge this suite back into an unfiltered run, without re-verifying
 * `enums.php.inc` (and the rest of the Rector fixture suite) still passes with both
 * `UNIVAPAY_COMPAT_PATH`/`UNIVAPAY_OLD_SDK_PATH` set.
 */
final class MapIntegrityTest extends TestCase
{
    /**
     * Compat-only additions that are deliberately NOT renamed-from-old-SDK targets, and so are
     * expected to be ABSENT from ClassMap::SUPPORTED's values. Kept as an explicit, small
     * allowlist (not a blanket namespace exclusion for anything under `Errors\` or the whole
     * `Utility\` namespace) precisely so that a genuinely new, forgotten compat class NOT on this
     * list still fails the reverse-guard test below -- that failure mode is the whole point of
     * this reverse guard.
     */
    private const EXCLUDED_ADDITION_PREFIXES = [
        // Compat's own internal architecture (ApiCaller, Bridge, CompatContext,
        // ExceptionMapper, ListDispatcher, MoneyHelper, RequestModelFactory, ...) -- has no
        // old-SDK analogue at all, by design; nothing under here is ever a rename target.
        'Univapay\\Compat\\Support\\',
    ];

    private const EXCLUDED_ADDITION_CLASSES = [
        // New error types the compat package introduces that the old SDK never had:
        // - UnivapayNetworkError: thrown for connection failures instead of the old SDK's
        //   WpOrg\Requests\Exception (see FlagInternalApiUsageRector's network-exception marker).
        // - UnivapayUnsupportedFeatureError: thrown by FlagUnsupportedFeatureRector-flagged
        //   call sites (Transfer/Ledger/StatusChanges/ApplePayPayment/qrMerchantToken) at runtime.
        // - UnivapayListDispatchError: ListDispatcher's own fail-loud error type.
        'Univapay\\Compat\\Errors\\UnivapayNetworkError',
        'Univapay\\Compat\\Errors\\UnivapayUnsupportedFeatureError',
        'Univapay\\Compat\\Errors\\UnivapayListDispatchError',
    ];

    /**
     * The four `Requests\Handlers\*` classes that ARE real rename targets (already present in
     * ClassMap::SUPPORTED). Anything else compat later adds under this sub-namespace is treated
     * as an internal helper (not a consumer-facing renamed class) and excluded from the reverse
     * guard -- there are none currently, but the exclusion is written generically rather
     * than as a fixed list so a future purely-internal helper under this sub-namespace doesn't
     * need this test updated too.
     */
    private const KNOWN_HANDLER_CLASSES = [
        'Univapay\\Compat\\Requests\\Handlers\\BasicRetryHandler',
        'Univapay\\Compat\\Requests\\Handlers\\NetworkRetryHandler',
        'Univapay\\Compat\\Requests\\Handlers\\RateLimitHandler',
        'Univapay\\Compat\\Requests\\Handlers\\RequestHandler',
    ];

    private const HANDLERS_PREFIX = 'Univapay\\Compat\\Requests\\Handlers\\';

    private const FQCN_TO_PATH_REGEX_TEMPLATE = '/^[ \t]*(?:abstract[ \t]+|final[ \t]+)?'
        . '(?:class|interface|trait)[ \t]+%s\b/m';

    private static string $compatPath;

    private static string $oldSdkPath;

    public static function setUpBeforeClass(): void
    {
        $compatPath = getenv('UNIVAPAY_COMPAT_PATH');
        if ($compatPath === false || trim($compatPath) === '') {
            self::markTestSkipped(
                'UNIVAPAY_COMPAT_PATH is not set -- map-integrity needs a sibling checkout of '
                    . 'univapay/univapay-sdk-compat to verify ClassMap::SUPPORTED\'s values '
                    . 'resolve to real classes. Set the env var to a local clone to run this '
                    . 'suite (e.g. UNIVAPAY_COMPAT_PATH=/path/to/univapay-php-sdk-compat).'
            );
        }
        if (!is_dir($compatPath)) {
            self::markTestSkipped("UNIVAPAY_COMPAT_PATH ({$compatPath}) is not a directory.");
        }

        $compatAutoload = rtrim($compatPath, '/') . '/vendor/autoload.php';
        if (!is_file($compatAutoload)) {
            self::markTestSkipped(
                "{$compatAutoload} not found -- run `composer install` inside the compat "
                    . "checkout ({$compatPath}) first."
            );
        }

        $oldSdkPath = getenv('UNIVAPAY_OLD_SDK_PATH');
        if ($oldSdkPath === false || trim($oldSdkPath) === '') {
            self::markTestSkipped(
                'UNIVAPAY_OLD_SDK_PATH is not set -- map-integrity needs a sibling checkout of '
                    . 'univapay/php-sdk to verify ClassMap::SUPPORTED\'s keys resolve to real '
                    . 'classes. Set the env var to a local clone to run this suite (e.g. '
                    . 'UNIVAPAY_OLD_SDK_PATH=/path/to/univapay-php-sdk).'
            );
        }
        if (!is_dir(rtrim($oldSdkPath, '/') . '/src/Univapay')) {
            self::markTestSkipped(
                "{$oldSdkPath}/src/Univapay not found -- UNIVAPAY_OLD_SDK_PATH does not look "
                    . 'like a univapay/php-sdk checkout.'
            );
        }

        // See class doc block for why this is safe to do inside the same process as this
        // package's own vendor/autoload.php (already loaded by tests/phpunit.xml's bootstrap).
        require_once $compatAutoload;

        self::$compatPath = rtrim($compatPath, '/');
        self::$oldSdkPath = rtrim($oldSdkPath, '/');
    }

    public function testEveryMapValueExistsInCompat(): void
    {
        foreach (ClassMap::SUPPORTED as $oldFqcn => $compatFqcn) {
            self::assertTrue(
                class_exists($compatFqcn) || interface_exists($compatFqcn) || trait_exists($compatFqcn),
                "ClassMap::SUPPORTED['{$oldFqcn}'] => '{$compatFqcn}' does not exist as a "
                    . 'class/interface/trait in the univapay/univapay-sdk-compat checkout at '
                    . self::$compatPath . '.'
            );
        }
    }

    public function testEveryMapKeyExistsInOldSdk(): void
    {
        foreach (array_keys(ClassMap::SUPPORTED) as $oldFqcn) {
            $path = $this->oldSdkPathFor($oldFqcn);
            self::assertFileExists(
                $path,
                "ClassMap::SUPPORTED key '{$oldFqcn}' has no corresponding file in the "
                    . 'univapay/php-sdk checkout at ' . self::$oldSdkPath . " (expected {$path})."
            );

            $basename = $this->basename($oldFqcn);
            $contents = file_get_contents($path);
            self::assertIsString($contents);
            $pattern = sprintf(self::FQCN_TO_PATH_REGEX_TEMPLATE, preg_quote($basename, '/'));
            self::assertMatchesRegularExpression(
                $pattern,
                $contents,
                "{$path} does not appear to declare class/interface/trait {$basename} "
                    . "(ClassMap::SUPPORTED key '{$oldFqcn}')."
            );
        }
    }

    /**
     * Reverse guard: every public class/trait/interface actually shipped under
     * `Univapay\Compat\` in the sibling checkout must be reachable as a VALUE in
     * ClassMap::SUPPORTED, unless it's a known compat-only addition (see the allowlists above).
     * Without this, a newly added compat resource with no corresponding map entry would go
     * completely unnoticed -- the forward checks above only ever look FROM the map, never at
     * what compat actually ships.
     */
    public function testEveryCompatClassIsReachableFromTheMapOrKnownException(): void
    {
        $mapValues = array_flip(ClassMap::SUPPORTED);
        $srcDir = self::$compatPath . '/src';
        self::assertDirectoryExists($srcDir);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS)
        );

        $unmapped = [];
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($srcDir) + 1);
            $relative = substr($relative, 0, -strlen('.php'));
            $fqcn = 'Univapay\\Compat\\' . str_replace('/', '\\', $relative);

            if ($this->isKnownAddition($fqcn)) {
                continue;
            }

            if (!isset($mapValues[$fqcn])) {
                $unmapped[] = $fqcn;
            }
        }

        self::assertSame(
            [],
            $unmapped,
            "Found compat class(es) under Univapay\\Compat\\ with no ClassMap::SUPPORTED entry "
                . "pointing at them, and not on the known-addition allowlist: \n  - "
                . implode("\n  - ", $unmapped)
                . "\nEither add a ClassMap::SUPPORTED entry (with a matching old-SDK key) or, if "
                . 'this is a genuine compat-only addition, add it to '
                . 'MapIntegrityTest::EXCLUDED_ADDITION_PREFIXES/EXCLUDED_ADDITION_CLASSES with a '
                . 'comment explaining why it has no old-SDK analogue.'
        );
    }

    private function isKnownAddition(string $fqcn): bool
    {
        foreach (self::EXCLUDED_ADDITION_PREFIXES as $prefix) {
            if (strncmp($fqcn, $prefix, strlen($prefix)) === 0) {
                return true;
            }
        }

        if (in_array($fqcn, self::EXCLUDED_ADDITION_CLASSES, true)) {
            return true;
        }

        if (strncmp($fqcn, self::HANDLERS_PREFIX, strlen(self::HANDLERS_PREFIX)) === 0) {
            return !in_array($fqcn, self::KNOWN_HANDLER_CLASSES, true);
        }

        return false;
    }

    /**
     * PSR-0: `univapay/php-sdk`'s composer.json maps namespace prefix `Univapay` to base dir
     * `src/`, so `Univapay\Foo\Bar` -> `src/Univapay/Foo/Bar.php` (the namespace separator
     * becomes a directory separator throughout, including the leading `Univapay` segment).
     */
    private function oldSdkPathFor(string $fqcn): string
    {
        return self::$oldSdkPath . '/src/' . str_replace('\\', '/', $fqcn) . '.php';
    }

    private function basename(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
