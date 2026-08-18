<?php

namespace UnivapayConsumer;

use Univapay\UnivapayClient as UC;

class AliasImportUser
{
    public function run(): UC
    {
        return new UC();
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\UnivapayClient;

class AliasImportUser
{
    public function run(): UnivapayClient
    {
        return new UnivapayClient();
    }
}

?>
