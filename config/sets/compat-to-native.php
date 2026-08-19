<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\Use_\SeparateMultiUseImportsRector;
use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Name\RenameClassRector;
use Rector\Renaming\Rector\String_\RenameStringRector;
use Univapay\Migrate\NativeClassMap;
use Univapay\Migrate\Rector\Rule\FlagCompatManualMigrationRector;
use Univapay\Migrate\Rector\Rule\RenameDocblockTagFqcnRector;
use Univapay\Migrate\Rector\Rule\SeparateGroupUseImportsRector;

/**
 * The `compat-to-native` Rector set: the SECOND migration hop, from
 * `univapay/univapay-sdk-compat` (`Univapay\Compat\*`) onto the native, APIMatic-generated
 * `univapay/client-sdk` (`UnivaPay\*`). REVIEW-ASSISTED, not drop-in -- see NativeClassMap's own
 * doc block for the full audit behind why {@see NativeClassMap::SUPPORTED} is currently empty:
 * every construct a real consumer codebase is likely to reference falls into one of
 * {@see FlagCompatManualMigrationRector}'s categories instead of a safe mechanical rename.
 *
 * Same pre-pass / rename / flag shape as config/sets/php-sdk-to-compat.php, minus the
 * FlagUnsupportedFeatureRector/FlagInternalApiUsageRector pair (phase 1's "some old-SDK surface
 * has literally no compat equivalent" concept does not apply the same way here -- everything
 * phase 2 would otherwise treat as "unsupported" is instead a flagged manual-migration category,
 * since there is always SOME native equivalent construct to point at, just never a mechanical
 * one).
 */
return static function (RectorConfig $rectorConfig): void {
    // Same two import-splitting pre-passes as phase 1, registered for the same reason (a
    // comma-form or brace-form grouped `use Univapay\Compat\{A, B};` import must be split into one
    // `use` per class before RenameClassRector -- or, here, before the flag rule below -- can see
    // each class individually). Harmless / a no-op on files with no such import.
    $rectorConfig->rule(SeparateMultiUseImportsRector::class);
    $rectorConfig->rule(SeparateGroupUseImportsRector::class);

    // Flag rule, registered BEFORE RenameClassRector for the same node-consumption reason
    // documented at length in config/sets/php-sdk-to-compat.php (RenameClassRector replaces a
    // `Name` with a `Name\FullyQualified` -- a different node class -- which stops the traverser
    // from calling any later visitor for that same node in the same pass). NativeClassMap::SUPPORTED
    // is empty today, so this ordering has no observable effect yet, but keeping it identical to
    // the phase-1 set means a future non-empty entry here is safe by construction, without anyone
    // needing to rediscover this ordering constraint from scratch.
    $rectorConfig->rule(FlagCompatManualMigrationRector::class);

    // The rename map itself -- currently empty (see NativeClassMap's doc block), registered anyway
    // so a future carefully-reviewed 1:1 addition needs no set-wiring changes.
    $rectorConfig->ruleWithConfiguration(RenameClassRector::class, NativeClassMap::SUPPORTED);

    // String-literal FQCN rename, same built-in rule phase 1 uses, over the same (currently empty)
    // map.
    $rectorConfig->ruleWithConfiguration(RenameStringRector::class, NativeClassMap::SUPPORTED);

    // Docblock-tag rename (@expectedException/@covers/@uses), same built-in-adjacent custom rule
    // phase 1 uses, over the same (currently empty) map.
    $rectorConfig->ruleWithConfiguration(RenameDocblockTagFqcnRector::class, NativeClassMap::SUPPORTED);
};
