<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Univapay\Migrate\UnivapaySetList;

/**
 * Rector config used by the fixture test suite (see PhpSdkToCompatRectorTest). Wires the exact
 * same production set (UnivapaySetList::PHP_SDK_TO_COMPAT) that consumers get via the
 * materialized config/rector-template.php, so these tests exercise the real shipped
 * configuration rather than a parallel test-only wiring that could drift from it. `phpVersion`
 * and `importNames` are kept identical to config/rector-template.php too (see that file's doc
 * block for why `importNames(true)`, not `false`, is the correct setting).
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        UnivapaySetList::PHP_SDK_TO_COMPAT,
    ]);

    $rectorConfig->phpVersion(PhpVersion::PHP_72);
    $rectorConfig->importNames(true);
};
