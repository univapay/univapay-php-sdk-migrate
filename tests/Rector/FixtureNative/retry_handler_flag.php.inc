<?php

namespace UnivapayConsumer;

use Univapay\Compat\Requests\Handlers\BasicRetryHandler;

class RetryHandlerUser
{
    public function run(): BasicRetryHandler
    {
        return new BasicRetryHandler();
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Requests\Handlers\BasicRetryHandler;

class RetryHandlerUser
{
    // @univapay-migrate:phase2-manual BasicRetryHandler [client-construction] — UnivapayClient/UnivapayClientOptions construction, AppJWT/StoreAppJWT/MerchantAppJWT token building, and the Requests\Handlers\* retry/rate-limit handler classes have no native equivalent -- replace client/token construction with UnivaPay\UnivapayClientSdkClientBuilder::init()->...->build() plus UnivaPay\Authentication\BearerAuthCredentialsBuilder::init($secretKey, $jwtToken), and replace custom retry-handler wiring with the builder's own enableRetries()/numberOfRetries() configuration. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
    public function run(): BasicRetryHandler
    {
        // @univapay-migrate:phase2-manual BasicRetryHandler [client-construction] — UnivapayClient/UnivapayClientOptions construction, AppJWT/StoreAppJWT/MerchantAppJWT token building, and the Requests\Handlers\* retry/rate-limit handler classes have no native equivalent -- replace client/token construction with UnivaPay\UnivapayClientSdkClientBuilder::init()->...->build() plus UnivaPay\Authentication\BearerAuthCredentialsBuilder::init($secretKey, $jwtToken), and replace custom retry-handler wiring with the builder's own enableRetries()/numberOfRetries() configuration. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
        return new BasicRetryHandler();
    }
}

?>
