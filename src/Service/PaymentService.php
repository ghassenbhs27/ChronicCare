<?php

namespace App\Service;

use App\Entity\Commande;

class PaymentService
{
    /**
     * Valider un paiement (simulation simple)
     * En production, utiliser Stripe ou autre service de paiement
     */
    public function validatePayment(Commande $commande, array $paymentData): bool
    {
        // Vérifier que les données de paiement sont présentes
        if (!isset($paymentData['cardHolder']) || !isset($paymentData['cardNumber']) || !isset($paymentData['cvv'])) {
            return false;
        }

        $cardNumber = $paymentData['cardNumber'];
        $cvv = $paymentData['cvv'];
        $cardHolder = $paymentData['cardHolder'];

        // Validations simples
        if (empty($cardNumber) || empty($cvv) || empty($cardHolder)) {
            return false;
        }

        // Vérifier le format du numéro de carte (14-16 chiffres)
        if (!preg_match('/^\d{14,16}$/', str_replace(' ', '', $cardNumber))) {
            return false;
        }

        // Vérifier le CVV (3-4 chiffres)
        if (!preg_match('/^\d{3,4}$/', $cvv)) {
            return false;
        }

        // Vérifier que le nom n'est pas vide
        if (strlen($cardHolder) < 3) {
            return false;
        }

        // Paiement valide
        return true;
    }

    /**
     * Traiter un paiement
     */
    public function processPayment(Commande $commande, array $paymentData): bool
    {
        // Valider les données de paiement
        if (!$this->validatePayment($commande, $paymentData)) {
            return false;
        }

        // Ici on pourrait appeler une API de paiement (Stripe, PayPal, etc.)
        // Pour cette version simple, on simule juste le paiement
        
        return true;
    }

    /**
     * Vérifier le montant du paiement
     */
    public function verifyAmount(Commande $commande, float $amount): bool
    {
        $commandeTotal = (float) $commande->getTotal();
        return abs($commandeTotal - $amount) < 0.01; // Tolérance de 0.01€
    }

    /**
     * Calculer les frais de port
     */
    public function calculateShippingFee(float $orderTotal): float
    {
        if ($orderTotal >= 100) {
            return 0; // Livraison gratuite dès 100€
        }

        if ($orderTotal >= 50) {
            return 5; // 5€ de frais
        }

        return 9.99; // Frais standard
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
