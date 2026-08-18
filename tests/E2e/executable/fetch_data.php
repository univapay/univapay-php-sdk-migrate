<?php

/**
 * Adapted, EXECUTABLE copy of tests/E2e/expected/examples/fetch_data.php (bank accounts are
 * permanently unsupported). The golden file at that path is NOT touched. Adaptations:
 *
 *   - `AppJWT::createToken('token', 'secret')` referenced `AppJWT` with NO `use` import anywhere
 *     in the golden file at all -- a real, preserved old-SDK-example bug (see
 *     GoldenMigrationTest's own doc comment, "the bare-AppJWT bug"): unqualified `AppJWT` resolves
 *     to the GLOBAL namespace, where no such class exists, so the golden file as printed would
 *     fatal with "Class \"AppJWT\" not found" if actually run. Fixed here with a real `use`
 *     import + e2eMerchantAppToken() (a merchant-level token -- every call this script makes is
 *     merchant-wide; none of them route through `Support\Bridge::requireStoreId()`).
 *   - `new UnivapayClient(...)` -> given a UnivapayClientOptions pointed at UNIVAPAY_PRISM_URL.
 *   - The four `@univapay-migrate:unsupported`-flagged call sites (`listTransfers()`/
 *     `getTransfer()`/`listBankAccounts()`/`getBankAccount()`) are wrapped in `e2eAssertThrows()`
 *     instead of executed unconditionally -- this IS the regression guard for the ExceptionMapper
 *     fix and for `FlagUnsupportedFeatureRector`'s runtime counterpart: proving the
 *     flagged call sites really do throw `UnivapayUnsupportedFeatureError` at runtime, not just
 *     that Rector flagged them statically. The original `if (sizeof($transfers->items) > 0) { ... }`
 *     guard is removed since `listTransfers()` now always throws before returning anything to
 *     iterate -- there is no `$transfers->items` to check; likewise the golden file's
 *     `current($accounts->items)->fetch()` line is removed here since `listBankAccounts()` now
 *     always throws before returning anything to call `current()` on.
 *
 * Every other call in this script is unchanged and real: getMe(), listStores()/current()->fetch(),
 * getStore(), listCharges()/current()->fetch(), getCharge(), listRefunds(),
 * listSubscriptions()/current()->fetch(), getSubscription(), and listTransactions()->getNext()
 * (see synthetic_execution_checks.php for the dedicated NoMoreItems assertion on that last one --
 * every list example in the spec is static with `has_more: false`, so this getNext() call throws
 * UnivapayNoMoreItemsError too; wrapped here the same way).
 */

use Univapay\Compat\UnivapayClient;
use Univapay\Compat\UnivapayClientOptions;
use Univapay\Compat\Resources\Authentication\AppJWT;
use Univapay\Compat\Errors\UnivapayUnsupportedFeatureError;
use Univapay\Compat\Errors\UnivapayNoMoreItemsError;

require_once('vendor/autoload.php');
require __DIR__ . '/_bootstrap.php';

$client = new UnivapayClient(e2eMerchantAppToken(), new UnivapayClientOptions(e2ePrismUrl()));

$client->getMe();
$stores = $client->listStores();
$store = current($stores->items)->fetch();
$client->getStore($store->id);
// @univapay-migrate:unsupported listBankAccounts — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
e2eAssertThrows(
    function () use ($client) {
        $client->listBankAccounts();
    },
    UnivapayUnsupportedFeatureError::class,
    "listBankAccounts() throws UnivapayUnsupportedFeatureError at runtime (bank accounts are permanently unsupported)"
);
// @univapay-migrate:unsupported getBankAccount — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
e2eAssertThrows(
    function () use ($client) {
        $client->getBankAccount('11ef0000-0000-4000-8000-000000000050');
    },
    UnivapayUnsupportedFeatureError::class,
    "getBankAccount() throws UnivapayUnsupportedFeatureError at runtime"
);
// @univapay-migrate:unsupported listTransfers — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
e2eAssertThrows(
    function () use ($client) {
        $client->listTransfers();
    },
    UnivapayUnsupportedFeatureError::class,
    "listTransfers() throws UnivapayUnsupportedFeatureError at runtime (ExceptionMapper/unsupported-feature regression guard)"
);
// @univapay-migrate:unsupported getTransfer — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
e2eAssertThrows(
    function () use ($client) {
        $client->getTransfer('11ef0000-0000-4000-8000-000000000099');
    },
    UnivapayUnsupportedFeatureError::class,
    "getTransfer() throws UnivapayUnsupportedFeatureError at runtime"
);
$charges = $client->listCharges();
$charge = current($charges->items)->fetch();
$client->getCharge($charge->storeId, $charge->id);
$refunds = $charge->listRefunds();
$subscriptions = $client->listSubscriptions();
if (sizeof($subscriptions->items) > 0) {
    $subscription = current($subscriptions->items)->fetch();
    $client->getSubscription($subscription->storeId, $subscription->id);
}
e2eAssertThrows(
    function () use ($client) {
        $client->listTransactions()->getNext();
    },
    UnivapayNoMoreItemsError::class,
    "listTransactions()->getNext() throws UnivapayNoMoreItemsError (every list example in the spec is static, has_more: false)"
);

echo "PASS fetch_data\n";
