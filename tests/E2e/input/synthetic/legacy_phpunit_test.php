<?php

namespace UnivapayConsumer\Tests;

use PHPUnit\Framework\TestCase;

/**
 * E2E synthetic fixture: a PHPUnit test class mixing the legacy `@expectedException` docblock
 * style (PHPUnit 8 and earlier still reads and *executes* this tag -- it is not just
 * documentation) with the modern `expectException('Fully\Qualified\String')` runtime call, plus
 * `@covers`/`@uses` referencing old-SDK FQCNs, all in the same class. Exercises
 * RenameDocblockTagFqcnRector and the built-in RenameStringRector together on the same file, the
 * combination the isolated unit fixtures (docblock_expected_exception.php.inc, string_fqcn.php.inc)
 * each cover only in isolation.
 */
class LegacyStyleCompatibilityTest extends TestCase
{
    /**
     * @expectedException \Univapay\Errors\UnivapayServerError
     */
    public function testLegacyAnnotationStyleThrowsServerError(): void
    {
        throw new \Univapay\Errors\UnivapayServerError(500, 'https://api.univapay.com/charges');
    }

    /**
     * @covers \Univapay\UnivapayClient::createCharge
     * @uses Univapay\Resources\Charge
     */
    public function testModernStyleThrowsRateLimitedError(): void
    {
        $this->expectException('Univapay\Errors\UnivapayRateLimitedError');

        throw new \Univapay\Errors\UnivapayRateLimitedError(429, 'https://api.univapay.com/charges');
    }

    /**
     * @expectedException \Univapay\Errors\UnivapayNotFoundError
     * @covers \Univapay\UnivapayClient::getCharge
     */
    public function testBothStylesTogether(): void
    {
        $this->expectException("Univapay\\Errors\\UnivapayNotFoundError");

        throw new \Univapay\Errors\UnivapayNotFoundError(404, 'https://api.univapay.com/charges/x');
    }
}
