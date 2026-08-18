<?php

declare(strict_types=1);

namespace Univapay\Migrate;

/**
 * The full rename map from the legacy `univapay/php-sdk` (namespace `Univapay\*`)
 * to the runtime compat package `univapay/univapay-sdk-compat` (namespace `Univapay\Compat\*`).
 *
 * Mechanically enumerated from every class/interface/trait under `src/Univapay/` in the old
 * SDK tree, EXCEPT five purely-internal transport(-coupled) classes with no compat
 * counterpart, all instead caught by {@see \Univapay\Migrate\Rector\Rule\FlagInternalApiUsageRector}:
 * `Univapay\Requests\Requester`, `Univapay\Requests\HttpRequester`,
 * `Univapay\Requests\RequestContext`, `Univapay\Utility\HttpUtils`, and
 * `Univapay\Utility\RequesterUtils`.
 *
 * `Univapay\Utility\*` is otherwise included here: the compat package ships verbatim ports of
 * the standalone utility classes (DateUtils, FormatterUtils, FunctionalUtils, the Json\*
 * parsers, OptionsValidator, StringUtils, ValidationHelper), so those are renamed like
 * everything else. Usage is additionally marked with an `@internal` comment by
 * FlagInternalApiUsageRector, but the code still compiles because a real compat target exists.
 *
 * `HttpUtils` and `RequesterUtils` are the exception: both operate directly on the old transport
 * (`RequesterUtils` takes a `Univapay\Requests\RequestContext` argument on every method;
 * `HttpUtils::checkResponse()` takes a `WpOrg\Requests\Response`), which has no compat
 * equivalent at all -- the compat package's `Support\ApiCaller` and `Support\ExceptionMapper`
 * reimplement their behavior against the new engine SDK instead of porting them verbatim (no
 * `Univapay\Compat\Utility\HttpUtils` or `Univapay\Compat\Utility\RequesterUtils` class exists
 * in `univapay/univapay-sdk-compat`). They are therefore excluded here and flagged as
 * internal-API usage, same as the three `Requests\*` classes above.
 *
 * Classes that are functionally unsupported post-migration (Transfer, TransferStatusChange,
 * Ledger, the three transfer/ledger mixins, ApplePayPayment) are STILL present in this map and
 * ARE renamed to compat stubs -- so migrated code always compiles -- but {@see UNSUPPORTED_CLASSES}
 * additionally causes {@see \Univapay\Migrate\Rector\Rule\FlagUnsupportedFeatureRector} to flag
 * any reference to them.
 *
 * @see UnivapaySetList::PHP_SDK_TO_COMPAT for the Rector set that consumes this map.
 */
