<?php

namespace UnivapayConsumer;

use Univapay\Enums\CardBrand, Univapay\Enums\CardType;

class CommaImportUser
{
    public function run(): array
    {
        return [CardBrand::VISA(), CardType::CREDIT()];
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Enums\CardBrand;
use Univapay\Compat\Enums\CardType;

class CommaImportUser
{
    public function run(): array
    {
        return [CardBrand::VISA(), CardType::CREDIT()];
    }
}

?>
