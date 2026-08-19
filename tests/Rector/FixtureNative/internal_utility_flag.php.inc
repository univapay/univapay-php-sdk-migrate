<?php

namespace UnivapayConsumer;

use Univapay\Compat\Utility\DateUtils;

class InternalUtilityUser
{
    public function run(string $value): string
    {
        return DateUtils::class . $value;
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Utility\DateUtils;

class InternalUtilityUser
{
    public function run(string $value): string
    {
        // @univapay-migrate:phase2-manual DateUtils [internal-utility] — This is a verbatim port of an old-SDK standalone utility helper with no native SDK equivalent at all (the native SDK's own Utils\* classes are internal codegen support, not a public replacement) -- port the logic you depend on yourself, or keep vendoring this one function via the compat package. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#phase-2-manual-review
        return DateUtils::class . $value;
    }
}

?>