final class ClassMap
{
    /**
     * Full old-FQCN => compat-FQCN rename map, consumed by RenameClassRector.
     *
     * @var array<string, string>
     */
    public const SUPPORTED = [
        'Univapay\Enums\ActiveFilter' => 'Univapay\Compat\Enums\ActiveFilter',
        'Univapay\Enums\AppTokenMode' => 'Univapay\Compat\Enums\AppTokenMode',
        'Univapay\Enums\BankAccountStatus' => 'Univapay\Compat\Enums\BankAccountStatus',
        'Univapay\Enums\BankAccountType' => 'Univapay\Compat\Enums\BankAccountType',
        'Univapay\Enums\CallMethod' => 'Univapay\Compat\Enums\CallMethod',
        'Univapay\Enums\CancelStatus' => 'Univapay\Compat\Enums\CancelStatus',
        'Univapay\Enums\CardBrand' => 'Univapay\Compat\Enums\CardBrand',
        'Univapay\Enums\CardCategory' => 'Univapay\Compat\Enums\CardCategory',
        'Univapay\Enums\CardSubBrand' => 'Univapay\Compat\Enums\CardSubBrand',
        'Univapay\Enums\CardType' => 'Univapay\Compat\Enums\CardType',
        'Univapay\Enums\ChargeStatus' => 'Univapay\Compat\Enums\ChargeStatus',
        'Univapay\Enums\ChargeType' => 'Univapay\Compat\Enums\ChargeType',
        'Univapay\Enums\ConvenienceStore' => 'Univapay\Compat\Enums\ConvenienceStore',
        'Univapay\Enums\CursorDirection' => 'Univapay\Compat\Enums\CursorDirection',
        'Univapay\Enums\CvvAuthorizationStatus' => 'Univapay\Compat\Enums\CvvAuthorizationStatus',
        'Univapay\Enums\Field' => 'Univapay\Compat\Enums\Field',
        'Univapay\Enums\InstallmentPlanType' => 'Univapay\Compat\Enums\InstallmentPlanType',
        'Univapay\Enums\LedgerOrigin' => 'Univapay\Compat\Enums\LedgerOrigin',
        'Univapay\Enums\OnlineBrand' => 'Univapay\Compat\Enums\OnlineBrand',
        'Univapay\Enums\OsType' => 'Univapay\Compat\Enums\OsType',
        'Univapay\Enums\PaymentType' => 'Univapay\Compat\Enums\PaymentType',
        'Univapay\Enums\Period' => 'Univapay\Compat\Enums\Period',
        'Univapay\Enums\QrBrand' => 'Univapay\Compat\Enums\QrBrand',
        'Univapay\Enums\QrBrandMerchant' => 'Univapay\Compat\Enums\QrBrandMerchant',
        'Univapay\Enums\Reason' => 'Univapay\Compat\Enums\Reason',
        'Univapay\Enums\RecurringTokenPrivilege' => 'Univapay\Compat\Enums\RecurringTokenPrivilege',
        'Univapay\Enums\RefundReason' => 'Univapay\Compat\Enums\RefundReason',
        'Univapay\Enums\RefundStatus' => 'Univapay\Compat\Enums\RefundStatus',
        'Univapay\Enums\SubscriptionPlanType' => 'Univapay\Compat\Enums\SubscriptionPlanType',
        'Univapay\Enums\SubscriptionStatus' => 'Univapay\Compat\Enums\SubscriptionStatus',
        'Univapay\Enums\ThreeDSMode' => 'Univapay\Compat\Enums\ThreeDSMode',
        'Univapay\Enums\ThreeDSStatus' => 'Univapay\Compat\Enums\ThreeDSStatus',
        'Univapay\Enums\TokenType' => 'Univapay\Compat\Enums\TokenType',
        'Univapay\Enums\TransactionType' => 'Univapay\Compat\Enums\TransactionType',
        'Univapay\Enums\TransferStatus' => 'Univapay\Compat\Enums\TransferStatus',
        'Univapay\Enums\TypedEnum' => 'Univapay\Compat\Enums\TypedEnum',
        'Univapay\Enums\UsageLimit' => 'Univapay\Compat\Enums\UsageLimit',
        'Univapay\Enums\WebhookEvent' => 'Univapay\Compat\Enums\WebhookEvent',
        'Univapay\Errors\UnivapayError' => 'Univapay\Compat\Errors\UnivapayError',
        'Univapay\Errors\UnivapayForbiddenError' => 'Univapay\Compat\Errors\UnivapayForbiddenError',
        'Univapay\Errors\UnivapayInvalidWebhookData' => 'Univapay\Compat\Errors\UnivapayInvalidWebhookData',
        'Univapay\Errors\UnivapayLogicError' => 'Univapay\Compat\Errors\UnivapayLogicError',
        'Univapay\Errors\UnivapayNoMoreItemsError' => 'Univapay\Compat\Errors\UnivapayNoMoreItemsError',
        'Univapay\Errors\UnivapayNotFoundError' => 'Univapay\Compat\Errors\UnivapayNotFoundError',
        'Univapay\Errors\UnivapayRateLimitedError' => 'Univapay\Compat\Errors\UnivapayRateLimitedError',
        'Univapay\Errors\UnivapayRequestError' => 'Univapay\Compat\Errors\UnivapayRequestError',
        'Univapay\Errors\UnivapayResourceConflictError' => 'Univapay\Compat\Errors\UnivapayResourceConflictError',
        'Univapay\Errors\UnivapaySDKError' => 'Univapay\Compat\Errors\UnivapaySDKError',
        'Univapay\Errors\UnivapayServerError' => 'Univapay\Compat\Errors\UnivapayServerError',
        'Univapay\Errors\UnivapayUnauthorizedError' => 'Univapay\Compat\Errors\UnivapayUnauthorizedError',
        'Univapay\Errors\UnivapayUnknownWebhookEvent' => 'Univapay\Compat\Errors\UnivapayUnknownWebhookEvent',
        'Univapay\Errors\UnivapayValidationError' => 'Univapay\Compat\Errors\UnivapayValidationError',
        'Univapay\Requests\Handlers\BasicRetryHandler' => 'Univapay\Compat\Requests\Handlers\BasicRetryHandler',
        'Univapay\Requests\Handlers\NetworkRetryHandler' => 'Univapay\Compat\Requests\Handlers\NetworkRetryHandler',
        'Univapay\Requests\Handlers\RateLimitHandler' => 'Univapay\Compat\Requests\Handlers\RateLimitHandler',
        'Univapay\Requests\Handlers\RequestHandler' => 'Univapay\Compat\Requests\Handlers\RequestHandler',
        'Univapay\Resources\Authentication\AppJWT' => 'Univapay\Compat\Resources\Authentication\AppJWT',
        'Univapay\Resources\Authentication\InvalidJWTFormat' => 'Univapay\Compat\Resources\Authentication\InvalidJWTFormat',
        'Univapay\Resources\Authentication\MerchantAppJWT' => 'Univapay\Compat\Resources\Authentication\MerchantAppJWT',
        'Univapay\Resources\Authentication\StoreAppJWT' => 'Univapay\Compat\Resources\Authentication\StoreAppJWT',
        'Univapay\Resources\BankAccount' => 'Univapay\Compat\Resources\BankAccount',
        'Univapay\Resources\Cancel' => 'Univapay\Compat\Resources\Cancel',
        'Univapay\Resources\Charge' => 'Univapay\Compat\Resources\Charge',
        'Univapay\Resources\CheckoutInfo' => 'Univapay\Compat\Resources\CheckoutInfo',
        'Univapay\Resources\Configuration\CardBrandPercentFees' => 'Univapay\Compat\Resources\Configuration\CardBrandPercentFees',
        'Univapay\Resources\Configuration\CardChargeCvvConfirmation' => 'Univapay\Compat\Resources\Configuration\CardChargeCvvConfirmation',
        'Univapay\Resources\Configuration\CardConfiguration' => 'Univapay\Compat\Resources\Configuration\CardConfiguration',
        'Univapay\Resources\Configuration\ColorsConfiguration' => 'Univapay\Compat\Resources\Configuration\ColorsConfiguration',
        'Univapay\Resources\Configuration\Configuration' => 'Univapay\Compat\Resources\Configuration\Configuration',
        'Univapay\Resources\Configuration\ConvenienceConfiguration' => 'Univapay\Compat\Resources\Configuration\ConvenienceConfiguration',
        'Univapay\Resources\Configuration\InstallmentsConfiguration' => 'Univapay\Compat\Resources\Configuration\InstallmentsConfiguration',
        'Univapay\Resources\Configuration\LimitChargeByCardConfiguration' => 'Univapay\Compat\Resources\Configuration\LimitChargeByCardConfiguration',
        'Univapay\Resources\Configuration\OnlineConfiguration' => 'Univapay\Compat\Resources\Configuration\OnlineConfiguration',
        'Univapay\Resources\Configuration\PaidyConfiguration' => 'Univapay\Compat\Resources\Configuration\PaidyConfiguration',
        'Univapay\Resources\Configuration\QrScanConfiguration' => 'Univapay\Compat\Resources\Configuration\QrScanConfiguration',
        'Univapay\Resources\Configuration\RecurringConfiguration' => 'Univapay\Compat\Resources\Configuration\RecurringConfiguration',
        'Univapay\Resources\Configuration\SecurityConfiguration' => 'Univapay\Compat\Resources\Configuration\SecurityConfiguration',
        'Univapay\Resources\Configuration\SubscriptionConfiguration' => 'Univapay\Compat\Resources\Configuration\SubscriptionConfiguration',
        'Univapay\Resources\Configuration\SupportedBrand' => 'Univapay\Compat\Resources\Configuration\SupportedBrand',
        'Univapay\Resources\Configuration\ThemeConfiguration' => 'Univapay\Compat\Resources\Configuration\ThemeConfiguration',
        'Univapay\Resources\Configuration\TransferSchedule' => 'Univapay\Compat\Resources\Configuration\TransferSchedule',
        'Univapay\Resources\Configuration\UserTransactionsConfiguration' => 'Univapay\Compat\Resources\Configuration\UserTransactionsConfiguration',
        'Univapay\Resources\Jsonable' => 'Univapay\Compat\Resources\Jsonable',
        'Univapay\Resources\Ledger' => 'Univapay\Compat\Resources\Ledger',
        'Univapay\Resources\Merchant' => 'Univapay\Compat\Resources\Merchant',
        'Univapay\Resources\Mixins\GetBankAccounts' => 'Univapay\Compat\Resources\Mixins\GetBankAccounts',
        'Univapay\Resources\Mixins\GetCancels' => 'Univapay\Compat\Resources\Mixins\GetCancels',
        'Univapay\Resources\Mixins\GetCharges' => 'Univapay\Compat\Resources\Mixins\GetCharges',
        'Univapay\Resources\Mixins\GetLedgers' => 'Univapay\Compat\Resources\Mixins\GetLedgers',
        'Univapay\Resources\Mixins\GetRefunds' => 'Univapay\Compat\Resources\Mixins\GetRefunds',
        'Univapay\Resources\Mixins\GetScheduledPayments' => 'Univapay\Compat\Resources\Mixins\GetScheduledPayments',
        'Univapay\Resources\Mixins\GetStatusChanges' => 'Univapay\Compat\Resources\Mixins\GetStatusChanges',
        'Univapay\Resources\Mixins\GetStores' => 'Univapay\Compat\Resources\Mixins\GetStores',
        'Univapay\Resources\Mixins\GetSubscriptions' => 'Univapay\Compat\Resources\Mixins\GetSubscriptions',
        'Univapay\Resources\Mixins\GetTransactionTokens' => 'Univapay\Compat\Resources\Mixins\GetTransactionTokens',
        'Univapay\Resources\Mixins\GetTransactions' => 'Univapay\Compat\Resources\Mixins\GetTransactions',
        'Univapay\Resources\Mixins\GetTransfers' => 'Univapay\Compat\Resources\Mixins\GetTransfers',
        'Univapay\Resources\Paginated' => 'Univapay\Compat\Resources\Paginated',
        'Univapay\Resources\PaymentData\Address' => 'Univapay\Compat\Resources\PaymentData\Address',
        'Univapay\Resources\PaymentData\BillingData' => 'Univapay\Compat\Resources\PaymentData\BillingData',
        'Univapay\Resources\PaymentData\Card' => 'Univapay\Compat\Resources\PaymentData\Card',
        'Univapay\Resources\PaymentData\CardData' => 'Univapay\Compat\Resources\PaymentData\CardData',
        'Univapay\Resources\PaymentData\ConvenienceStoreData' => 'Univapay\Compat\Resources\PaymentData\ConvenienceStoreData',
        'Univapay\Resources\PaymentData\CvvAuthorize' => 'Univapay\Compat\Resources\PaymentData\CvvAuthorize',
        'Univapay\Resources\PaymentData\OnlineData' => 'Univapay\Compat\Resources\PaymentData\OnlineData',
        'Univapay\Resources\PaymentData\PaidyData' => 'Univapay\Compat\Resources\PaymentData\PaidyData',
        'Univapay\Resources\PaymentData\PhoneNumber' => 'Univapay\Compat\Resources\PaymentData\PhoneNumber',
        'Univapay\Resources\PaymentData\QrMerchantData' => 'Univapay\Compat\Resources\PaymentData\QrMerchantData',
        'Univapay\Resources\PaymentData\QrScanData' => 'Univapay\Compat\Resources\PaymentData\QrScanData',
        'Univapay\Resources\PaymentData\TokenThreeDS' => 'Univapay\Compat\Resources\PaymentData\TokenThreeDS',
        'Univapay\Resources\PaymentMethod\ApplePayPayment' => 'Univapay\Compat\Resources\PaymentMethod\ApplePayPayment',
        'Univapay\Resources\PaymentMethod\CardPayment' => 'Univapay\Compat\Resources\PaymentMethod\CardPayment',
        'Univapay\Resources\PaymentMethod\CardPaymentPatch' => 'Univapay\Compat\Resources\PaymentMethod\CardPaymentPatch',
        'Univapay\Resources\PaymentMethod\ConvenienceStorePayment' => 'Univapay\Compat\Resources\PaymentMethod\ConvenienceStorePayment',
        'Univapay\Resources\PaymentMethod\OnlinePayment' => 'Univapay\Compat\Resources\PaymentMethod\OnlinePayment',
        'Univapay\Resources\PaymentMethod\PaidyPayment' => 'Univapay\Compat\Resources\PaymentMethod\PaidyPayment',
        'Univapay\Resources\PaymentMethod\PaymentMethod' => 'Univapay\Compat\Resources\PaymentMethod\PaymentMethod',
        'Univapay\Resources\PaymentMethod\PaymentMethodPatch' => 'Univapay\Compat\Resources\PaymentMethod\PaymentMethodPatch',
        'Univapay\Resources\PaymentMethod\QrMerchantPayment' => 'Univapay\Compat\Resources\PaymentMethod\QrMerchantPayment',
        'Univapay\Resources\PaymentMethod\QrScanPayment' => 'Univapay\Compat\Resources\PaymentMethod\QrScanPayment',
        'Univapay\Resources\PaymentThreeDS' => 'Univapay\Compat\Resources\PaymentThreeDS',
        'Univapay\Resources\PaymentToken\OnlineToken' => 'Univapay\Compat\Resources\PaymentToken\OnlineToken',
        'Univapay\Resources\PaymentToken\QrMerchantToken' => 'Univapay\Compat\Resources\PaymentToken\QrMerchantToken',
        'Univapay\Resources\PaymentToken\ThreeDSIssuerToken' => 'Univapay\Compat\Resources\PaymentToken\ThreeDSIssuerToken',
        'Univapay\Resources\Pollable' => 'Univapay\Compat\Resources\Pollable',
        'Univapay\Resources\Redirect' => 'Univapay\Compat\Resources\Redirect',
        'Univapay\Resources\Refund' => 'Univapay\Compat\Resources\Refund',
        'Univapay\Resources\Resource' => 'Univapay\Compat\Resources\Resource',
        'Univapay\Resources\SimpleList' => 'Univapay\Compat\Resources\SimpleList',
        'Univapay\Resources\Store' => 'Univapay\Compat\Resources\Store',
        'Univapay\Resources\Subscription' => 'Univapay\Compat\Resources\Subscription',
        'Univapay\Resources\Subscription\InstallmentPlan' => 'Univapay\Compat\Resources\Subscription\InstallmentPlan',
        'Univapay\Resources\Subscription\ScheduleSettings' => 'Univapay\Compat\Resources\Subscription\ScheduleSettings',
        'Univapay\Resources\Subscription\ScheduledPayment' => 'Univapay\Compat\Resources\Subscription\ScheduledPayment',
        'Univapay\Resources\Subscription\SubscriptionPlan' => 'Univapay\Compat\Resources\Subscription\SubscriptionPlan',
        'Univapay\Resources\ThreeDSMPI' => 'Univapay\Compat\Resources\ThreeDSMPI',
        'Univapay\Resources\Transaction' => 'Univapay\Compat\Resources\Transaction',
        'Univapay\Resources\TransactionToken' => 'Univapay\Compat\Resources\TransactionToken',
        'Univapay\Resources\Transfer' => 'Univapay\Compat\Resources\Transfer',
        'Univapay\Resources\TransferStatusChange' => 'Univapay\Compat\Resources\TransferStatusChange',
        'Univapay\Resources\WebhookPayload' => 'Univapay\Compat\Resources\WebhookPayload',
        'Univapay\UnivapayClient' => 'Univapay\Compat\UnivapayClient',
        'Univapay\UnivapayClientOptions' => 'Univapay\Compat\UnivapayClientOptions',
        'Univapay\Utility\DateUtils' => 'Univapay\Compat\Utility\DateUtils',
        'Univapay\Utility\FormatterUtils' => 'Univapay\Compat\Utility\FormatterUtils',
        'Univapay\Utility\FunctionalUtils' => 'Univapay\Compat\Utility\FunctionalUtils',
        'Univapay\Utility\Json\JsonException' => 'Univapay\Compat\Utility\Json\JsonException',
        'Univapay\Utility\Json\JsonSchema' => 'Univapay\Compat\Utility\Json\JsonSchema',
        'Univapay\Utility\Json\NoSuchPathException' => 'Univapay\Compat\Utility\Json\NoSuchPathException',
        'Univapay\Utility\Json\RequiredValueNotFoundException' => 'Univapay\Compat\Utility\Json\RequiredValueNotFoundException',
        'Univapay\Utility\Json\SchemaComponent' => 'Univapay\Compat\Utility\Json\SchemaComponent',
        'Univapay\Utility\OptionsValidator' => 'Univapay\Compat\Utility\OptionsValidator',
        'Univapay\Utility\StringUtils' => 'Univapay\Compat\Utility\StringUtils',
        'Univapay\Utility\ValidationHelper' => 'Univapay\Compat\Utility\ValidationHelper',
    ];

