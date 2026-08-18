<?php

namespace UnivapayConsumer\Internals;

use Univapay\Requests\HttpRequester;
use Univapay\Requests\RequestContext;
use Univapay\Requests\Requester;

/**
 * E2E synthetic fixture: a consumer reaching into the old SDK's purely-internal transport layer
 * (`Requester`/`HttpRequester`/`RequestContext`, under the SDK's internal Requests namespace) --
 * none of the real examples/ or README snippets do this (it's not part of the SDK's intended
 * public surface), but it is plausible for a consumer who built custom request/response
 * instrumentation on top of the SDK's internals. These three classes have no compat replacement
 * at all -- a hard compile error once univapay/php-sdk is removed, distinct in severity from an
 * unsupported *feature* flag (which still compiles and only changes runtime behavior).
 *
 * NOTE ON PLACEMENT: this doc comment deliberately sits AFTER the `use` imports, not before them
 * (unlike most other fixtures in this corpus, which put an explanatory header first). Rector's
 * `NameImportingPostRector` (active because `importNames(true)` -- see
 * config/rector-template.php's own doc block for why that setting is required) rebuilds a file's
 * entire `use`-import block from scratch whenever ANY import in it needs renaming, and that
 * rebuild silently DELETES (not just relocates) whatever comment was attached as the leading
 * comment of the use-statement it replaces (MarkerCommentTrait works around this for THIS
 * package's own generated markers; there is no equivalent guard for arbitrary pre-existing
 * developer comments, because Rector's own postprocessor is what deletes them, not this package's
 * code). See NOTES.md's comment-deletion caveat for the full writeup and the other fixtures in
 * this corpus that hit it.
 */
// @univapay-migrate:internal-api Requests\Requester — no compat replacement exists; this is a hard compile error once univapay/php-sdk is removed. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#internal-api-usage
class CustomInstrumentedRequester implements Requester
{
    /** @var HttpRequester */
    private $delegate;

    // @univapay-migrate:internal-api Requests\HttpRequester — no compat replacement exists; this is a hard compile error once univapay/php-sdk is removed. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#internal-api-usage
    public function __construct(HttpRequester $delegate)
    {
        $this->delegate = $delegate;
    }

    // @univapay-migrate:internal-api Requests\RequestContext — no compat replacement exists; this is a hard compile error once univapay/php-sdk is removed. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#internal-api-usage
    public function request(RequestContext $context)
    {
        $start = microtime(true);
        $result = $this->delegate->request($context);
        error_log(sprintf('request took %.3fs', microtime(true) - $start));

        return $result;
    }
}
