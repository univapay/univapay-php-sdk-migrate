<?php

/**
 * Shared helpers for tests/E2e/executable/*.php (cross-repo E2E execution).
 *
 * This directory is NOT part of the golden-migrated fixture corpus (tests/E2e/expected/'s
 * byte-for-byte golden output) -- it holds hand-adapted, EXECUTABLE copies of a subset of
 * that corpus, built to actually run against a live (Prism-mocked) endpoint with a real
 * univapay/univapay-sdk-compat installed. See each sibling script's own doc comment for exactly
 * what was changed relative to its tests/E2e/expected/ counterpart; nothing under
 * tests/E2e/expected/ or tests/E2e/input/ is touched here.
 *
 * Required by every script in this directory via `require __DIR__ . '/_bootstrap.php';`, placed
 * AFTER that script's own `require_once('vendor/autoload.php');` line (preserved verbatim from
 * the golden file) so the compat classes referenced by the functions below are autoloadable
 * by the time any of them are actually CALLED. `use` statements earlier in each file do not
 * require the referenced classes to exist yet -- only calling them does -- so this ordering is
 * safe.
 *
 * Fixture ids are copied verbatim from the docs repo's own tests/helpers.js `IDS` map / the
 * compat repo's tests/Integration/IntegrationTestCase.php -- the same values Prism's own spec
 * examples were authored against (see that class's own doc comment for why: several path params
 * are declared `format: uuid` and Prism validates that against the schema even though it never
 * inspects the value itself).
 */

declare(strict_types=1);

const E2E_STORE_ID = '11edf541-c42d-653c-8c3d-dfe0a55f95c0';
const E2E_MERCHANT_ID = '01234567-89ab-cdef-0123-456789abcdef';
const E2E_CHARGE_ID = '11ef0000-0000-4000-8000-000000000001';
const E2E_TOKEN_ID = '11ef32a7-3a71-8662-803f-1bc27702eeec';

/**
 * Resolves the Prism endpoint every executable script in this directory points its
 * UnivapayClientOptions at. Set by scripts/e2e-execution.sh (either directly, or via the
 * `prism`/`e2e-prism` container hostname on the shared Docker network) -- never defaults to the
 * real https://api.univapay.com, unlike UnivapayClientOptions's own constructor default.
 */
function e2ePrismUrl(): string
{
    $url = getenv('UNIVAPAY_PRISM_URL');
    if ($url === false || trim($url) === '') {
        fwrite(STDERR, "UNIVAPAY_PRISM_URL is not set -- this script must run under scripts/e2e-execution.sh.\n");
        exit(1);
    }
    return $url;
}

/**
 * Same technique as the compat repo's own tests/Support/FakeJwtBuilder.php: `AppJWT::
 * createToken()` never verifies its input's signature (matching the old SDK exactly), so a
 * hand-built, unsigned three-segment JWT-shaped string is sufficient -- no real signing key
 * needed to exercise a real store_id/merchant_id claim pair end-to-end.
 */
function e2eBuildJwtString(array $payload): string
{
    $header = base64_encode(json_encode(['alg' => 'none']));
    $body = base64_encode(json_encode($payload));
    return "$header.$body.sig";
}

/**
 * @return \Univapay\Compat\Resources\Authentication\AppJWT A real StoreAppJWT (store_id claim
 *         present) -- required by every store-scoped call (createToken/getCheckoutInfo/
 *         getTransactionToken/simulation/...). Replaces the golden fixture's own
 *         `AppJWT::createToken('token', 'secret')`, which decodes to no usable claims at all
 *         (the first argument has no '.' separator, so `explode('.', $appToken)[1]` -- see
 *         AppJWT::createToken() -- has no index 1 to decode).
 */
function e2eStoreAppToken(string $secret = 'test-secret'): \Univapay\Compat\Resources\Authentication\AppJWT
{
    return \Univapay\Compat\Resources\Authentication\AppJWT::createToken(
        e2eBuildJwtString([
            'sub' => 'app_token',
            'iat' => 1700000000,
            'merchant_id' => E2E_MERCHANT_ID,
            'store_id' => E2E_STORE_ID,
            'domains' => ['example.com'],
            'mode' => 'test',
            'creator_id' => 'test',
            'version' => 1,
            'jti' => 'e2e-store-jti',
        ]),
        $secret
    );
}

/**
 * @return \Univapay\Compat\Resources\Authentication\AppJWT A real MerchantAppJWT (no store_id
 *         claim) -- for merchant-wide calls (getMe/listStores/listCharges/listSubscriptions/
 *         listTransactions/listBankAccounts/listTransfers/getTransfer -- none of these route
 *         through Support\Bridge::requireStoreId(), see UnivapayClient's own class doc "Mixins").
 */
function e2eMerchantAppToken(string $secret = 'test-secret'): \Univapay\Compat\Resources\Authentication\AppJWT
{
    return \Univapay\Compat\Resources\Authentication\AppJWT::createToken(
        e2eBuildJwtString([
            'sub' => 'app_token',
            'iat' => 1700000000,
            'merchant_id' => E2E_MERCHANT_ID,
            'creator_id' => 'test',
            'version' => 1,
            'jti' => 'e2e-merchant-jti',
        ]),
        $secret
    );
}

/**
 * Fails LOUDLY (throws, killing the script with a non-zero exit) rather than accumulating
 * failures -- tests/E2e/execution-runner.php (which invokes each of these scripts as its own
 * subprocess) treats "non-zero exit" as the one signal that determines pass/fail, so every
 * meaningful runtime assertion in this directory funnels through this one function.
 */
function e2eAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("ASSERTION FAILED: $message");
    }
    echo "OK: $message\n";
}

/**
 * Asserts that calling $fn throws an instance of $expectedClass, and returns the caught
 * exception (so callers can assert further on its message/properties). Fails loudly (see
 * e2eAssert) both when $fn does NOT throw at all, and when it throws something else.
 *
 * @template T of Throwable
 * @param class-string<T> $expectedClass
 * @return T
 */
function e2eAssertThrows(callable $fn, string $expectedClass, string $message): Throwable
{
    try {
        $fn();
    } catch (Throwable $caught) {
        if ($caught instanceof $expectedClass) {
            echo "OK: $message (threw " . get_class($caught) . ": {$caught->getMessage()})\n";
            return $caught;
        }
        throw new RuntimeException(
            "ASSERTION FAILED: $message -- expected $expectedClass, got " . get_class($caught)
            . ' (' . $caught->getMessage() . ')',
            0,
            $caught
        );
    }
    throw new RuntimeException("ASSERTION FAILED: $message -- expected $expectedClass, nothing was thrown");
}
