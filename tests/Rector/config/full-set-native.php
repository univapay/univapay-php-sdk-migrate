<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Univapay\Migrate\UnivapaySetList;

/**
 * Rector config used by the compat-to-native fixture test suite (see CompatToNativeRectorTest).
 * Sibling of tests/Rector/config/full-set.php -- wires the real production set
 * (UnivapaySetList::COMPAT_TO_NATIVE) rather than a parallel test-only wiring, for the same reason
 * that file documents.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        UnivapaySetList::COMPAT_TO_NATIVE,
    ]);

    $rectorConfig->phpVersion(PhpVersion::PHP_72);
    $rectorConfig->importNames(true);
};
