<?php

declare(strict_types=1);

namespace Univapay\Migrate\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Regression protection for the structural claims NativeClassMap's own doc-block audit makes
 * about the installed `univapay/client-sdk` (require-dev, real Packagist package -- see
 * composer.json). If a future `univapay/client-sdk` release changes any of these shapes, THIS
 * suite fails first, forcing a re-audit of NativeClassMap/FlagCompatManualMigrationRector before
 * anyone notices via a silently-wrong flag/rename.
 *
 * Deliberately NOT `@group map-integrity` and needs no env var: `univapay/client-sdk` is a normal
 * require-dev dependency of THIS package, loaded via the same `vendor/autoload.php` every other
 * test in this suite already uses (unlike `univapay/univapay-sdk-compat`, which is only ever a
 * sibling checkout, never a real dependency of this package -- see tests/MapIntegrityTest.php's
 * own doc block for why THAT loading pattern needs isolation). No cross-process type-resolution
 * pollution risk applies here.
 */
final class NativeSdkAuditTest extends TestCase
{
    public function testClientSdkIsInstalled(): void
    {
        self::assertTrue(
            class_exists('UnivaPay\\UnivapayClientSdkClientBuilder'),
            'univapay/client-sdk does not appear to be installed -- run `composer install` ' .
                '(it is a require-dev dependency of this package specifically so this audit can run).'
        );
    }

    /**
     * Every native Models\* class exposes NO public properties -- the foundational claim behind
     * the "public-property" flag category (a compat resource's public properties have no native
     * counterpart to keep working after a bare rename). Fields are private (the declared model
     * data, behind getFoo()/setFoo()) or protected (generated-codegen internals, e.g.
     * `$propertyNames` used by `addAdditionalProperty()`/`findAdditionalProperty()`) -- either is
     * fine for this claim, since neither is directly readable/writable from outside the class the
     * way a compat resource's public property is.
     *
     * @dataProvider provideModelClasses
     */
    public function testNativeModelClassesExposeNoPublicProperties(string $class): void
    {
        $reflection = new ReflectionClass($class);
        foreach ($reflection->getProperties() as $property) {
            self::assertFalse(
                $property->isPublic(),
                "{$class}::\${$property->getName()} is public -- NativeClassMap's audit claim " .
                    "(no native Models\\* property is public) no longer holds; re-audit the " .
                    "'public-property' flag category."
            );
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function provideModelClasses(): array
    {
        return [
            'TokenCreatePhoneNumber' => ['UnivaPay\\Models\\TokenCreatePhoneNumber'],
            'Charge' => ['UnivaPay\\Models\\Charge'],
            'Refund' => ['UnivaPay\\Models\\Refund'],
            'Subscription' => ['UnivaPay\\Models\\Subscription'],
        ];
    }

    /**
     * Native "enums" are plain `public const` string groups on an uninstantiated class, not
     * TypedEnum-style singleton objects -- the foundational claim behind the "typed-enum" flag
     * category.
     */
    public function testNativeChargeStatusIsAPlainConstStringGroup(): void
    {
        self::assertSame('successful', \UnivaPay\Models\ChargeStatus::SUCCESSFUL);

        // Every PUBLIC constant is a plain string case (e.g. SUCCESSFUL, PENDING, ...) -- the
        // class also declares a private `_ALL_VALUES` aggregate array constant (an internal
        // codegen convenience for validation), which is deliberately excluded from this check
        // since it isn't itself an enum case.
        $reflection = new ReflectionClass(\UnivaPay\Models\ChargeStatus::class);
        $publicConstants = array_filter(
            $reflection->getReflectionConstants(),
            static fn ($constant): bool => $constant->isPublic()
        );
        self::assertNotEmpty($publicConstants);
        foreach ($publicConstants as $constant) {
            self::assertIsString($constant->getValue());
        }
    }

    /**
     * The native exception hierarchy is exactly two classes, `ApiErrorException extends
     * ApiException` -- the foundational claim behind the "exception-handling" flag category (a
     * compat exception subclass has no 1:1 native counterpart to rename to).
     */
    public function testNativeExceptionHierarchyIsApiExceptionAndApiErrorException(): void
    {
        self::assertTrue(class_exists('UnivaPay\\Exceptions\\ApiException'));
        self::assertTrue(class_exists('UnivaPay\\Exceptions\\ApiErrorException'));
        self::assertTrue(is_subclass_of('UnivaPay\\Exceptions\\ApiErrorException', 'UnivaPay\\Exceptions\\ApiException'));
    }

    /**
     * The native poller methods `awaitResult()` is flagged in favor of -- the foundational claim
     * behind the "poll" flag category's guidance text.
     */
    public function testNativePollMethodsExistOnTheirRespectiveApis(): void
    {
        self::assertTrue(method_exists('UnivaPay\\Apis\\ChargesApi', 'pollCharge'));
        self::assertTrue(method_exists('UnivaPay\\Apis\\RefundsApi', 'pollRefund'));
        self::assertTrue(method_exists('UnivaPay\\Apis\\CancelsApi', 'pollCancel'));
        self::assertTrue(method_exists('UnivaPay\\Apis\\SubscriptionsApi', 'pollSubscription'));
    }

    /**
     * The native webhook Handler classes `parseWebhookData()` is flagged in favor of -- the
     * foundational claim behind the "webhook" flag category's guidance text.
     */
    public function testNativeWebhookHandlerClassesExist(): void
    {
        foreach (
            [
                'ChargeHandler', 'RefundHandler', 'CancelHandler', 'SubscriptionHandler',
                'TokenHandler', 'BankTransferHandler', 'CustomsHandler',
            ] as $handler
        ) {
            self::assertTrue(
                class_exists("UnivaPay\\Events\\Webhooks\\{$handler}"),
                "UnivaPay\\Events\\Webhooks\\{$handler} does not exist."
            );
        }
    }

    /**
     * The native client/auth builders `UnivapayClient`/`AppJWT` construction is flagged in favor
     * of -- the foundational claim behind the "client-construction" flag category's guidance text.
     */
    public function testNativeClientAndAuthBuildersExist(): void
    {
        self::assertTrue(class_exists('UnivaPay\\UnivapayClientSdkClientBuilder'));
        self::assertTrue(class_exists('UnivaPay\\Authentication\\BearerAuthCredentialsBuilder'));
        self::assertTrue(method_exists('UnivaPay\\UnivapayClientSdkClientBuilder', 'init'));
        self::assertTrue(method_exists('UnivaPay\\Authentication\\BearerAuthCredentialsBuilder', 'init'));
    }

    /**
     * The native SDK has no moneyphp dependency at all -- the foundational claim behind the
     * "money" flag category (Money\Money/Money\Currency have no native equivalent type).
     */
    public function testNativeSdkHasNoMoneyphpDependency(): void
    {
        self::assertFalse(
            class_exists('Money\\Money'),
            'Money\\Money is autoloadable -- if univapay/client-sdk now depends on moneyphp/money ' .
                '(directly or transitively), re-audit the "money" flag category (it may no longer ' .
                'be true that native models take a flat int amount + string currency pair).'
        );
    }
}
