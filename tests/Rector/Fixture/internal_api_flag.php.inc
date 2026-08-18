<?php

namespace UnivapayConsumer;

use Univapay\Requests\HttpRequester;

class InternalApiUser
{
    public function run(HttpRequester $requester): void
    {
        $requester->request();
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Requests\HttpRequester;

class InternalApiUser
{
    // @univapay-migrate:internal-api Requests\HttpRequester — no compat replacement exists; this is a hard compile error once univapay/php-sdk is removed. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#internal-api-usage
    public function run(HttpRequester $requester): void
    {
        $requester->request();
    }
}

?>
