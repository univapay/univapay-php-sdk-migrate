<?php

namespace UnivapayConsumer;

use Univapay\Compat\Errors\UnivapayNotFoundError;

class ExceptionHandlingUser
{
    public function run(callable $fn): void
    {
        try {
            $fn();
        } catch (UnivapayNotFoundError $e) {
            // handled
        }
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Errors\UnivapayNotFoundError;

class ExceptionHandlingUser
{
    public function run(callable $fn): void
    {
        try {
            $fn();
        } // @univapay-migrate:phase2-manual UnivapayNotFoundError [exception-handling] — This compat exception class has no 1:1 native equivalent -- the native SDK throws only UnivaPay\Exceptions\ApiException (network failures or a non-OK status with no parseable body) and UnivaPay\Exceptions\ApiErrorException (a parsed {status, code, errors} body). Distinguish which error happened via $e->getHttpResponse()->getStatusCode() / $e->getCodeProperty(), not via exception class -- review every catch site by hand. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
        catch (UnivapayNotFoundError $e) {
            // handled
        }
    }
}

?>
