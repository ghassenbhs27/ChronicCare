<?php

namespace App\Tests\Unit\Service;

use App\Entity\Commande;
use App\Service\PaymentService;
use PHPUnit\Framework\TestCase;

class PaymentServiceTest extends TestCase
{
    private $paymentService;

    protected function setUp(): void
    {
        $this->paymentService = new PaymentService();
    }

    public function testValidatePaymentValid(): void
    {
        $paymentData = [
            'cardHolder' => 'John Doe',
            'cardNumber' => '1234567890123456',
            'cvv' => '123',
        ];

        $result = $this->paymentService->validatePayment(null, $paymentData);

        $this->assertTrue($result);
    }

    public function testValidatePaymentMissingData(): void
    {
        $paymentData = [
            'cardHolder' => 'John Doe',
        ];

        $result = $this->paymentService->validatePayment(null, $paymentData);

        $this->assertFalse($result);
    }

    public function testValidatePaymentInvalidCardNumber(): void
    {
        $paymentData = [
            'cardHolder' => 'John Doe',
            'cardNumber' => '123',
            'cvv' => '123',
        ];

        $result = $this->paymentService->validatePayment(null, $paymentData);

        $this->assertFalse($result);
    }

    public function testValidatePaymentInvalidCVV(): void
    {
        $paymentData = [
            'cardHolder' => 'John Doe',
            'cardNumber' => '1234567890123456',
            'cvv' => '12',
        ];

        $result = $this->paymentService->validatePayment(null, $paymentData);

        $this->assertFalse($result);
    }

    public function testCalculateShippingFeeGratuit(): void
    {
        $fee = $this->paymentService->calculateShippingFee(150);

        $this->assertEquals(0, $fee);
    }

    public function testCalculateShippingFee5(): void
    {
        $fee = $this->paymentService->calculateShippingFee(75);

        $this->assertEquals(5, $fee);
    }

    public function testCalculateShippingFeeStandard(): void
    {
        $fee = $this->paymentService->calculateShippingFee(30);

        $this->assertEquals(9.99, $fee);
    }

    public function testCalculateTotalWithShipping(): void
    {
        $total = $this->paymentService->calculateTotalWithShipping(100);

        $this->assertEquals(100, $total);
    }

    public function testVerifyAmount(): void
    {
        $commande = new Commande();
        $commande->setTotal(50);

        $result = $this->paymentService->verifyAmount($commande, 50);

        $this->assertTrue($result);
    }

    public function testVerifyAmountWithTolerance(): void
    {
        $commande = new Commande();
        $commande->setTotal(50);

        $result = $this->paymentService->verifyAmount($commande, 50.005);

        $this->assertTrue($result);
    }

    public function testVerifyAmountWrong(): void
    {
        $commande = new Commande();
        $commande->setTotal(50);

        $result = $this->paymentService->verifyAmount($commande, 75);

        $this->assertFalse($result);
    }
}