    /**
     * Old AND compat FQCNs of classes/traits that are functionally unsupported by the compat
     * package after migration. Referencing any of these (as a class name, `new`, `instanceof`,
     * type hint, or trait `use`) is flagged by FlagUnsupportedFeatureRector, because the compat
     * implementation throws `UnivapayUnsupportedFeatureError` at runtime for any HTTP-touching
     * method on these classes.
     *
     * Scope note: `Transaction` (transaction history) is fully supported and must never be added
     * here. `BankAccount` (and its `GetBankAccounts` mixin) IS unsupported -- merchant payout
     * bank accounts are not exposed by the new engine SDK -- so it belongs here, joining
     * `Transfer`/`Ledger`/etc. `ClassMap::SUPPORTED` above still renames it to its compat stub
     * (the rename is additive/always-on; flagging on top of a still-valid rename target is
     * exactly the pattern every other entry in this const already follows).
     *
     * @var string[]
     */
    public const UNSUPPORTED_CLASSES = [
        'Univapay\Resources\Transfer',
        'Univapay\Compat\Resources\Transfer',
        'Univapay\Resources\TransferStatusChange',
        'Univapay\Compat\Resources\TransferStatusChange',
        'Univapay\Resources\Ledger',
        'Univapay\Compat\Resources\Ledger',
        'Univapay\Resources\Mixins\GetTransfers',
        'Univapay\Compat\Resources\Mixins\GetTransfers',
        'Univapay\Resources\Mixins\GetLedgers',
        'Univapay\Compat\Resources\Mixins\GetLedgers',
        'Univapay\Resources\Mixins\GetStatusChanges',
        'Univapay\Compat\Resources\Mixins\GetStatusChanges',
        'Univapay\Resources\PaymentMethod\ApplePayPayment',
        'Univapay\Compat\Resources\PaymentMethod\ApplePayPayment',
        'Univapay\Resources\BankAccount',
        'Univapay\Compat\Resources\BankAccount',
        'Univapay\Resources\Mixins\GetBankAccounts',
        'Univapay\Compat\Resources\Mixins\GetBankAccounts',
    ];

