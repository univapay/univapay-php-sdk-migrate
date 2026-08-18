<?php

namespace UnivapayConsumer;

use Univapay;

class NamespacePrefixImportUser
{
    public function run(): Univapay\UnivapayClient
    {
        return new Univapay\UnivapayClient();
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\UnivapayClient;
use Univapay;

class NamespacePrefixImportUser
{
    public function run(): UnivapayClient
    {
        return new UnivapayClient();
    }
}

?>
