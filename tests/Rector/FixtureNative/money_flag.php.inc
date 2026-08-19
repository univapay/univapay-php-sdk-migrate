<?php

namespace UnivapayConsumer;

use Money\Money;

class MoneyUser
{
    public function run(Money $amount): void
    {
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Money\Money;

class MoneyUser
{
    // @univapay-migrate:phase2-manual Money [money] — Money\Money/Money\Currency (moneyphp) values have no native equivalent type -- UnivaPay\Models\* takes a flat int $amount + string $currency pair instead of a moneyphp object. Replace with $money->getAmount() (minor units) and $money->getCurrency()->getCode(). See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
    public function run(Money $amount): void
    {
    }
}

?>
