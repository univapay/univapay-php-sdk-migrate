<?php

/**
 * Adapted, EXECUTABLE copy of tests/E2e/expected/examples/parse_webhook_data.php. The
 * golden file at that path is NOT touched -- it is a real, uncaught gap in the original doc
 * example: `$client` is referenced on the last line but never assigned ANYWHERE in that file (not
 * a migration-tool omission -- Rector has no way to detect "this variable is never defined", and
 * the golden fixture corpus deliberately preserves the original example verbatim, bug and all --
 * see GoldenMigrationTest's own doc comment on "residual" fixtures).
 *
 * This adaptation only adds the missing `$client = new UnivapayClient(...)` line (using a
 * merchant-level token: `parseWebhookData()`'s CHARGE_FINISHED branch does not route through
 * `Support\Bridge::requireStoreId()` -- see `UnivapayClient::parseWebhookData()`'s own class doc,
 * "Corner-case semantics" -- so a merchant token is sufficient and deliberately used here to also
 * exercise that asymmetry). No Prism/network call is made at all: `parseWebhookData()` only
 * hydrates the given array offline.
 *
 * ONE MORE adaptation, found only by actually trying to execute this file (not visible from a
 * static read): the golden/input heredoc ends `}"` immediately before `EOD;` -- a stray trailing
 * double-quote after the JSON's closing brace. That is not something this migration corpus
 * introduced: it is byte-for-byte present in the REAL upstream `univapay/php-sdk` repository's
 * own `examples/parse_webhook_data.php` (verified directly against a fresh clone of
 * github.com/univapay/univapay-php-sdk). `json_decode()` on that string returns `null` (trailing
 * non-whitespace after a complete JSON value is a syntax error, confirmed empirically), so the
 * real upstream example -- executed as-is, under the OLD SDK too, not just this compat layer --
 * would pass `null` to `parseWebhookData(array $data)` and fatal with a `TypeError`. Worth
 * flagging to a human: a real, pre-existing bug in the upstream doc example, independent of this
 * migration project. Fixed here (trailing quote removed) so this script can actually execute;
 * tests/E2e/expected/examples/parse_webhook_data.php (the golden fixture) is deliberately left
 * with the bug intact, matching the golden corpus's "preserve verbatim" policy for the rest of
 * the corpus.
 */

use Univapay\Compat\UnivapayClient;
use Univapay\Compat\Resources\WebhookPayload;
use Univapay\Compat\Enums\WebhookEvent;
use Univapay\Compat\Resources\Charge;

require_once('vendor/autoload.php');
require __DIR__ . '/_bootstrap.php';

$client = new UnivapayClient(e2eMerchantAppToken());

$data = <<<EOD
{
   "event":"charge_finished",
   "data":{
      "id":"11e756f4-ed34-6152-970d-77c75a0f7890",
      "store_id":"11e746a0-f4f1-dc3a-a472-831414c04dce",
      "transaction_token_id":"11e756f4-e9dc-2c56-970b-2f1c78640cc7",
      "transaction_token_type":"one_time",
      "requested_amount":100,
      "requested_currency":"JPY",
      "requested_amount_formatted":100,
      "charged_amount":100,
      "charged_currency":"JPY",
      "charged_amount_formatted":100,
      "status":"successful",
      "error":null,
      "metadata":{
         "orderId":123456,
         "someString":"abcdefg"
      },
      "mode":"test",
      "created_on":"2017-06-22T02:46:00.972639Z"
   }
}
EOD;
$payload = $client->parseWebhookData(json_decode($data, true));

e2eAssert($payload instanceof WebhookPayload, "parseWebhookData() returns a WebhookPayload");
e2eAssert($payload->event === WebhookEvent::CHARGE_FINISHED(), "WebhookPayload::\$event is the identical CHARGE_FINISHED() enum singleton");
e2eAssert($payload->data instanceof Charge, "charge_finished webhook data hydrates as a real Charge");
e2eAssert($payload->data->id === '11e756f4-ed34-6152-970d-77c75a0f7890', "hydrated Charge carries the webhook payload's own id, not a Prism example (this call never touches the network)");

echo "PASS parse_webhook_data\n";
