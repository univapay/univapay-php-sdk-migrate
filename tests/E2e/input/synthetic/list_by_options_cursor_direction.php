<?php

namespace UnivapayConsumer\Listing;

use Univapay\Enums\CursorDirection;
use Univapay\Resources\Paginated;
use Univapay\UnivapayClient;

/**
 * E2E synthetic fixture: listXByOptions array calls with CursorDirection -- the real examples/
 * tree never uses the *ByOptions family at all (only README's "Lists and pagination" snippet
 * does, for listTransactionsByOptions specifically). Broadens coverage to listChargesByOptions
 * and listSubscriptionsByOptions receivers too.
 *
 * (Doc comment placed after the imports, not before -- see synthetic/internal_api_usage.php's
 * note on why.)
 */
class ListingUser
{
    public function listCharges(UnivapayClient $client): Paginated
    {
        return $client->listChargesByOptions([
            'from' => date_create('-1 month'),
            'to' => date_create('now'),
            'cursor_direction' => CursorDirection::DESC(),
        ]);
    }

    public function listSubscriptions(UnivapayClient $client): Paginated
    {
        return $client->listSubscriptionsByOptions([
            'limit' => 25,
            'cursor_direction' => CursorDirection::ASC(),
        ]);
    }

    public function paginateForward(Paginated $list): array
    {
        $all = $list->items;

        while ($list->hasMore) {
            $list = $list->getNext();
            $all = array_merge($all, $list->items);
        }

        return $all;
    }
}
