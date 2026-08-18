<?php

declare(strict_types=1);

namespace Univapay\Migrate;

/**
 * The rename map + flag-rule configuration for the SECOND migration hop: `univapay/univapay-sdk-compat`
 * (namespace `Univapay\Compat\*`) to the native, APIMatic-generated `univapay/client-sdk`
 * (namespace `UnivaPay\*` -- capital P, not a typo, see PackageNames::NEW_SDK's own doc block).
 *
 * Unlike ClassMap (the phase-1 `php-sdk` -> `compat` map), this is deliberately
 * REVIEW-ASSISTED, not drop-in. The audit behind {@see SUPPORTED} (conducted against a real
 * checkout of both trees -- `univapay-php-sdk-compat`'s `src/` and the docs repo's generated
 * `sdk/php/src/`) found ZERO data classes and ZERO exception classes that qualify for a safe,
 * behavior-preserving, pure `use`/FQCN rename. Both audits are recorded below rather than
 * asserted, because "the map is empty" is exactly the kind of finding that looks like a mistake
 * unless the evidence is on record.
 *
 * ## Data-class audit (Univapay\Compat\Resources\* -> UnivaPay\Models\*)
 *
 * 62 of the 85 files under compat's `src/Resources/` (and every file under
 * `src/Resources/Configuration/`, `src/Resources/PaymentData/`, `src/Resources/PaymentToken/`,
 * `src/Resources/Subscription/`) declare `public $prop;` fields -- a verbatim port of the OLD
 * SDK's public-property DTO style (see ClassMap's own doc block: compat exists specifically to
 * keep the old SDK's public-property access working). Every file under the native tree's
 * `src/Models/` instead declares `private $prop;` with paired `getProp()`/`setProp()` methods
 * (APIMatic codegen convention, confirmed against e.g. `src/Models/TokenCreatePhoneNumber.php`).
 * A class-name-only rename does not change how the class is USED at call sites -- `$addr->line1`
 * keeps compiling against a renamed-but-still-public-property compat class, and immediately fatals
 * (`Error: Cannot access private property`) against the real native class, which has no public
 * `line1` at all. This is exactly the scope of the required "Public property access on compat
 * resources" flag category (not a rename target) -- see
 * {@see \Univapay\Migrate\Rector\Rule\FlagCompatManualMigrationRector}.
 *
 * The remaining 23 `Resources/` files that do NOT declare public properties are themselves each
 * disqualified for a different, independent reason, not because the audit ran out of files to
 * check:
 * - `Resources/Mixins/Get*` (11 files): traits, not data classes -- add `listX()`/`getX()`
 *   methods returning old-style `Paginated` iterators. No native trait equivalent exists at all
 *   (native pagination is cursor-param loops against a generated `Apis\*` method) -- in scope for
 *   the "Paginated / getNext() / getPrevious()" flag category, never a rename target.
 * - `Resources/PaymentMethod/*` (10 files, e.g. `CardPayment`): private-property, but constructed
 *   via a large POSITIONAL constructor that takes `PaymentType`/`TokenType`/`UsageLimit` TypedEnum
 *   singleton arguments (`PaymentType::CARD()`) -- native's equivalent request shape is a fluent
 *   `*Builder` class (e.g. `TokenCreateCardDataBuilder`) taking plain string consts, an entirely
 *   different construction paradigm, not a 1:1 property-for-property match. In scope for the
 *   "TypedEnum singleton usage" flag category.
 * - `Resources/Jsonable.php` / `Resources/Pollable.php`: traits/interfaces internal to compat's
 *   own hydration/polling machinery, never referenced by consumer code as a data shape.
 * - `Resources/Authentication/InvalidJWTFormat.php`: an exception (see the exception audit below),
 *   not a data class.
 *
 * Every `Enums\*` class was excluded from this audit entirely, by design, not oversight: they are
 * squarely the "TypedEnum singleton usage" flag category (`ChargeStatus::SUCCESSFUL()`,
 * `->getValue()`, `===`, `switch`) required by the migration plan, never a rename target -- native
 * enums are plain `public const` string groups (`UnivaPay\Models\ChargeStatus::SUCCESSFUL`), a
 * fundamentally different access pattern than a singleton method call.
 *
 * ## Exception-class audit (Univapay\Compat\Errors\* -> UnivaPay\Exceptions\*)
 *
 * The native SDK ships exactly two exception classes: `UnivaPay\Exceptions\ApiException` (base;
 * thrown for a network failure OR any non-OK HTTP status with no parseable error body) and
 * `UnivaPay\Exceptions\ApiErrorException extends ApiException` (thrown when the body parses into
 * the standard `{status, code, errors}` shape -- distinguishing WHICH error happened via
 * `getCodeProperty()`/`getHttpResponse()->getStatusCode()`, not via exception subclass).
 *
 * Compat instead ships 17 distinct exception classes. Sorting them into the same two native
 * buckets is never a 1:1, behavior-preserving catch-site rename:
 * - `UnivapayForbiddenError`, `UnivapayNotFoundError`, `UnivapayRateLimitedError`,
 *   `UnivapayResourceConflictError`, `UnivapayUnauthorizedError`, `UnivapayValidationError`,
 *   `UnivapayServerError`, and the base `UnivapayRequestError` itself all extend/represent an
 *   HTTP-status-specific request failure and would all collapse onto the SAME native class
 *   (`ApiErrorException`). That is precisely the "multi-class old->one-class new mapping" the
 *   migration plan calls out as NOT auto -- a codebase with `catch (UnivapayNotFoundError $e) {}
 *   catch (UnivapayForbiddenError $e) {}` distinguishing 404-vs-403 by exception TYPE would, after
 *   a blind rename, have two catch blocks for the identical type, and lose that distinction
 *   entirely (it now has to branch on `$e->getHttpResponse()->getStatusCode()` inside one catch
 *   block instead) -- a human has to make that rewrite, Rector renaming the class name alone would
 *   silently produce broken/duplicate catch types.
 * - `UnivapayNetworkError` looks tantalizingly 1:1 with base `ApiException` (both represent "no
 *   HTTP response at all"), but `ApiException` is ALSO the base class every `ApiErrorException`
 *   IS-A -- a renamed `catch (ApiException $e)` silently WIDENS what a network-only catch block
 *   catches to include every HTTP-status error too, the opposite direction of the multi-to-one
 *   problem above but just as unsafe to automate.
 * - `UnivapaySDKError`, `UnivapayLogicError`, `UnivapayInvalidWebhookData`,
 *   `UnivapayUnknownWebhookEvent`, `UnivapayNoMoreItemsError`, `UnivapayListDispatchError`,
 *   `UnivapayUnsupportedFeatureError` are purely client-side conditions (preflight validation,
 *   webhook parsing, pagination exhaustion, unsupported-feature stubs) the native SDK has no
 *   concept of at all -- there is no native exception to rename TO. `UnivapayInvalidWebhookData`/
 *   `UnivapayUnknownWebhookEvent` are covered by the "parseWebhookData()" flag category instead
 *   (native webhook Handler classes have their own, different error-signaling shape);
 *   `UnivapayNoMoreItemsError` is covered by the "Paginated" flag category.
 *
 * Net result: every compat exception reference is flagged, not renamed -- see the
 * "exception-handling" category in {@see FLAG_NAMESPACE_PREFIXES} below.
 *
 * @see UnivapaySetList::COMPAT_TO_NATIVE for the Rector set that consumes this map/config.
 * @see \Univapay\Migrate\Rector\Rule\FlagCompatManualMigrationRector for the flag rule driven by
 *      the FLAG_* consts below.
 */
