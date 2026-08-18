<?php

namespace UnivapayConsumer;

use Univapay\Utility\HttpUtils;
use Univapay\Utility\RequesterUtils;

class UtilityTransportUser
{
    public function checkResponse($response)
    {
        return HttpUtils::checkResponse($response);
    }

    public function fetch($context)
    {
        return RequesterUtils::executeGet(null, $context);
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Utility\HttpUtils;
use Univapay\Utility\RequesterUtils;

class UtilityTransportUser
{
    public function checkResponse($response)
    {
        // @univapay-migrate:internal-api Utility\HttpUtils — no compat replacement exists; this is a hard compile error once univapay/php-sdk is removed. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#internal-api-usage
        return HttpUtils::checkResponse($response);
    }

    public function fetch($context)
    {
        // @univapay-migrate:internal-api Utility\RequesterUtils — no compat replacement exists; this is a hard compile error once univapay/php-sdk is removed. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#internal-api-usage
        return RequesterUtils::executeGet(null, $context);
    }
}

?>
