<?php

/**
 * Adapted, EXECUTABLE copy of tests/E2e/expected/examples/create_subscription.php. The
 * golden file at that path is NOT touched. Same two adaptations as create_charge_and_refund.php
 * (see its own doc comment for the full rationale):
 *
 *   - AppJWT::createToken('token', 'secret') -> e2eStoreAppToken() (real store-scoped JWT).
 *   - `new UnivapayClient($storeAppToken)` -> given a UnivapayClientOptions pointed at
 *     UNIVAPAY_PRISM_URL.
 *
 * Every method call, argument shape, and inline comment from the golden file is otherwise
 * unchanged. Also exercises Subscription's own enum-identity round-trip (SubscriptionStatus) as
 * a bonus, since this is the only one of the executable scripts that creates a real subscription.
 */

use Univapay\Compat\Resources\Authentication\AppJWT;
use Univapay\Compat\UnivapayClient;
use Univapay\Compat\UnivapayClientOptions;
use Univapay\Compat\Resources\PaymentMethod\CardPayment;
use Univapay\Compat\Enums\TokenType;
use Univapay\Compat\Resources\PaymentData\Address;
use Univapay\Compat\Resources\PaymentData\PhoneNumber;
use Univapay\Compat\Enums\Period;
use Univapay\Compat\Resources\Subscription\ScheduleSettings;
use Univapay\Compat\Resources\Subscription\SubscriptionPlan;
use Univapay\Compat\Enums\SubscriptionPlanType;
use Univapay\Compat\Enums\SubscriptionStatus;
use Univapay\Compat\Resources\Subscription;

require_once('vendor/autoload.php');
require __DIR__ . '/_bootstrap.php';
use Money\Money;

$storeAppToken = e2eStoreAppToken();
$client = new UnivapayClient($storeAppToken, new UnivapayClientOptions(e2ePrismUrl()));
$paymentMethod = new CardPayment(
    'test@test.com',
    'PHP example',
    '4242424242424242',
    '02',
    '2030',
    '123',
    TokenType::SUBSCRIPTION(), // Set TokenType::RECURRING() here for recurring tokens. See TokenType for other token types.
    null,
    new Address(
        'test line 1',
        'test line 2',
        'tokyo',
        'test city',
        'jp',
        '101-1111'
    ),
    new PhoneNumber(PhoneNumber::JP, '12910298309128')
);

$charge = $client->createToken($paymentMethod)->createSubscription(
    Money::JPY(20000),
    Period::QUARTERLY(),
    Money::JPY(15000),
    new ScheduleSettings(
        date_create('+1 month') // Date to start the subscription after initial charge
    ),
    new SubscriptionPlan(
        SubscriptionPlanType::FIXED_CYCLES(),
        21 // The number of cycles including the first cycle of initial amount
    )
)->awaitResult(5);

e2eAssert($charge instanceof Subscription, "createToken()->createSubscription()->awaitResult() returns a Subscription");
e2eAssert($charge->status instanceof SubscriptionStatus, "\$subscription->status hydrates as a real SubscriptionStatus enum instance");
e2eAssert($charge->status === SubscriptionStatus::fromValue($charge->status->getValue()), "SubscriptionStatus enum identity round-trips (same singleton instance for the same wire value)");

echo "PASS create_subscription\n";