final class NativeClassMap
{
    /**
     * Full compat-FQCN => native-FQCN rename map, consumed by RenameClassRector (same mechanism
     * ClassMap::SUPPORTED feeds in the phase-1 set). Deliberately EMPTY right now -- see this
     * class's own doc block for the full audit trail of why. Registered in
     * config/sets/compat-to-native.php anyway (an empty configuration is a no-op, not an error),
     * so a future, carefully-reviewed 1:1 addition needs no re-wiring of the set itself, only a
     * new entry here plus a MapIntegrityTest-style existence check against both trees.
     *
     * @var array<string, string>
     */
    public const SUPPORTED = [];

    /**
     * Exact compat FQCN => flag category. Checked before {@see FLAG_NAMESPACE_PREFIXES} (exact
     * match takes precedence over a prefix match) by
     * {@see \Univapay\Migrate\Rector\Rule\FlagCompatManualMigrationRector}.
     *
     * `Money\Money`/`Money\Currency` are moneyphp's OWN classes, not under `Univapay\Compat\` at
     * all -- compat re-exports them unchanged as its Money representation (see ClassMap's "what
     * does NOT change" list in the phase-1 README), but the native SDK has no moneyphp dependency
     * at all: every native model takes a flat `int $amount` + `string $currency` pair instead.
     * Matched here by exact FQCN, the same way FlagInternalApiUsageRector matches
     * `WpOrg\Requests\*` independently of the `Univapay\` prefix it otherwise keys off.
     *
     * @var array<string, string>
     */
    public const FLAG_EXACT_CLASSES = [
        'Univapay\Compat\UnivapayClient' => 'client-construction',
        'Univapay\Compat\UnivapayClientOptions' => 'client-construction',
        'Univapay\Compat\Resources\Paginated' => 'pagination',
        'Money\Money' => 'money',
        'Money\Currency' => 'money',
    ];

