<?php

namespace UnivapayConsumer;

use Univapay\Requests\Handlers\BasicRetryHandler;

class StringFqcnUser
{
    public function expectException(\PHPUnit\Framework\TestCase $test): void
    {
        $test->expectException('Univapay\Errors\UnivapayServerError');
    }

    public function makeRetryHandler(): BasicRetryHandler
    {
        return new BasicRetryHandler("Univapay\\Errors\\UnivapayServerError", 5, 2);
    }

    public function classExistsGuard(): bool
    {
        return class_exists('Univapay\Errors\UnivapayServerError');
    }

    public function isACheck($error): bool
    {
        return is_a($error, 'Univapay\Errors\UnivapayServerError');
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use PHPUnit\Framework\TestCase;
use Univapay\Compat\Requests\Handlers\BasicRetryHandler;

class StringFqcnUser
{
    public function expectException(TestCase $test): void
    {
        $test->expectException('Univapay\Compat\Errors\UnivapayServerError');
    }

    public function makeRetryHandler(): BasicRetryHandler
    {
        return new BasicRetryHandler('Univapay\Compat\Errors\UnivapayServerError', 5, 2);
    }

    public function classExistsGuard(): bool
    {
        return class_exists('Univapay\Compat\Errors\UnivapayServerError');
    }

    public function isACheck($error): bool
    {
        return is_a($error, 'Univapay\Compat\Errors\UnivapayServerError');
    }
}

?>
