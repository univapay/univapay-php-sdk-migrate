<?php

namespace UnivapayConsumer;

use Univapay\Errors\UnivapayRateLimitedError;
use Univapay\Errors\UnivapayServerError;

class MultiCatchUser
{
    public function run(callable $fn): void
    {
        try {
            $fn();
        } catch (UnivapayRateLimitedError | UnivapayServerError $e) {
            echo $e->getMessage();
        }
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Errors\UnivapayRateLimitedError;
use Univapay\Compat\Errors\UnivapayServerError;

class MultiCatchUser
{
    public function run(callable $fn): void
    {
        try {
            $fn();
        } catch (UnivapayRateLimitedError | UnivapayServerError $e) {
            echo $e->getMessage();
        }
    }
}

?>
