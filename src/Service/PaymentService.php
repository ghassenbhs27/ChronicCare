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

    /**
     * Vérifier le montant du paiement
     */
    public function verifyAmount(Commande $commande, float $amount): bool
    {
        $commandeTotal = (float) $commande->getTotal();
        return abs($commandeTotal - $amount) < 0.01;
    }

    /**
     * Calculer les frais de port
     */
    public function calculateShippingFee(float $orderTotal): float
    {
        if ($orderTotal >= 100) {
            return 0;
        }
        if ($orderTotal >= 50) {
            return 5;
        }
        return 9.99;
    }

    /**
     * Calculer le total avec frais de port
     */
    public function calculateTotalWithShipping(float $orderTotal): float
    {
        $shipping = $this->calculateShippingFee($orderTotal);
        return round($orderTotal + $shipping, 2);
    }
}