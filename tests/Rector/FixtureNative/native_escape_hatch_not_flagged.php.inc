<?php

namespace UnivapayConsumer;

use Univapay\Compat\UnivapayClient;

class NativeEscapeHatchUser
{
    public function run(UnivapayClient $client)
    {
        return $client->native();
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\UnivapayClient;

class NativeEscapeHatchUser
{
    // @univapay-migrate:phase2-manual UnivapayClient [client-construction] — UnivapayClient/UnivapayClientOptions construction and AppJWT/StoreAppJWT/MerchantAppJWT token building have no native equivalent -- replace with UnivaPay\UnivapayClientSdkClientBuilder::init()->...->build() plus UnivaPay\Authentication\BearerAuthCredentialsBuilder::init($secretKey, $jwtToken). See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
    public function run(UnivapayClient $client)
    {
        return $client->native();
    }
}

?>
