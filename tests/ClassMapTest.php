<?php

declare(strict_types=1);

namespace Univapay\Migrate\Tests;

use PHPUnit\Framework\TestCase;
use Univapay\Migrate\ClassMap;

/**
 * Asserts ClassMap is internally well-formed. This does NOT assert the compat package's classes
 * actually exist (that is the CI "map-integrity" job, which runs against an installed
 * univapay/univapay-sdk-compat and is out of scope until that package exists) -- only that the
 * map itself is structurally sound.
 */
final class ClassMapTest extends TestCase
{
    private const FQCN_PATTERN = '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)+$/';

    public function testSupportedMapIsNotEmpty(): void
    {
        self::assertNotEmpty(ClassMap::SUPPORTED, 'ClassMap::SUPPORTED must not be empty.');
    }

    public function testSupportedMapHasExpectedSize(): void
    {
        // Mechanically enumerated from every class/interface/trait under the old SDK's
        // src/Univapay/ tree (159 files), minus 5 purely-internal transport(-coupled) classes
        // that have no compat target: Requester, HttpRequester, RequestContext (the transport
        // itself), plus HttpUtils and RequesterUtils (both operate directly on that transport and
        // were never actually ported to the compat package; see ClassMap's own doc block) = 154.
        self::assertCount(154, ClassMap::SUPPORTED);
    }

    public function testAllKeysAreValidFullyQualifiedClassNames(): void
    {
        foreach (array_keys(ClassMap::SUPPORTED) as $key) {
            self::assertIsString($key);
            self::assertMatchesRegularExpression(
                self::FQCN_PATTERN,
                $key,
                "Key '{$key}' is not a valid fully-qualified class name."
            );
        }
    }

    public function testAllValuesAreValidFullyQualifiedClassNames(): void
    {
        foreach (ClassMap::SUPPORTED as $key => $value) {
            self::assertIsString($value);
            self::assertMatchesRegularExpression(
                self::FQCN_PATTERN,
                $value,
                "Value '{$value}' (for key '{$key}') is not a valid fully-qualified class name."
            );
        }
    }

    public function testNoDuplicateKeys(): void
    {
        $keys = array_keys(ClassMap::SUPPORTED);
        $unique = array_unique($keys);
        self::assertSame(
            count($keys),
            count($unique),
            'ClassMap::SUPPORTED has duplicate keys (a PHP array literal would silently keep only the last one).'
        );
    }

    public function testNoDuplicateValues(): void
    {
        $values = array_values(ClassMap::SUPPORTED);
        $unique = array_unique($values);
        self::assertSame(
            count($values),
            count($unique),
            'ClassMap::SUPPORTED has duplicate compat targets -- two old classes would collapse onto one.'
        );
    }

    public function testAllValuesStartWithUnivapayCompatNamespace(): void
    {
        foreach (ClassMap::SUPPORTED as $key => $value) {
            self::assertStringStartsWith(
                'Univapay\\Compat\\',
                $value,
                "Value '{$value}' (for key '{$key}') must start with the Univapay\\Compat\\ namespace prefix."
            );
        }
    }

    public function testAllKeysStartWithUnivapayNamespaceButNotCompatOrMigrate(): void
    {
        foreach (array_keys(ClassMap::SUPPORTED) as $key) {
            self::assertStringStartsWith('Univapay\\', $key);
            self::assertNotSame(
                0,
                strncmp($key, 'Univapay\\Compat\\', strlen('Univapay\\Compat\\')),
                "Key '{$key}' must not already be in the Univapay\\Compat\\ namespace."
            );
            self::assertNotSame(
                0,
                strncmp($key, 'Univapay\\Migrate\\', strlen('Univapay\\Migrate\\')),
                "Key '{$key}' must not be in the Univapay\\Migrate\\ namespace."
            );
        }
    }

    public function testKeyToValueIsAPureNamespacePrefixSwapWithIdenticalBasenameTail(): void
    {
        foreach (ClassMap::SUPPORTED as $key => $value) {
            $expected = 'Univapay\\Compat\\' . substr($key, strlen('Univapay\\'));
            self::assertSame(
                $expected,
                $value,
                "'{$key}' => '{$value}' is not a pure Univapay\\ -> Univapay\\Compat\\ prefix swap."
            );
        }
    }

