<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Univapay\Migrate\UnivapaySetList;

/**
 * Template materialized by `bin/univapay-migrate` into the consumer project as
 * `rector-univapay.php`. Placeholders (delimited `__UNIVAPAY_MIGRATE_..._PLACEHOLDER__`) are
 * substituted by the bin script before Rector runs; this file is never used unmodified.
 *
 * `phpVersion(PhpVersion::PHP_72)` pins the PRINTED-code target, independent of the PHP version
 * Rector itself runs on: consumer floor is PHP 7.2 (matches the new SDK's `"php": "^7.2 || ^8.0"`),
 * so Rector must never emit 7.4+-only syntax (typed properties, arrow functions, `??=`, native
 * enums) even though Rector's own runtime requirement is PHP 7.4+/8.0+ (see NOTES.md).
 *
 * `importNames(true)`: verified empirically against the pinned
 * `rector/rector` 2.6.2 dist package that `false` here does NOT do what its previous comment
 * claimed. `RenameClassRector` only rewrites `Name\FullyQualified` nodes directly; with
 * `AUTO_IMPORT_NAMES` off, plain (imported, short-form) `Name` nodes are never renamed via a
 * `use` update at all -- instead, every touched reference in the file (not just the renamed
 * class) gets printed fully-qualified, and the OLD `use Univapay\...;` line is left dangling,
 * pointing at a class that will not exist once `composer remove univapay/php-sdk` runs. That is
 * strictly worse than the one documented side effect of turning this on: Rector's printer will
 * also opportunistically shorten OTHER pre-existing literal fully-qualified references elsewhere
 * in any file it touches at all (e.g. `new \Some\Unrelated\Thing()` becomes `new Thing()` plus a
 * new `use Some\Unrelated\Thing;` line) -- cosmetic and behavior-preserving (same class, still
 * resolves the same way), never a compile error and never a Univapay-specific change, but not
 * nothing either. `importShortClasses(false)` does NOT suppress this (verified empirically); no
 * config combination was found that keeps a clean rename AND fully prevents the unrelated
 * cosmetic side effect, so this documents the accepted trade-off rather than a bug.
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
        UnivapaySetList::PHP_SDK_TO_COMPAT,
    ]);

    $rectorConfig->phpVersion(PhpVersion::PHP_72);
    $rectorConfig->importNames(true);
};
