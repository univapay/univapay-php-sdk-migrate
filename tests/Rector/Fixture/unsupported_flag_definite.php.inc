<?php

namespace UnivapayConsumer;

use Univapay\Resources\Transfer;

class DefiniteUnsupportedUser
{
    public function run(Transfer $transfer): void
    {
        $transfer->listLedgers();
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Resources\Transfer;

class DefiniteUnsupportedUser
{
    // @univapay-migrate:unsupported Transfer — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
    public function run(Transfer $transfer): void
    {
        // @univapay-migrate:unsupported listLedgers — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
        $transfer->listLedgers();
    }
}

?>
