<?php

namespace UnivapayConsumer;

use Univapay\Compat\Enums\ChargeStatus;

class TypedEnumUser
{
    public function run($status): bool
    {
        return $status === ChargeStatus::SUCCESSFUL();
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Enums\ChargeStatus;

class TypedEnumUser
{
    public function run($status): bool
    {
        // @univapay-migrate:phase2-manual ChargeStatus [typed-enum] — TypedEnum singleton usage (e.g. ChargeStatus::SUCCESSFUL(), ->getValue(), === comparisons, switch) has no native equivalent object -- UnivaPay\Models\* enums are plain string public const groups (e.g. UnivaPay\Models\ChargeStatus::SUCCESSFUL === 'successful'). Replace singleton calls/comparisons with the native class's const. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
        return $status === ChargeStatus::SUCCESSFUL();
    }
}

?>
