<?php

namespace UnivapayConsumer;

use Univapay\UnivapayClient;

class CallUserFuncUser
{
    public function run(UnivapayClient $client): void
    {
        call_user_func([$client, 'listTransfers']);
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\UnivapayClient;

class CallUserFuncUser
{
    public function run(UnivapayClient $client): void
    {
        call_user_func([$client, 'listTransfers']);
    }
}

?>
