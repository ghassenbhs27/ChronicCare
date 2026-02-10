<?php

namespace App\Service;

use App\Entity\Commande;

class PaymentService
{
    public function validatePayment(Commande $commande, array $paymentData): bool
    {
        if (!isset($paymentData['cardHolder']) || !isset($paymentData['cardNumber']) || !isset($paymentData['cvv'])) {
            return false;
        }
        $cardNumber = str_replace(' ', '', (string) $paymentData['cardNumber']);
        $cvv = (string) $paymentData['cvv'];
        $cardHolder = (string) $paymentData['cardHolder'];

        if (empty($cardNumber) || empty($cvv) || empty($cardHolder)) {
            return false;
        }
        if (!preg_match('/^\d{14,16}$/', $cardNumber)) {
            return false;
        }
        if (!preg_match('/^\d{3,4}$/', $cvv)) {
            return false;
        }
        return strlen($cardHolder) >= 3;
    }

    public function processPayment(?Commande $commande, array $paymentData): bool
    {
        return $this->validatePayment($commande ?? new Commande(), $paymentData);
    }
}
