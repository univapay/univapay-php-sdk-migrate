<?php

namespace UnivapayConsumer\Webhooks;

use Univapay\Compat\UnivapayClient;
use Univapay\Compat\Resources\WebhookPayload;
use Univapay\Compat\Errors\UnivapayInvalidWebhookData;
use Univapay\Compat\Errors\UnivapayUnknownWebhookEvent;

/**
 * E2E synthetic fixture: parseWebhookData handling wrapped in try/catch on Univapay error
 * classes -- the real examples/parse_webhook_data.php never wraps the call in a catch block at
 * all, so multi-catch + single-catch renaming of old-SDK error classes inside a
 * webhook-handling context is otherwise untested end to end.
 *
 * (Doc comment placed after the imports, not before -- see synthetic/internal_api_usage.php's
 * note on why.)
 */
class WebhookController
{
    public function handle(UnivapayClient $client, array $rawJsonBody): ?WebhookPayload
    {
        try {
            return $client->parseWebhookData($rawJsonBody);
        } catch (UnivapayInvalidWebhookData | UnivapayUnknownWebhookEvent $e) {
            error_log('webhook parse failed: ' . $e->getMessage());
            return null;
        }
    }

    public function handleSeparateCatches(UnivapayClient $client, array $rawJsonBody): ?WebhookPayload
    {
        try {
            $payload = $client->parseWebhookData($rawJsonBody);
        } catch (UnivapayUnknownWebhookEvent $e) {
            // Event type this SDK version doesn't know about yet.
            error_log('unknown webhook event: ' . $e->getMessage());
            return null;
        } catch (UnivapayInvalidWebhookData $e) {
            // Malformed payload, or a merchant-token context error swallowed into this type.
            error_log('invalid webhook data: ' . $e->getMessage());
            return null;
        }

        return $payload;
    }
}
