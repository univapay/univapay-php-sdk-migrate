<?php

/**
 * NEW synthetic execution script -- has no tests/E2e/expected/ golden counterpart at
 * all (unlike its siblings in this directory, which adapt an existing golden fixture). Written
 * directly against the real, installed univapay/univapay-sdk-compat to prove four specific
 * runtime behaviors the static-rewrite golden corpus could never prove (it only ever asserts
 * that Rector's OUTPUT is byte-identical, never that the output actually RUNS correctly):
 *
 *   1. Enum identity round-trip: a value hydrated off the wire is the SAME singleton instance
 *      (`===`, not just `==`) as the one returned by the enum's own named constructor -- proving
 *      `Enums\TypedEnum::create()`'s memoization survives a real fetch through
 *      `Support\CompatContext`/`Jsonable` schema parsing, not just in isolation.
 *   2. `Resources\Paginated::getNext()`'s `UnivapayNoMoreItemsError` branch -- every list example
 *      in the spec is a static Prism mock with `has_more: false` (verified directly against
 *      src/spec/openapi.yaml), so this is the ONLY branch of getNext() reachable at all against
 *      Prism; see that class's own doc for why (no real backing store, so `hasMore` never flips).
 *   3. Catch-clause behavior for a REAL mapped HTTP error, exercised two ways:
 *      (a) organically, through the real compat facade, no tricks: `getCharge()` with a
 *          non-UUID-shaped id fails Prism's OWN request-schema validation (`id` is declared
 *          `format: uuid`) with a real 400, mapped by `Support\ExceptionMapper` to
 *          `Errors\UnivapayRequestError` -- proving the full catch-clause plumbing end to end
 *          (ApiCaller -> ExceptionMapper -> a real `Errors\*` exception reaching this script's own
 *          catch block) via a call this compat layer's public surface can make entirely on its
 *          own, no workaround needed.
 *      (b) `Errors\UnivapayNotFoundError` (404) specifically -- forcing a 404 via a bogus id, if
 *          Prism permits it. Empirically: a well-formed bogus UUID does NOT get a 404 from
 *          Prism -- Prism's static mock serves the same 200
 *          example for ANY path-param value that matches the declared format (confirmed via a
 *          direct curl probe against a running Prism instance before writing this). Prism DOES
 *          expose a real, documented way to select a non-default response by status code -- the
 *          `Prefer: code=<status>` header (confirmed the same way: `curl -H "Prefer: code=404"`
 *          against this exact route returns a real 404 with a `{"status":"error","code":
 *          "NOT_FOUND"}` body) -- but none of the generated `Apis\*Api` controller methods compat
 *          calls through accept a per-call custom header, and `Support\Bridge`/`Support\ApiCaller`
 *          expose no such hook either (`ApiCaller::httpCallback()` only ever registers an
 *          AFTER-response hook, never a before-request one). So this can only be forced via a
 *          direct HTTP call bypassing the generated SDK entirely, then handed to
 *          `Support\ExceptionMapper::mapResponse()` (a real, non-`@internal`-enforced public
 *          static method) exactly the way `Support\ApiCaller::call()` itself does. This is NOT a
 *          full end-to-end proof through the compat facade the way (a) above is -- compat has
 *          no per-call header override at all, which would also block a real integrator from ever
 *          sending `Idempotency-Key`... no, that one IS handled -- but e.g. a custom trace header
 *          would hit the same wall. It DOES prove, for real, against a real Prism 404 response,
 *          that the ExceptionMapper fix (previously dead code) produces the right `Errors\*`
 *          class for the status-code mapping.
 *   4. UnivapayUnsupportedFeatureError -- already the dedicated subject of the adapted
 *      fetch_data.php in this same directory; not duplicated here beyond a second, independent
 *      call site (`listTransfersByOptions()`) for a bit of extra breadth at negligible cost.
 */

use Univapay\Compat\UnivapayClient;
use Univapay\Compat\UnivapayClientOptions;
use Univapay\Compat\Enums\ChargeStatus;
use Univapay\Compat\Errors\UnivapayNoMoreItemsError;
use Univapay\Compat\Errors\UnivapayNotFoundError;
use Univapay\Compat\Errors\UnivapayRequestError;
use Univapay\Compat\Errors\UnivapayUnsupportedFeatureError;
use Univapay\Compat\Resources\Charge;
use Univapay\Compat\Resources\Paginated;
use Univapay\Compat\Support\ExceptionMapper;

