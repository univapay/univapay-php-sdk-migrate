<?php

namespace UnivapayConsumer;

use Univapay\Compat\Resources\Paginated;

class PaginationUser
{
    public function run(Paginated $list): void
    {
        while ($list->getNext() !== null) {
            $list = $list->getNext();
        }
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Resources\Paginated;

class PaginationUser
{
    // @univapay-migrate:phase2-manual Paginated [pagination] — Paginated/getNext()/getPrevious() (and the Mixins\Get* traits providing them) have no native equivalent -- native list endpoints take a cursor query parameter directly; replace with a loop that re-calls the same Apis\* list method passing the previous page's last cursor value. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
    public function run(Paginated $list): void
    {
        // @univapay-migrate:phase2-manual getNext [pagination] (verify) — could not statically confirm the receiver type; if this is a compat object: Paginated/getNext()/getPrevious() (and the Mixins\Get* traits providing them) have no native equivalent -- native list endpoints take a cursor query parameter directly; replace with a loop that re-calls the same Apis\* list method passing the previous page's last cursor value. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
        while ($list->getNext() !== null) {
            // @univapay-migrate:phase2-manual getNext [pagination] (verify) — could not statically confirm the receiver type; if this is a compat object: Paginated/getNext()/getPrevious() (and the Mixins\Get* traits providing them) have no native equivalent -- native list endpoints take a cursor query parameter directly; replace with a loop that re-calls the same Apis\* list method passing the previous page's last cursor value. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
            $list = $list->getNext();
        }
    }
}

?>
