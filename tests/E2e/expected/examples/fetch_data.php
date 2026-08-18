<?php

use Univapay\Compat\UnivapayClient;

require_once('vendor/autoload.php');

$client = new UnivapayClient(AppJWT::createToken('token', 'secret'));

$client->getMe();
$stores = $client->listStores();
$store = current($stores->items)->fetch();
$client->getStore($store->id);
// @univapay-migrate:unsupported listBankAccounts — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
$accounts = $client->listBankAccounts();
$account = current($accounts->items)->fetch();
// @univapay-migrate:unsupported getBankAccount — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
$client->getBankAccount($account->id);
// @univapay-migrate:unsupported listTransfers — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
$transfers = $client->listTransfers();
if (sizeof($transfers->items) > 0) {
    $transfer = current($transfers->items)->fetch();
    // @univapay-migrate:unsupported getTransfer — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
    $client->getTransfer($transfer->id);
}
$charges = $client->listCharges();
$charge = current($charges->items)->fetch();
$client->getCharge($charge->storeId, $charge->id);
$refunds = $charge->listRefunds();
$subscriptions = $client->listSubscriptions();
if (sizeof($subscriptions->items) > 0) {
    $subscription = current($subscriptions->items)->fetch();
    $client->getSubscription($subscription->storeId, $subscription->id);
}
$client->listTransactions()->getNext();
