<?php

namespace UnivapayConsumer;

use Univapay\UnivapayClient;

class VerifyUnsupportedUser
{
    public function run(UnivapayClient $client, $somethingUntyped): void
    {
        $somethingUntyped->listTransfers();
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\UnivapayClient;

class VerifyUnsupportedUser
{
    public function run(UnivapayClient $client, $somethingUntyped): void
    {
        // @univapay-migrate:unsupported listTransfers (verify) — could not statically confirm the receiver type; if this is a Univapay object, it throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
        $somethingUntyped->listTransfers();
    }
}

?>