    /**
     * Compat namespace prefix => flag category. Checked by
     * {@see \Univapay\Migrate\Rector\Rule\FlagCompatManualMigrationRector} against a resolved
     * `Name` node's FQCN using longest-prefix-first matching (so the more specific
     * `Resources\Authentication\`/`Resources\Mixins\` entries win over the general `Resources\`
     * one below, which exists purely to drive the PropertyFetch/NullsafePropertyFetch gate for
     * the "public-property" category -- see the rule's own doc block for why property-fetch
     * nodes need this prefix rather than an exact class list: consumers type-hint dozens of
     * distinct compat resource classes, and every one of them shares the exact same
     * public-property problem).
     *
     * @var array<string, string>
     */
    public const FLAG_NAMESPACE_PREFIXES = [
        'Univapay\Compat\Enums\\' => 'typed-enum',
        'Univapay\Compat\Errors\\' => 'exception-handling',
        'Univapay\Compat\Resources\Authentication\\' => 'client-construction',
        'Univapay\Compat\Resources\Mixins\\' => 'pagination',
        'Univapay\Compat\Utility\\' => 'internal-utility',
        // General fallback for any other Resources\* class reference (Name nodes: use, new,
        // instanceof, type hints, catch, ::class) not already caught by a more specific prefix
        // above -- e.g. `Univapay\Compat\Resources\Charge`, `Resources\PaymentMethod\CardPayment`,
        // `Resources\Configuration\CardConfiguration`. Kept last so the specific-prefix checks
        // above win first (longest-prefix-first, see FlagCompatManualMigrationRector).
        'Univapay\Compat\Resources\\' => 'public-property',
    ];

    /**
     * Prefix used to gate the PropertyFetch/NullsafePropertyFetch flag (the "public-property"
     * category): any `->prop` access on a receiver whose resolved type is under this namespace is
     * flagged, since EVERY class under it uses public properties (see this class's own doc-block
     * audit) while every native `UnivaPay\Models\*` counterpart uses private properties + getters.
     */
    public const PUBLIC_PROPERTY_PREFIX = 'Univapay\Compat\Resources\\';

    /**
     * Method name => flag category, gated by receiver-type resolution exactly like
     * FlagUnsupportedFeatureRector's `methods` config (definite flag when the receiver resolves to
     * a `Univapay\Compat\*` class, `(verify)` when unresolved but the file references
     * `Univapay\Compat\` at all, skipped entirely for any other resolved type).
     *
     * `native()` (the compat package's own escape hatch onto the real
     * `UnivaPay\UnivapayClientSdkClient`, see the phase-1 README's "Migrating further to the
     * native SDK" section) is deliberately NEVER in this list -- calling it is exactly the
     * recommended mixed-mode pattern, not something to flag.
     *
     * @var array<string, string>
     */
    public const FLAG_METHODS = [
        'awaitResult' => 'poll',
        'parseWebhookData' => 'webhook',
        'getNext' => 'pagination',
        'getPrevious' => 'pagination',
    ];

