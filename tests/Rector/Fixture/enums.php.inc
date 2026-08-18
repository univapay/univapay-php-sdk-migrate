<?php

namespace UnivapayConsumer;

use Univapay\Enums\ChargeStatus;

class EnumUser
{
    /**
     * @var ChargeStatus
     */
    public $lastStatus;

    public function check($charge): bool
    {
        $direct = $charge->status === ChargeStatus::SUCCESSFUL();
        $fromValue = ChargeStatus::fromValue('successful');
        $classRef = ChargeStatus::class;

        switch ($charge->status) {
            case ChargeStatus::PENDING():
                return false;
            case ChargeStatus::SUCCESSFUL():
                return true;
            default:
                return false;
        }
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Enums\ChargeStatus;

class EnumUser
{
    /**
     * @var \Univapay\Compat\Enums\ChargeStatus
     */
    public $lastStatus;

    public function check($charge): bool
    {
        $direct = $charge->status === ChargeStatus::SUCCESSFUL();
        $fromValue = ChargeStatus::fromValue('successful');
        $classRef = ChargeStatus::class;

        switch ($charge->status) {
            case ChargeStatus::PENDING():
                return false;
            case ChargeStatus::SUCCESSFUL():
                return true;
            default:
                return false;
        }
    }
}

?>
