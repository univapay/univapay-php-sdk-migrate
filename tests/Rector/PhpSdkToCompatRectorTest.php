<?php

declare(strict_types=1);

namespace Univapay\Migrate\Tests\Rector;

use Iterator;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * Fixture test suite for the whole `php-sdk-to-compat` Rector set (rename map, string-FQCN
 * rename, docblock-tag rename, and both flag rules), run through the exact production
 * configuration (see tests/Rector/config/full-set.php).
 *
 * Fixtures follow the standard Rector `.php.inc` convention: a file containing `-----` splits
 * "before" from "after"; a file with no split asserts the input is left byte-for-byte unchanged
 * (used here for every no-op / expected-miss / idempotency fixture).
 */
final class PhpSdkToCompatRectorTest extends AbstractRectorTestCase
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
        return self::yieldFilesFromDirectory(__DIR__ . '/Fixture');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/full-set.php';
    }
}