    public function testExcludedInternalTransportClassesAreNotInTheMap(): void
    {
        $excluded = [
            'Univapay\\Requests\\Requester',
            'Univapay\\Requests\\HttpRequester',
            'Univapay\\Requests\\RequestContext',
            // HttpUtils and RequesterUtils both take a RequestContext/WpOrg\Requests\Response
            // argument on every method and have no compat replacement -- the compat package
            // reimplements their behavior in Support\ApiCaller / Support\ExceptionMapper instead
            // of porting them verbatim.
            'Univapay\\Utility\\HttpUtils',
            'Univapay\\Utility\\RequesterUtils',
        ];
        foreach ($excluded as $fqcn) {
            self::assertArrayNotHasKey(
                $fqcn,
                ClassMap::SUPPORTED,
                "{$fqcn} has no compat target and must be handled by FlagInternalApiUsageRector, not renamed."
            );
        }
    }

    public function testUtilityNamespaceIsIncludedPerBindingAmendment(): void
    {
        self::assertArrayHasKey('Univapay\\Utility\\FormatterUtils', ClassMap::SUPPORTED);
        self::assertArrayHasKey('Univapay\\Utility\\Json\\JsonSchema', ClassMap::SUPPORTED);
    }

    public function testUnsupportedClassesConstIsWellFormedAndSubsetOfKnownFqcns(): void
    {
        self::assertNotEmpty(ClassMap::UNSUPPORTED_CLASSES);
        foreach (ClassMap::UNSUPPORTED_CLASSES as $fqcn) {
            self::assertIsString($fqcn);
            self::assertMatchesRegularExpression(self::FQCN_PATTERN, $fqcn);
            self::assertMatchesRegularExpression('/^Univapay\\\\(Compat\\\\)?/', $fqcn);
        }
    }

    public function testTransactionIsNeverMarkedUnsupported(): void
    {
        // Transaction (transaction history) is fully supported, not unsupported. BankAccount
        // used to share this guarantee, but merchant payout bank accounts are now permanently
        // unsupported -- see testBankAccountIsMarkedUnsupported() below for its opposite
        // assertion, and ClassMap::UNSUPPORTED_CLASSES's own doc block for the full story.
        foreach (ClassMap::UNSUPPORTED_CLASSES as $fqcn) {
            self::assertStringNotContainsString('\\Transaction', $fqcn);
        }
    }

    public function testBankAccountIsMarkedUnsupported(): void
    {
        // Merchant payout bank accounts are unsupported. Both FQCN forms must be flagged, same as
        // every other UNSUPPORTED_CLASSES entry.
        self::assertContains('Univapay\\Resources\\BankAccount', ClassMap::UNSUPPORTED_CLASSES);
        self::assertContains('Univapay\\Compat\\Resources\\BankAccount', ClassMap::UNSUPPORTED_CLASSES);
        self::assertContains('Univapay\\Resources\\Mixins\\GetBankAccounts', ClassMap::UNSUPPORTED_CLASSES);
        self::assertContains('Univapay\\Compat\\Resources\\Mixins\\GetBankAccounts', ClassMap::UNSUPPORTED_CLASSES);

        // Still a rename target -- flagging is additive, not a replacement for the rename (see
        // ClassMap::SUPPORTED's own doc block, and UNSUPPORTED_CLASSES's doc block).
        self::assertArrayHasKey('Univapay\\Resources\\BankAccount', ClassMap::SUPPORTED);
        self::assertArrayHasKey('Univapay\\Resources\\Mixins\\GetBankAccounts', ClassMap::SUPPORTED);
    }

    public function testUnsupportedMethodsIncludesTheBankAccountMethodTrio(): void
    {
        // getBankAccount/listBankAccounts/listBankAccountContextsByOptions all throw permanently
        // in compat -- see ClassMap::UNSUPPORTED_METHODS's own doc block for why fetch()/update()
        // are deliberately NOT included here.
        self::assertContains('getBankAccount', ClassMap::UNSUPPORTED_METHODS);
        self::assertContains('listBankAccounts', ClassMap::UNSUPPORTED_METHODS);
        self::assertContains('listBankAccountContextsByOptions', ClassMap::UNSUPPORTED_METHODS);
        self::assertNotContains('fetch', ClassMap::UNSUPPORTED_METHODS);
        self::assertNotContains('update', ClassMap::UNSUPPORTED_METHODS);
    }

    public function testUnsupportedMethodsConstIsWellFormedAndIncludesQrMerchantToken(): void
    {
        self::assertNotEmpty(ClassMap::UNSUPPORTED_METHODS);
        foreach (ClassMap::UNSUPPORTED_METHODS as $method) {
            self::assertIsString($method);
            self::assertMatchesRegularExpression('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $method);
        }
        self::assertContains('qrMerchantToken', ClassMap::UNSUPPORTED_METHODS);
    }
}
