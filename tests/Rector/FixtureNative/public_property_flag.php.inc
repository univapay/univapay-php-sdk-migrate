<?php

namespace UnivapayConsumer;

use Univapay\Compat\Resources\Charge;

class PublicPropertyUser
{
    public function run(Charge $charge): string
    {
        return $charge->status;
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Resources\Charge;

class PublicPropertyUser
{
    // @univapay-migrate:phase2-manual Charge [public-property] — Public property access on a compat resource has no native equivalent field -- UnivaPay\Models\* exposes only private properties behind getFoo()/setFoo() methods, and a native Apis\* call returns an ApiResponse whose model is reached via ->getResult(). Replace $obj->prop with $obj->getProp() (or ->getResult()->getProp() on a raw API response). See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
    public function run(Charge $charge): string
    {
        // @univapay-migrate:phase2-manual status [public-property] — Public property access on a compat resource has no native equivalent field -- UnivaPay\Models\* exposes only private properties behind getFoo()/setFoo() methods, and a native Apis\* call returns an ApiResponse whose model is reached via ->getResult(). Replace $obj->prop with $obj->getProp() (or ->getResult()->getProp() on a raw API response). See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
        return $charge->status;
    }
}

?>
