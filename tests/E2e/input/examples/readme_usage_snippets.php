<?php

/**
 * Consolidated from univapay/php-sdk's README.md / README_en.md `php`-fenced code blocks. Each
 * section below is lifted near-verbatim from its corresponding README subsection and stitched
 * into one parseable file in README order; the README itself presents these as independent
 * illustrative snippets (some reference variables -- `$transactionList`, `$token` -- defined only
 * in an earlier, unseen snippet), so this file is exactly as "not really executable end to end"
 * as the README always was. Never run this file -- only Rector's static rewrite is exercised here
 * (see tests/E2e/GoldenMigrationTest.php).
 *
 * Three genuine bugs are preserved verbatim from the real README source; Rector must not "fix"
 * any of them. NOTE: namespaces below are deliberately written with `/` instead of `\` in this
 * explanatory comment ONLY (the real code further down uses real backslash-separated FQCNs, as it
 * must) -- otherwise this doc comment's own prose would itself trip bin/univapay-migrate's
 * raw-text residual-reference scanner (section (b)), which cannot distinguish an explanatory
 * mention from a genuine leftover reference.
 *
 *   1. `AppJWT::createToken(...)` (Usage section) -- the README never imports
 *      `Univapay/Resources/Authentication/AppJWT` anywhere above this call. Same class of bug as
 *      examples/fetch_data.php's bare AppJWT reference: this resolves to the *global* namespace
 *      (bare `AppJWT`), not `Univapay/Resources/Authentication/AppJWT`, and fatals at runtime.
 *      Rector must leave it untouched (no `use` statement means the resolved name is bare
 *      `AppJWT`, which matches nothing in ClassMap::SUPPORTED).
 *   2. `UnivapayResourceConflictError::class` / the closure's `UnivapayResourceConflictError $error`
 *      type hint (Request/Response Handlers section) -- likewise never imported in the real
 *      README. Another bare/global-namespace reference bug, preserved as-is.
 *   3. Three dead imports that never existed in the real univapay/php-sdk tree, copied verbatim:
 *      `Univapay/Client` (should be `Univapay/UnivapayClient`), `Univapay/RequestsHandlers/*`
 *      (should be `Univapay/Requests/Handlers/*` -- note both occurrences below, RateLimitHandler
 *      *and* BasicRetryHandler, both under the same dead namespace), and
 *      `Univapay/PaymentMethod/CardPayment` (should be `Univapay/Resources/PaymentMethod/CardPayment`).
 *      bin/univapay-migrate's post-scan section (c) must report all three, never rename them.
 *
 * A fourth thing, not a README bug but a real Rector-tooling limitation (see NOTES.md's
 * comment-deletion caveat): several of the `// --- "..." section ---` banner comments below are
 * expected to DISAPPEAR entirely from this file's golden output, whenever the immediately-following
 * `use` statement is one the rename touches. This is left as-is (not restructured to dodge it)
 * specifically BECAUSE this file's whole point is to be a faithful, section-by-section
 * reproduction of the real README -- so the golden expected/ output for this file is the clearest,
 * most realistic demonstration in this corpus of that limitation.
 */

// --- "Usage" section ---------------------------------------------------------

use Univapay\Client;
use Univapay\UnivapayClientOptions;
use Univapay\RequestsHandlers\RateLimitHandler;

$client = new UnivapayClient(AppJWT::createToken('token', 'secret'));

// For more options, create and modify the client options object before instantiating the client
// See UnivapayClientOptions for full options list
$clientOptions = new UnivapayClientOptions();
$clientOptions->rateLimitHandler = new RateLimitHandler(5, 2);
$client = new UnivapayClient(AppJWT::createToken('token', 'secret'), $clientOptions);

// See the examples folder for usage examples

// --- "Money models" section ---------------------------------------------------

use Money\Currency;
use Money\Money;
use Univapay\PaymentMethod\CardPayment;

$paymentMethod = new CardPayment('test@test.com', 'PHP example', '4242424242424242', '02', '2030', '123');
$charge = $client
    ->createToken($paymentMethod)
    ->createCharge(Money::USD(1000));

$isUsd = $charge->currency === new Currency('USD'); // true
$isThousand = $charge->requestAmount === new Money(1000, $charge->currency); // true

// --- "Enumerators" section -----------------------------------------------------

use Univapay\Enums\ChargeStatus;

$values = ChargeStatus::findValues(); // Get a list of all names and values in the enumerator
$chargeStatus = ChargeStatus::PENDING(); // Note the braces at the end
$isPending = $chargeStatus->getValue() === 'pending'; // true
$sameInstance = $chargeStatus === ChargeStatus::fromValue('pending'); // true
// Also works for switch statements
switch ($chargeStatus) {
    case ChargeStatus::PENDING():
        // Do something
        break;
    // ...
}

// --- "Updating resource models" section ----------------------------------------

$charge->fetch();

// --- "Long polling" section -----------------------------------------------------

$charge = $client
    ->createCharge($token->id, Money::USD(1000)) // $charge->status == PENDING
    ->awaitResult(); // $charge->status == SUCCESSFUL

// OR
$charge = $client
    ->createCharge($token->id, Money::USD(1000)) // $charge->status == PENDING
    ->awaitResult(5); // Retries up to 5 times (up to 15s) until a non PENDING status is retrieved

// --- "Lists and pagination" section ---------------------------------------------

use InvalidArgumentException;
use Univapay\Enums\CursorDirection;

try {
    $transactionList = $client->listTransactionsByOptions([
        'from' => date_create('-1 week'),
        'to' => date_create('+1 week'),
    ]);
} catch (InvalidArgumentException $error) {
    // When input parameters does not correspond to the correct type
}

$transactions = $transactionList->items; // Default limit per page = 10 items

if ($transactionList->hasMore) {
    $transactionList = $transactionList->getNext(); // The list does not mutate internally
    $transactions = array_merge($transactions, $transactionList->items);
}

$firstTenItems = $client->listTransactionsByOptions([
    'from' => date_create('-1 week'),
    'to' => date_create('+1 week'),
    'cursor_direction' => CursorDirection::ASC(),
]);

// --- "Request/Response Handlers" section -----------------------------------------

use Univapay\RequestsHandlers\BasicRetryHandler;

$subscriptionTokenRetryHandler = new BasicRetryHandler(
    UnivapayResourceConflictError::class, // Exception to match
    5, // Tries 5 times
    2, // At 2 seconds interval
    // More specific filtering based on the error, takes in the error as the first parameter
    // return true to retry, false to ignore.
    function (UnivapayResourceConflictError $error) { // Closure takes one parameter and must match the declared Exception class
        return $error->code === 'NON_UNIQUE_ACTIVE_TOKEN';
    }
);
$client->addHandlers($subscriptionTokenRetryHandler);

// To reset or to clear and add new handlers from scratch
// The rateLimitHandler will be automatically added from UnivapayClientOptions
$client->setHandlers($subscriptionTokenRetryHandler);
