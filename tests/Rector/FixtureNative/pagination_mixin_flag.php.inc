<?php

namespace UnivapayConsumer;

use Univapay\Compat\Resources\Mixins\GetCharges;

class MixinUser
{
    use GetCharges;
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Resources\Mixins\GetCharges;

class MixinUser
{
    // @univapay-migrate:phase2-manual GetCharges [pagination] — Paginated/getNext()/getPrevious() (and the Mixins\Get* traits providing them) have no native equivalent -- native list endpoints take a cursor query parameter directly; replace with a loop that re-calls the same Apis\* list method passing the previous page's last cursor value. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
    use GetCharges;
}

?>
