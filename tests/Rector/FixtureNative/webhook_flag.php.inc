<?php

namespace UnivapayConsumer;

use Univapay\Compat\UnivapayClient;

class WebhookUser
{
    public function run(UnivapayClient $client, string $body): void
    {
        $client->parseWebhookData($body, 'secret');
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\UnivapayClient;

class WebhookUser
{
    // @univapay-migrate:phase2-manual UnivapayClient [client-construction] — UnivapayClient/UnivapayClientOptions construction, AppJWT/StoreAppJWT/MerchantAppJWT token building, and the Requests\Handlers\* retry/rate-limit handler classes have no native equivalent -- replace client/token construction with UnivaPay\UnivapayClientSdkClientBuilder::init()->...->build() plus UnivaPay\Authentication\BearerAuthCredentialsBuilder::init($secretKey, $jwtToken), and replace custom retry-handler wiring with the builder's own enableRetries()/numberOfRetries() configuration. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
    public function run(UnivapayClient $client, string $body): void
    {
        // @univapay-migrate:phase2-manual parseWebhookData [webhook] — parseWebhookData() has no native equivalent method -- replace with the matching native UnivaPay\Events\Webhooks\*Handler class (ChargeHandler, RefundHandler, CancelHandler, SubscriptionHandler, TokenHandler, BankTransferHandler, CustomsHandler), which parses and validates the payload directly instead of returning an old-SDK-shaped WebhookPayload. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
        $client->parseWebhookData($body, 'secret');
    }
}

?>
