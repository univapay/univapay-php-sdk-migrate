<?php

namespace UnivapayConsumer;

use Univapay\Compat\UnivapayClient;

class DynamicAccessUser
{
    public function run($obj, string $method, string $prop)
    {
        $obj->{$method}();
        return $obj->{$prop};
    }
}

?>
