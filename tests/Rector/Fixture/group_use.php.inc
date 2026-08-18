<?php

namespace UnivapayConsumer;

use Univapay\Enums\{ChargeStatus, RefundStatus};

class GroupUseUser
{
    public function run(): array
    {
        return [ChargeStatus::SUCCESSFUL(), RefundStatus::SUCCESSFUL()];
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Enums\ChargeStatus;
use Univapay\Compat\Enums\RefundStatus;

class GroupUseUser
{
    public function run(): array
    {
        return [ChargeStatus::SUCCESSFUL(), RefundStatus::SUCCESSFUL()];
    }
}

?>
