<?php

namespace UnivapayConsumer;

use Univapay\Compat\UnivapayClient;

class VerifyOnlyUser
{
    /**
     * @param mixed $anything untyped -- receiver type cannot be statically resolved.
     */
    public function run(UnivapayClient $client, $anything): void
    {
        $anything->awaitResult();
        echo $anything->status;
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\UnivapayClient;

class VerifyOnlyUser
{
    /**
     * @param mixed $anything untyped -- receiver type cannot be statically resolved.
     */
    // @univapay-migrate:phase2-manual UnivapayClient [client-construction] — UnivapayClient/UnivapayClientOptions construction, AppJWT/StoreAppJWT/MerchantAppJWT token building, and the Requests\Handlers\* retry/rate-limit handler classes have no native equivalent -- replace client/token construction with UnivaPay\UnivapayClientSdkClientBuilder::init()->...->build() plus UnivaPay\Authentication\BearerAuthCredentialsBuilder::init($secretKey, $jwtToken), and replace custom retry-handler wiring with the builder's own enableRetries()/numberOfRetries() configuration. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
    public function run(UnivapayClient $client, $anything): void
    {
        // @univapay-migrate:phase2-manual awaitResult [poll] (verify) — could not statically confirm the receiver type; if this is a compat object: awaitResult() has no native equivalent method -- replace with the matching native poller on the generated Apis\* controller: pollCharge()/pollRefund()/pollCancel()/pollSubscription() (each returns an ApiResponse once the resource leaves its pending state or the attempt budget is exhausted). See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
        $anything->awaitResult();
        // @univapay-migrate:phase2-manual status [public-property] (verify) — could not statically confirm the receiver type; if this is a compat object: Public property access on a compat resource has no native equivalent field -- UnivaPay\Models\* exposes only private properties behind getFoo()/setFoo() methods, and a native Apis\* call returns an ApiResponse whose model is reached via ->getResult(). Replace $obj->prop with $obj->getProp() (or ->getResult()->getProp() on a raw API response). See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
        echo $anything->status;
    }
}

?>