    /**
     * Human-readable guidance per flag category, interpolated into the marker comment by
     * {@see \Univapay\Migrate\Rector\Rule\FlagCompatManualMigrationRector::buildComment()}. Kept
     * here (not hard-coded in the rule) so the wording for a given category is defined exactly
     * once regardless of which node shape (Name / MethodCall / PropertyFetch) triggered it.
     *
     * @var array<string, string>
     */
    public const FLAG_GUIDANCE = [
        'typed-enum' => 'TypedEnum singleton usage (e.g. ChargeStatus::SUCCESSFUL(), ->getValue(), '
            . '=== comparisons, switch) has no native equivalent object -- UnivaPay\Models\* enums '
            . 'are plain string public const groups (e.g. UnivaPay\Models\ChargeStatus::SUCCESSFUL '
            . "=== 'successful'). Replace singleton calls/comparisons with the native class's const.",
        'money' => 'Money\Money/Money\Currency (moneyphp) values have no native equivalent type -- '
            . 'UnivaPay\Models\* takes a flat int $amount + string $currency pair instead of a '
            . 'moneyphp object. Replace with $money->getAmount() (minor units) and '
            . '$money->getCurrency()->getCode().',
        'public-property' => 'Public property access on a compat resource has no native equivalent '
            . 'field -- UnivaPay\Models\* exposes only private properties behind getFoo()/setFoo() '
            . 'methods, and a native Apis\* call returns an ApiResponse whose model is reached via '
            . '->getResult(). Replace $obj->prop with $obj->getProp() (or ->getResult()->getProp() '
            . 'on a raw API response).',
        'poll' => 'awaitResult() has no native equivalent method -- replace with the matching native '
            . 'poller on the generated Apis\* controller: pollCharge()/pollRefund()/pollCancel()/'
            . 'pollSubscription() (each returns an ApiResponse once the resource leaves its pending '
            . 'state or the attempt budget is exhausted).',
        'pagination' => 'Paginated/getNext()/getPrevious() (and the Mixins\Get* traits providing '
            . 'them) have no native equivalent -- native list endpoints take a cursor query '
            . 'parameter directly; replace with a loop that re-calls the same Apis\* list method '
            . "passing the previous page's last cursor value.",
        'webhook' => 'parseWebhookData() has no native equivalent method -- replace with the '
            . 'matching native UnivaPay\Events\Webhooks\*Handler class (ChargeHandler, '
            . 'RefundHandler, CancelHandler, SubscriptionHandler, TokenHandler, '
            . 'BankTransferHandler, CustomsHandler), which parses and validates the payload '
            . 'directly instead of returning an old-SDK-shaped WebhookPayload.',
        'client-construction' => 'UnivapayClient/UnivapayClientOptions construction and '
            . 'AppJWT/StoreAppJWT/MerchantAppJWT token building have no native equivalent -- '
            . 'replace with UnivaPay\UnivapayClientSdkClientBuilder::init()->...->build() plus '
            . 'UnivaPay\Authentication\BearerAuthCredentialsBuilder::init($secretKey, $jwtToken).',
        'exception-handling' => 'This compat exception class has no 1:1 native equivalent -- the '
            . 'native SDK throws only UnivaPay\Exceptions\ApiException (network failures or a '
            . 'non-OK status with no parseable body) and UnivaPay\Exceptions\ApiErrorException '
            . '(a parsed {status, code, errors} body). Distinguish which error happened via '
            . "\$e->getHttpResponse()->getStatusCode() / \$e->getCodeProperty(), not via exception "
            . 'class -- review every catch site by hand.',
        'internal-utility' => 'This is a verbatim port of an old-SDK standalone utility helper with '
            . 'no native SDK equivalent at all (the native SDK\'s own Utils\* classes are internal '
            . 'codegen support, not a public replacement) -- port the logic you depend on yourself, '
            . 'or keep vendoring this one function via the compat package.',
    ];
}
