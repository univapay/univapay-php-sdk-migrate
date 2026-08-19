<?php

declare(strict_types=1);

namespace Univapay\Migrate\Tests\Rector;

use Iterator;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * Fixture test suite for the `compat-to-native` Rector set (the currently-empty rename map plus
 * FlagCompatManualMigrationRector), run through the exact production configuration (see
 * tests/Rector/config/full-set-native.php). Sibling of PhpSdkToCompatRectorTest -- same
 * `.php.inc` before/after convention (a `-----` split; no split asserts the input is left
 * byte-for-byte unchanged).
 */
final class CompatToNativeRectorTest extends AbstractRectorTestCase
{
    /**
     * @dataProvider provideData
     */
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    public static function provideData(): Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/FixtureNative');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/full-set-native.php';
    }
}
