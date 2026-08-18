<?php

declare(strict_types=1);

namespace Univapay\Migrate\Tests;

use PHPUnit\Framework\TestCase;
use Univapay\Migrate\NativeClassMap;

/**
 * Structural self-consistency checks for NativeClassMap -- the phase-2 (compat -> native)
 * counterpart of ClassMapTest. Unlike ClassMapTest, this suite does NOT assert `SUPPORTED` is
 * non-empty: the whole point of NativeClassMap's own doc-block audit is that it currently is
 * empty, by design (see that class's doc block for the full audit trail). What this suite DOES
 * assert is that the FLAG_* configuration tables driving FlagCompatManualMigrationRector are
 * internally well-formed -- every category referenced by an exact-class/namespace-prefix/method
 * entry has a guidance string, `native()` is never accidentally added as a flagged method, etc.
 *
 * No env vars, no sibling checkout needed -- see tests/NativeMapIntegrityTest.php for the
 * @group map-integrity suite that checks NativeClassMap::SUPPORTED against real installed/sibling
 * trees.
 */
final class NativeClassMapTest extends TestCase
{
    private const FQCN_PATTERN = '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)+$/';

    public function testSupportedMapIsCurrentlyEmptyByDesign(): void
    {
        // See NativeClassMap's own doc block: the audit against real compat/native trees found
        // zero data classes and zero exception classes qualifying for a safe, behavior-preserving,
        // pure use/FQCN rename. This assertion is deliberately here (not just "not asserted either
        // way") so a future PR that adds an entry must consciously update this test too --
        // forcing the addition to be reviewed against the same bar the doc-block audit sets,
        // rather than silently drifting.
        self::assertSame(
            [],
            NativeClassMap::SUPPORTED,
            'NativeClassMap::SUPPORTED is no longer empty -- if this is a deliberate, reviewed ' .
                '1:1 addition, update this test alongside it and add a MapIntegrityTest-style ' .
                'existence check (see tests/NativeMapIntegrityTest.php) for the new entry.'
        );
    }

    public function testAllFlagExactClassKeysAreValidFqcns(): void
    {
        foreach (array_keys(NativeClassMap::FLAG_EXACT_CLASSES) as $fqcn) {
            self::assertIsString($fqcn);
            self::assertMatchesRegularExpression(self::FQCN_PATTERN, $fqcn, "'{$fqcn}' is not a valid FQCN.");
        }
    }

    public function testAllFlagNamespacePrefixesEndWithABackslash(): void
    {
        foreach (array_keys(NativeClassMap::FLAG_NAMESPACE_PREFIXES) as $prefix) {
            self::assertStringEndsWith('\\', $prefix, "Namespace prefix '{$prefix}' must end with a backslash.");
        }
    }

    public function testPublicPropertyPrefixIsOneOfTheRegisteredNamespacePrefixes(): void
    {
        self::assertArrayHasKey(
            NativeClassMap::PUBLIC_PROPERTY_PREFIX,
            NativeClassMap::FLAG_NAMESPACE_PREFIXES,
            'PUBLIC_PROPERTY_PREFIX must also be registered in FLAG_NAMESPACE_PREFIXES so a Name-node ' .
                'reference to a Resources\\* class (e.g. a type hint) is flagged consistently with ' .
                'a PropertyFetch on the same class.'
        );
        self::assertSame(
            'public-property',
            NativeClassMap::FLAG_NAMESPACE_PREFIXES[NativeClassMap::PUBLIC_PROPERTY_PREFIX]
        );
    }

    public function testNativeEscapeHatchMethodIsNeverFlagged(): void
    {
        self::assertArrayNotHasKey(
            'native',
            NativeClassMap::FLAG_METHODS,
            'native() is the documented mixed-mode escape hatch onto the real UnivaPay client -- ' .
                'it must never be added to FLAG_METHODS.'
        );
    }

    /**
     * Every category value appearing anywhere across FLAG_EXACT_CLASSES/FLAG_NAMESPACE_PREFIXES/
     * FLAG_METHODS must have a corresponding FLAG_GUIDANCE entry, or
     * FlagCompatManualMigrationRector::buildComment() silently falls back to a generic "review by
     * hand" message instead of naming the real native equivalent.
     */
    public function testEveryReferencedCategoryHasGuidance(): void
    {
        $categories = array_unique(array_merge(
            array_values(NativeClassMap::FLAG_EXACT_CLASSES),
            array_values(NativeClassMap::FLAG_NAMESPACE_PREFIXES),
            array_values(NativeClassMap::FLAG_METHODS)
        ));

        foreach ($categories as $category) {
            self::assertArrayHasKey(
                $category,
                NativeClassMap::FLAG_GUIDANCE,
                "Category '{$category}' has no FLAG_GUIDANCE entry."
            );
        }
    }

    public function testEveryFlagGuidanceEntryIsANonEmptyString(): void
    {
        foreach (NativeClassMap::FLAG_GUIDANCE as $category => $guidance) {
            self::assertIsString($guidance);
            self::assertNotSame('', trim($guidance), "FLAG_GUIDANCE['{$category}'] must not be empty.");
        }
    }

    public function testRequiredFlagCategoriesArePresent(): void
    {
        // The seven categories the migration plan explicitly requires coverage for, plus this
        // package's own additional "exception-handling" and "internal-utility" categories (see
        // NativeClassMap's own doc block for why those two are needed beyond the required seven).
        $required = [
            'typed-enum',
            'money',
            'public-property',
            'poll',
            'pagination',
            'webhook',
            'client-construction',
            'exception-handling',
            'internal-utility',
        ];

        foreach ($required as $category) {
            self::assertArrayHasKey($category, NativeClassMap::FLAG_GUIDANCE, "Missing required category '{$category}'.");
        }
    }

    public function testFlagMethodsKeysAreValidMethodNames(): void
    {
        foreach (array_keys(NativeClassMap::FLAG_METHODS) as $method) {
            self::assertMatchesRegularExpression('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $method);
        }
    }
}
