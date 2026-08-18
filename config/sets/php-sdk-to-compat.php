<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\Use_\SeparateMultiUseImportsRector;
use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Name\RenameClassRector;
use Rector\Renaming\Rector\String_\RenameStringRector;
use Univapay\Migrate\ClassMap;
use Univapay\Migrate\Rector\Rule\FlagInternalApiUsageRector;
use Univapay\Migrate\Rector\Rule\FlagUnsupportedFeatureRector;
use Univapay\Migrate\Rector\Rule\RenameDocblockTagFqcnRector;
use Univapay\Migrate\Rector\Rule\SeparateGroupUseImportsRector;

/**
 * The `php-sdk-to-compat` Rector set: rewrites `univapay/php-sdk` (`Univapay\*`) usages to
 * `univapay/univapay-sdk-compat` (`Univapay\Compat\*`) equivalents.
 *
 * One rename map + two flag rules, plus the string-FQCN rename, the docblock-tag rename, and
 * the WpOrg\Requests\* fold-in on the internal-API flag rule.
 */
return static function (RectorConfig $rectorConfig): void {
    // Pre-pass: comma-form `use Univapay\A, Univapay\B;` imports are NOT handled by
    // RenameClassRector on their own -- it needs one `use` statement per class. This must run
    // before the rename below.
    $rectorConfig->rule(SeparateMultiUseImportsRector::class);

    // Pre-pass: bracket-form `use Univapay\Enums\{ChargeStatus, RefundStatus};`
    // group imports. Verified empirically that the built-in SeparateMultiUseImportsRector above
    // does NOT handle this node shape at all (it only inspects Stmt\Use_, never Stmt\GroupUse) --
    // left alone, RenameClassRector renames nothing inside a group-use statement, so it is left
    // dangling against the old (soon nonexistent) FQCNs while every usage elsewhere in the file
    // gets printed fully-qualified instead of reusing a short import. Custom rule, no built-in
    // equivalent found in Rector 2.6.2 (see SeparateGroupUseImportsRector's own doc block).
    $rectorConfig->rule(SeparateGroupUseImportsRector::class);

    // Rule 2: FlagUnsupportedFeatureRector. Config: ClassMap::UNSUPPORTED_CLASSES +
    // ClassMap::UNSUPPORTED_METHODS. Targets Name + MethodCall/NullsafeMethodCall nodes; PHPStan
    // receiver-type gate; inserts an idempotent `// @univapay-migrate:unsupported ...` marker
    // comment above the enclosing statement.
    //
    // Registration order matters here and is NOT arbitrary: this (and FlagInternalApiUsageRector
    // below) MUST run before RenameClassRector. Verified empirically against
    // RectorNodeTraverser::traverseNode() (rector/rector 2.6.2): when a visitor's refactor()
    // replaces a node with a DIFFERENT node *class* (RenameClassRector replaces a plain `Name`
    // with a `Name\FullyQualified` -- a different concrete class), the traverser deliberately
    // stops calling any remaining visitors for that node in the same pass
    // (`if ($originalSubNodeClass !== get_class($subNode)) { continue 2; }`). Since every
    // unsupported class is ALSO in ClassMap::SUPPORTED (renamed to a compat stub so migrated code
    // still compiles -- see ClassMap's own doc block), if RenameClassRector ran first it would
    // silently consume the Name node before this rule ever saw it, and a bare class reference
    // (`new Transfer()`, a `Transfer` type hint, `instanceof Transfer`) would never get flagged --
    // only method calls would. Running the flag rules first avoids that: they return the SAME
    // node unchanged (only mutating a *different*, ancestor statement node's comments), so
    // RenameClassRector still runs normally afterwards on the same node.
    $rectorConfig->ruleWithConfiguration(
        FlagUnsupportedFeatureRector::class,
        [
            'classes' => ClassMap::UNSUPPORTED_CLASSES,
            'methods' => ClassMap::UNSUPPORTED_METHODS,
        ]
    );

    // Rule 3: FlagInternalApiUsageRector. Targets the five classes that have NO compat target
    // (Univapay\Requests\{Requester,HttpRequester,RequestContext}, plus
    // Univapay\Utility\{HttpUtils,RequesterUtils} -- see below) -- those are hard compile errors
    // after the old SDK is removed, so the marker comment says so -- and, folded into the same
    // rule (binding amendment), any `WpOrg\Requests\*` reference: the ported NetworkRetryHandler
    // now targets a new `UnivapayNetworkError`, not `WpOrg\Requests\Exception`, so a consumer
    // subclass matching on the old exception type silently stops retrying after migration.
    // Distinct marker text per category (see the rule's own doc block) so the post-scan can
    // report them separately.
    //
    // Note: `Univapay\Utility\*` is mostly NOT flagged here (binding amendment moved the
    // standalone parts into ClassMap::SUPPORTED instead, since the compat package ships verbatim
    // ports of those) -- confirmed by their absence from FlagInternalApiUsageRector's target list
    // above and covered by a no-op fixture in tests/Rector/Fixture/. The exception: `HttpUtils`
    // and `RequesterUtils` ARE in FlagInternalApiUsageRector's target list, because both are coupled
    // to the old transport (`RequesterUtils` takes a `RequestContext` argument on every method;
    // `HttpUtils::checkResponse()` takes a `WpOrg\Requests\Response`) and have no compat
    // replacement -- the compat package reimplements their behavior in `Support\ApiCaller` /
    // `Support\ExceptionMapper` against the new engine SDK instead of porting them verbatim.
    // Also registered before RenameClassRector, for the same node-consumption reason as above
    // (none of the five internal-API classes are in ClassMap::SUPPORTED so this specific case
    // would work either way, but WpOrg\ references never touch RenameClassRector at all --
    // ordering is kept consistent regardless).
    $rectorConfig->rule(FlagInternalApiUsageRector::class);

    // Rule 1: the rename map. Covers `use` statements, FQCNs, `::class`, `new`, `instanceof`,
    // catch types, type hints, and docblocks. ~154 entries, mechanically enumerated from the old
    // SDK tree (see ClassMap::SUPPORTED doc block for exclusions/inclusions). Registered AFTER
    // the flag rules above (see their comments for why).
    $rectorConfig->ruleWithConfiguration(RenameClassRector::class, ClassMap::SUPPORTED);

    // String-literal FQCN rename. Rector 2.6.2 ships a built-in `RenameStringRector` under
    // `Rector\Renaming\Rector\String_` that targets `String_` nodes and matches via
    // `ValueResolver::isValue()`, i.e. against the *parsed*
    // string value, so single/double quoting and escaping are irrelevant to the match. No custom
    // rule was needed here (see README for the evidence trail). Critical case this rescues (old
    // SDK): `new BasicRetryHandler('Univapay\Errors\UnivapayServerError', ...)` -- the matcher is
    // `instanceof $this->exceptionClass` against that string, so an un-renamed string here is a
    // *silent* retry-matcher failure, not a compile error.
    $rectorConfig->ruleWithConfiguration(RenameStringRector::class, ClassMap::SUPPORTED);

    // Docblock rule for `@expectedException` / `@covers` / `@uses` tags -- generic text tags
    // Rector's built-in type-tag renamer (used by RenameClassRector for @var/@param/@return via
    // structured PHPStan-docblock integration) does not touch. PHPUnit 8 still executes
    // `@expectedException` at runtime, so a stale FQCN there silently changes test behavior.
    $rectorConfig->ruleWithConfiguration(RenameDocblockTagFqcnRector::class, ClassMap::SUPPORTED);

    // Not implemented (not in the fixture list either): flagging non-compat
    // `Univapay\Requests\Handlers\RequestHandler` subclasses for the changed handler-cascade
    // contract. This needs class-hierarchy (extends/implements) resolution distinguishing the
    // four ported built-in handler classes from genuine consumer subclasses, which is materially
    // more involved than the Name/MethodCall matching above. Left as a follow-up.
};
