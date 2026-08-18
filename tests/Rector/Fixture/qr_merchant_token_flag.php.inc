<?php

namespace UnivapayConsumer;

use Univapay\Resources\Charge;

class QrMerchantTokenUser
{
    public function run(Charge $charge): void
    {
        $charge->qrMerchantToken();
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Resources\Charge;

class QrMerchantTokenUser
{
    public function run(Charge $charge): void
    {
        // @univapay-migrate:unsupported qrMerchantToken — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
        $charge->qrMerchantToken();
    }
}

?>
