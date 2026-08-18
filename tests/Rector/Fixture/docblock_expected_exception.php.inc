<?php

namespace UnivapayConsumer;

class LegacyPhpUnitStyleTest
{
    /**
     * @expectedException \Univapay\Errors\UnivapayServerError
     */
    public function testThrowsServerError(): void
    {
    }

    /**
     * @covers \Univapay\UnivapayClient::createCharge
     * @uses Univapay\Resources\Charge
     */
    public function testCreateCharge(): void
    {
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

class LegacyPhpUnitStyleTest
{
    /**
     * @expectedException \Univapay\Compat\Errors\UnivapayServerError
     */
    public function testThrowsServerError(): void
    {
    }

    /**
     * @covers \Univapay\Compat\UnivapayClient::createCharge
     * @uses Univapay\Compat\Resources\Charge
     */
    public function testCreateCharge(): void
    {
    }
}

?>
