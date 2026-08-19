<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Univapay\Migrate\UnivapaySetList;

/**
 * Template materialized by `bin/univapay-migrate --phase2` into the consumer project as
 * `rector-univapay-phase2.php`. Sibling of config/rector-template.php (the phase-1 template) --
 * kept as a SEPARATE file rather than a parameterized shared template so each phase's materialized
 * config is trivially diffable against its own template, and so a consumer who greps their repo
 * for "how was this file generated" finds an unambiguous answer. Same placeholder substitution
 * contract as the phase-1 template (see that file's own doc block for `--paths` derivation).
 *
 * `phpVersion`/`importNames` kept identical to the phase-1 template for the same reasons documented
 * there -- phase 2 does not change the consumer's PHP floor or Rector's import-handling behavior,
 * only which set is wired in.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        // __UNIVAPAY_MIGRATE_PATHS_PLACEHOLDER__
        // Substituted with the consumer's autoload source directories, derived from their
        // composer.json `autoload`/`autoload-dev` PSR-4 paths, or from --paths CLI args.
    ]);

    $rectorConfig->skip([
        '*/vendor/*',
        '*/var/*',
        '*/storage/*',
        '*/node_modules/*',
        '*.blade.php',
        '*.twig',
    ]);

    $rectorConfig->sets([
        UnivapaySetList::COMPAT_TO_NATIVE,
    ]);

    $rectorConfig->phpVersion(PhpVersion::PHP_72);
    $rectorConfig->importNames(true);
};