require_once('vendor/autoload.php');
require __DIR__ . '/_bootstrap.php';

$prismUrl = e2ePrismUrl();
$client = new UnivapayClient(e2eStoreAppToken(), new UnivapayClientOptions($prismUrl));

// --- 1. Enum identity round-trip -------------------------------------------------------------

$charge = $client->getCharge(E2E_STORE_ID, E2E_CHARGE_ID); // any spec-shaped uuid; Prism ignores the value
e2eAssert($charge instanceof Charge, "getCharge() returns a real Charge (enum round-trip fixture)");
e2eAssert($charge->status instanceof ChargeStatus, "\$charge->status hydrates as a ChargeStatus");
e2eAssert(
    $charge->status === ChargeStatus::SUCCESSFUL(),
    "hydrated \$charge->status is the IDENTICAL (===) ChargeStatus::SUCCESSFUL() singleton, not merely == equal " .
        "(spec's ChargeGetSuccessExample pins status: \"successful\")"
);
e2eAssert(
    $charge->status === ChargeStatus::fromValue($charge->status->getValue()),
    "ChargeStatus::fromValue() round-trips back to the SAME singleton instance TypedEnum::create() memoized"
);

// --- 2. Paginated::getNext() NoMoreItems branch ----------------------------------------------

$charges = $client->listCharges();
e2eAssert($charges instanceof Paginated, "listCharges() returns a Paginated page");
e2eAssert(
    $charges->hasMore === false,
    "the initial page's hasMore is false (every list example in the spec is a static Prism mock -- see this file's own doc comment)"
);
e2eAssertThrows(
    function () use ($charges) {
        $charges->getNext();
    },
    UnivapayNoMoreItemsError::class,
    "Paginated::getNext() throws UnivapayNoMoreItemsError when hasMore is false"
);

// --- 3(a). Organic 400 through the real compat facade, no tricks -----------------------------

e2eAssertThrows(
    function () use ($client) {
        $client->getCharge(E2E_STORE_ID, 'not-a-valid-uuid');
    },
    UnivapayRequestError::class,
    "getCharge() with a non-uuid-shaped id fails Prism's own request-schema validation (400), " .
        "mapped end-to-end through the real compat facade to UnivapayRequestError"
);

// --- 3(b). Forced 404 (Prefer: code=404) fed directly through ExceptionMapper ----------------
// See this file's own doc comment, point 3(b), for exactly why this bypasses the generated
// controller instead of going through $client directly.

$chargeUrl = rtrim($prismUrl, '/') . '/stores/' . E2E_STORE_ID . '/charges/' . E2E_CHARGE_ID;
$ch = curl_init($chargeUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer test-secret.test-jwt',
    'Prefer: code=404',
]);
$rawBody = curl_exec($ch);
e2eAssert($rawBody !== false, "raw curl GET against Prism with Prefer: code=404 succeeds at the transport level");
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

e2eAssert($statusCode === 404, "Prism honors Prefer: code=404 for GET /stores/{storeId}/charges/{id} (got HTTP $statusCode)");

$decodedBody = json_decode((string) $rawBody, true);
$mappedError = ExceptionMapper::mapResponse($statusCode, $decodedBody, $chargeUrl);
e2eAssert(
    $mappedError instanceof UnivapayNotFoundError,
    "Support\\ExceptionMapper::mapResponse(404, ...) against a REAL Prism 404 response maps to UnivapayNotFoundError " .
        "(ExceptionMapper-being-dead-code regression guard)"
);

// --- 4. UnivapayUnsupportedFeatureError, second call site ------------------------------------

e2eAssertThrows(
    function () use ($client) {
        $client->listTransfersByOptions(['limit' => 5]);
    },
    UnivapayUnsupportedFeatureError::class,
    "listTransfersByOptions() also throws UnivapayUnsupportedFeatureError at runtime"
);

echo "PASS synthetic_execution_checks\n";
