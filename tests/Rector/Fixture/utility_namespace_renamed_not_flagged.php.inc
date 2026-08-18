<?php

namespace UnivapayConsumer;

use Univapay\Utility\FormatterUtils;

class UtilityNamespaceUser
{
    public function run(): string
    {
        return FormatterUtils::class;
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Utility\FormatterUtils;

class UtilityNamespaceUser
{
    public function run(): string
    {
        return FormatterUtils::class;
    }
}

?>
