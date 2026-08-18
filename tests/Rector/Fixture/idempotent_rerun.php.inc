<?php

namespace UnivapayConsumer;

use Univapay\Compat\UnivapayClient;
use Univapay\Compat\Resources\Transfer;

class AlreadyMigratedUser
{
    // @univapay-migrate:unsupported Transfer — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
    public function run(UnivapayClient $client, Transfer $transfer): void
    {
        // @univapay-migrate:unsupported listLedgers — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
        $transfer->listLedgers();
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\UnivapayClient;
use Univapay\Compat\Resources\Transfer;

class AlreadyMigratedUser
{
    // @univapay-migrate:unsupported Transfer — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
    public function run(UnivapayClient $client, Transfer $transfer): void
    {
        // @univapay-migrate:unsupported listLedgers — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
        $transfer->listLedgers();
    }
}

?>
