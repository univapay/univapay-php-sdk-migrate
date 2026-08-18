<?php

/**
 * Adapted, EXECUTABLE copy of tests/E2e/expected/examples/create_charge_and_refund.php.
 * The golden file at that path is NOT touched -- this starts from its exact content and makes
 * only the changes needed to actually run it against a live (Prism-mocked) endpoint:
 *
 *   - `AppJWT::createToken('token', 'secret')` (the doc example's own placeholder pair) has no
 *     '.' separator in its first argument and decodes to no usable claims -- replaced with
 *     e2eStoreAppToken() (real, unsigned store-scoped JWT; see _bootstrap.php).
 *   - `new UnivapayClient($storeAppToken)` -> given a UnivapayClientOptions pointed at
 *     UNIVAPAY_PRISM_URL instead of defaulting to https://api.univapay.com.
 *   - `$client->getCharge($storeAppToken->storeId, $chargeId)`: $chargeId is never assigned
 *     anywhere in the golden file at all -- a real gap in the original doc example (Rector has
 *     no way to catch "this variable is never defined"; PHP itself only warns, then passes null
 *     through to getCharge() and 400s at Prism). Replaced with the real $charge->id from the
 *     capture-flow charge created immediately above it.
 *   - e2eAssert()/e2eAssertThrows() calls added after each meaningful step, so a silent behavior
 *     regression (wrong return type, wrong hydration) fails loudly instead of merely not
 *     crashing.
 *
 * Every method call, argument shape, and inline comment from the golden file is otherwise
 * unchanged.
 */

use Univapay\Compat\Resources\Authentication\AppJWT;
use Univapay\Compat\UnivapayClient;
use Univapay\Compat\UnivapayClientOptions;
use Univapay\Compat\Resources\PaymentMethod\CardPayment;
use Univapay\Compat\Resources\PaymentData\Address;
use Univapay\Compat\Resources\PaymentData\PhoneNumber;
use Univapay\Compat\Enums\RefundReason;
use Univapay\Compat\Enums\ChargeStatus;
use Univapay\Compat\Resources\Charge;
use Univapay\Compat\Resources\Refund;
use Univapay\Compat\Resources\TransactionToken;

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
    null, // Set TokenType::RECURRING() here for recurring tokens. See TokenType for other token types.
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

$charge = $client->createToken($paymentMethod)->createCharge(Money::USD(1000))->awaitResult();
e2eAssert($charge instanceof Charge, "createToken()->createCharge()->awaitResult() returns a Charge");
// Or
$token = $client->createToken($paymentMethod);
e2eAssert($token instanceof TransactionToken && $token->id !== null, "createToken() returns a hydrated TransactionToken with an id");
// If you are using recurring tokens, you can save the token ID ($token->id) for later use
// The recurring token is unique to the customer's card, so ensure you store it in a way that
// that can be easily referenced later

// If you have saved an existing recurring token ID, replace $token->id with the ID
$charge = $client->createCharge($token->id, Money::USD(1000));
// Optionally specify the number of times to retry until a non waiting status returns.
$charge = $charge->awaitResult(3);
$status = $charge->status; // Check the status of the charge
e2eAssert($status instanceof ChargeStatus, "\$charge->status hydrates as a real ChargeStatus enum instance");

$refund = $charge
    ->createRefund(
        Money::USD(1000),
        // Please select an appropriate reason. See RefundReason.php for available options
        RefundReason::CUSTOMER_REQUEST(),
        'test',
        ['something' => null]
    )
    ->awaitResult(); // Long polls for the next status change, with a 3s timeout
e2eAssert($refund instanceof Refund, "createRefund()->awaitResult() returns a Refund");

// Use `fetch` to get the latest data from the API
$refund->fetch();

// Alternatively use `awaitResult` to poll for a non waiting status.
// Optionally specify the number of times to retry until a non waiting status returns.
$refund->awaitResult(3);

// To make an authorization charge and save the charge ID for later
$charge = $client->createCharge($token->id, Money::USD(1000), false);
e2eAssert($charge->id !== null, "authorization-only createCharge() returns a hydrated Charge with an id");

// Get the charge object from store ID and charge ID (adapted: $chargeId -> the id of the charge
// just created above -- see this file's own doc comment)
$charge = $client->getCharge($storeAppToken->storeId, $charge->id);
// Capture the charge
e2eAssert($charge->capture() === true, "capture() with no argument returns true"); // Full amount
e2eAssert($charge->capture(Money::USD(500)) === true, "capture() with a partial Money argument returns true"); // Partial amount
$charge = $charge->awaitResult(3); // Check the charge status
e2eAssert($charge instanceof Charge, "final awaitResult() returns a Charge");

echo "PASS create_charge_and_refund\n";
