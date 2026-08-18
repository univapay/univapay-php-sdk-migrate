<?php

namespace UnivapayConsumer;

use Univapay\UnivapayClient;
use Univapay\UnivapayClientOptions;
use Univapay\Resources\Authentication\AppJWT;

function makeClient(): UnivapayClient
{
    $options = new UnivapayClientOptions();
    return new UnivapayClient(AppJWT::createToken('token', 'secret'), $options);
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\UnivapayClient;
use Univapay\Compat\UnivapayClientOptions;
use Univapay\Compat\Resources\Authentication\AppJWT;

function makeClient(): UnivapayClient
{
    $options = new UnivapayClientOptions();
    return new UnivapayClient(AppJWT::createToken('token', 'secret'), $options);
}

?>