    /**
     * Method names that are unsupported regardless of which class in {@see UNSUPPORTED_CLASSES}
     * they are called on. Also flagged when called on a Univapay-typed receiver that cannot be
     * statically resolved (PHPStan type unknown) -- those flags are marked `(verify)` and are
     * warnings, not hard failures, unless `--strict` is passed to `bin/univapay-migrate`.
     *
     * `qrMerchantToken` is unsupported even though `Charge` itself is fully supported: the
     * underlying `/qr` endpoint is deprecated upstream and was never wired into the new SDK.
     *
     * `getBankAccount`/`listBankAccounts`/`listBankAccountContextsByOptions` (see
     * {@see UNSUPPORTED_CLASSES}'s doc block): the generic `fetch()`/`update()` BankAccount also
     * throws on (inherited from `Resource`, same as every other resource) are deliberately NOT
     * added here, mirroring how `Transfer::fetch()`/`update()` are not in this list either --
     * those method names are reused by every supported resource in the old SDK's surface, so
     * flagging them here would false-positive-flood every unrelated `->fetch()`/`->update()`
     * call in a migrated codebase.
     * `listBankAccountContextsByOptions` keeps the old SDK's own typo'd method name verbatim (not
     * `listBankAccountsByOptions`) -- see `Univapay\Compat\Resources\Mixins\GetBankAccounts`'s own
     * class doc.
     *
     * @var string[]
     */
    public const UNSUPPORTED_METHODS = [
        'getTransfer',
        'listTransfers',
        'listTransfersByOptions',
        'listLedgers',
        'listLedgersByOptions',
        'listStatusChanges',
        'listStatusChangesByOptions',
        'qrMerchantToken',
        'getBankAccount',
        'listBankAccounts',
        'listBankAccountContextsByOptions',
    ];
}
