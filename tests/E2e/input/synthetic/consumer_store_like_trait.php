<?php

namespace UnivapayConsumer\Traits;

use Univapay\Resources\Mixins\GetCharges;
use Univapay\Resources\Mixins\GetSubscriptions;

/**
 * E2E synthetic fixture: a consumer class composing Univapay SDK mixin traits directly, the way
 * a "Store-like" wrapper object in a consumer's own domain model might. Two traits at once, to
 * confirm each `use TraitName;` inside the class body gets its own correctly-renamed `use`
 * import at the top of the file (trait-use statements inside a class body are a different node
 * than file-level import `Use_`/`GroupUse` statements -- both must resolve correctly).
 *
 * (Doc comment placed after the imports, not before -- see synthetic/internal_api_usage.php's
 * note on why.)
 */
class MerchantStoreWrapper
{
    use GetCharges;
    use GetSubscriptions;

    /** @var string */
    private $storeId;

    public function __construct(string $storeId)
    {
        $this->storeId = $storeId;
    }
}
