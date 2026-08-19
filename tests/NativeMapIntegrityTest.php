<?php

declare(strict_types=1);

namespace Univapay\Migrate\Tests;

use PHPUnit\Framework\TestCase;
use Univapay\Migrate\NativeClassMap;

/**
 * @group map-integrity
 *
 * Phase-2 (compat -> native) counterpart of tests/MapIntegrityTest.php: every KEY in
 * NativeClassMap::SUPPORTED (a `Univapay\Compat\...` FQCN) must exist as a real class/trait/
 * interface in a `univapay/univapay-sdk-compat` checkout. Unlike the phase-1 suite, the VALUE
 * side needs no sibling checkout or env var at all -- `univapay/client-sdk` is a normal
 * require-dev dependency of this package (see composer.json), already autoloaded by every test
 * process via tests/phpunit.xml's bootstrap, so it is checked directly with class_exists() (see
 * tests/NativeSdkAuditTest.php for the broader structural audit of that installed package).
 *
 * `NativeClassMap::SUPPORTED` is currently empty (see that class's own doc block for the full
 * audit trail), so the loop below is a no-op today -- kept, like the set's own wiring, so a
 * future carefully-reviewed 1:1 addition is covered by this check for free, the same way
 * MapIntegrityTest already covers ClassMap::SUPPORTED.
 *
 * Same env var as tests/MapIntegrityTest.php (`UNIVAPAY_COMPAT_PATH`) and the same graceful-skip
 * contract -- see that class's own doc block for why this needs `@group map-integrity` and must
 * never run in the same PHPUnit process as the Rector fixture suites (compat's own
 * vendor/autoload.php pollutes PHPStan's docblock-type resolution once loaded).
 */
final class NativeMapIntegrityTest extends TestCase
{
    private const FQCN_TO_PATH_REGEX_TEMPLATE = '/^[ \t]*(?:abstract[ \t]+|final[ \t]+)?'
        . '(?:class|interface|trait)[ \t]+%s\b/m';

    private static string $compatPath;

    public static function setUpBeforeClass(): void
    {
        $compatPath = getenv('UNIVAPAY_COMPAT_PATH');
        if ($compatPath === false || trim($compatPath) === '') {
            self::markTestSkipped(
                'UNIVAPAY_COMPAT_PATH is not set -- map-integrity needs a sibling checkout of ' .
                    'univapay/univapay-sdk-compat to verify NativeClassMap::SUPPORTED\'s keys ' .
                    'resolve to real classes. Set the env var to a local clone to run this suite ' .
                    '(e.g. UNIVAPAY_COMPAT_PATH=/path/to/univapay-php-sdk-compat).'
            );
        }
        if (!is_dir($compatPath)) {
            self::markTestSkipped("UNIVAPAY_COMPAT_PATH ({$compatPath}) is not a directory.");
        }

        self::$compatPath = rtrim($compatPath, '/');
    }

    public function testEveryMapKeyExistsInCompat(): void
    {
        // NativeClassMap::SUPPORTED is currently empty (see its own doc block) -- this assertion
        // makes that explicit here too, so the loop below performing zero iterations reads as
        // "confirmed still empty", not as an accidentally-no-op test (PHPUnit flags a test with
        // no assertions at all as risky).
        self::assertIsArray(NativeClassMap::SUPPORTED);

        foreach (array_keys(NativeClassMap::SUPPORTED) as $compatFqcn) {
            $path = $this->compatPathFor($compatFqcn);
            self::assertFileExists(
                $path,
                "NativeClassMap::SUPPORTED key '{$compatFqcn}' has no corresponding file in the " .
                    'univapay/univapay-sdk-compat checkout at ' . self::$compatPath . " (expected {$path})."
            );

            $basename = $this->basename($compatFqcn);
            $contents = file_get_contents($path);
            self::assertIsString($contents);
            $pattern = sprintf(self::FQCN_TO_PATH_REGEX_TEMPLATE, preg_quote($basename, '/'));
            self::assertMatchesRegularExpression(
                $pattern,
                $contents,
                "{$path} does not appear to declare class/interface/trait {$basename} " .
                    "(NativeClassMap::SUPPORTED key '{$compatFqcn}')."
            );
        }
    }

    public function testEveryMapValueExistsInTheInstalledNativeSdk(): void
    {
        self::assertIsArray(NativeClassMap::SUPPORTED);

        foreach (NativeClassMap::SUPPORTED as $compatFqcn => $nativeFqcn) {
            self::assertTrue(
                class_exists($nativeFqcn) || interface_exists($nativeFqcn) || trait_exists($nativeFqcn),
                "NativeClassMap::SUPPORTED['{$compatFqcn}'] => '{$nativeFqcn}' does not exist as a " .
                    'class/interface/trait in the installed univapay/client-sdk package.'
            );
        }
    }

    /**
     * `univapay/univapay-sdk-compat`'s own composer.json maps namespace prefix `Univapay\Compat`
     * to base dir `src/` (PSR-4), so `Univapay\Compat\Foo\Bar` -> `src/Foo/Bar.php`.
     */
    private function compatPathFor(string $fqcn): string
    {
        $relative = substr($fqcn, strlen('Univapay\\Compat\\'));
        return self::$compatPath . '/src/' . str_replace('\\', '/', $relative) . '.php';
    }

    private function basename(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
