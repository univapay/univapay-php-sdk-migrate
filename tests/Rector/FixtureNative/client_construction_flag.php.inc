<?php

namespace UnivapayConsumer;

use Univapay\Compat\Resources\Authentication\AppJWT;
use Univapay\Compat\UnivapayClient;

class ClientConstructionUser
{
    public function run(): UnivapayClient
    {
        $token = AppJWT::createToken('token', 'secret');
        return new UnivapayClient($token);
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Resources\Authentication\AppJWT;
use Univapay\Compat\UnivapayClient;

class ClientConstructionUser
{
    // @univapay-migrate:phase2-manual UnivapayClient [client-construction] — UnivapayClient/UnivapayClientOptions construction, AppJWT/StoreAppJWT/MerchantAppJWT token building, and the Requests\Handlers\* retry/rate-limit handler classes have no native equivalent -- replace client/token construction with UnivaPay\UnivapayClientSdkClientBuilder::init()->...->build() plus UnivaPay\Authentication\BearerAuthCredentialsBuilder::init($secretKey, $jwtToken), and replace custom retry-handler wiring with the builder's own enableRetries()/numberOfRetries() configuration. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
    public function run(): UnivapayClient
    {
        // @univapay-migrate:phase2-manual AppJWT [client-construction] — UnivapayClient/UnivapayClientOptions construction, AppJWT/StoreAppJWT/MerchantAppJWT token building, and the Requests\Handlers\* retry/rate-limit handler classes have no native equivalent -- replace client/token construction with UnivaPay\UnivapayClientSdkClientBuilder::init()->...->build() plus UnivaPay\Authentication\BearerAuthCredentialsBuilder::init($secretKey, $jwtToken), and replace custom retry-handler wiring with the builder's own enableRetries()/numberOfRetries() configuration. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
        $token = AppJWT::createToken('token', 'secret');
        // @univapay-migrate:phase2-manual UnivapayClient [client-construction] — UnivapayClient/UnivapayClientOptions construction, AppJWT/StoreAppJWT/MerchantAppJWT token building, and the Requests\Handlers\* retry/rate-limit handler classes have no native equivalent -- replace client/token construction with UnivaPay\UnivapayClientSdkClientBuilder::init()->...->build() plus UnivaPay\Authentication\BearerAuthCredentialsBuilder::init($secretKey, $jwtToken), and replace custom retry-handler wiring with the builder's own enableRetries()/numberOfRetries() configuration. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
        return new UnivapayClient($token);
    }
}

?>
