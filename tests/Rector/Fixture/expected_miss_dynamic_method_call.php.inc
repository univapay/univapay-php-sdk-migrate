<?php

namespace UnivapayConsumer;

use Univapay\UnivapayClient;

class DynamicMethodCallUser
{
    public function run(UnivapayClient $client): void
    {
        $client->{'listTransfers'}();
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\UnivapayClient;

class DynamicMethodCallUser
{
    public function run(UnivapayClient $client): void
    {
        $client->{'listTransfers'}();
    }
}

?>
