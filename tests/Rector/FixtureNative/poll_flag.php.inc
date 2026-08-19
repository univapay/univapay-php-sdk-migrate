<?php

namespace UnivapayConsumer;

use Univapay\Compat\Resources\Charge;

class PollUser
{
    public function run(Charge $charge): void
    {
        $charge->awaitResult();
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Resources\Charge;

class PollUser
{
    // @univapay-migrate:phase2-manual Charge [public-property] — Public property access on a compat resource has no native equivalent field -- UnivaPay\Models\* exposes only private properties behind getFoo()/setFoo() methods, and a native Apis\* call returns an ApiResponse whose model is reached via ->getResult(). Replace $obj->prop with $obj->getProp() (or ->getResult()->getProp() on a raw API response). See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
    public function run(Charge $charge): void
    {
        // @univapay-migrate:phase2-manual awaitResult [poll] — awaitResult() has no native equivalent method -- replace with the matching native poller on the generated Apis\* controller: pollCharge()/pollRefund()/pollCancel()/pollSubscription() (each returns an ApiResponse once the resource leaves its pending state or the attempt budget is exhausted). See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
        $charge->awaitResult();
    }
}

?>
