<?php

namespace App\Service;

use App\Entity\Commande;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StripePaymentService
{
    public function __construct(
        private readonly string $stripeSecretKey,
        private readonly string $stripePublicKey,
        private readonly string $stripeWebhookSecret,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    /**
     * Retourne la clé publique Stripe (pour le frontend)
     */
    public function getPublicKey(): string
    {
        return $this->stripePublicKey;
    }

    /**
     * Crée une session Stripe Checkout pour une commande
     *
     * @param Commande $commande La commande à payer
     * @param array $cartItems Les articles du panier [{nom, prix, quantity, ...}]
     * @return Session La session Stripe Checkout
     * @throws ApiErrorException
     */
    public function createCheckoutSession(Commande $commande, array $cartItems): Session
    {
        $lineItems = [];

        foreach ($cartItems as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $item['nom'],
                    ],
                    'unit_amount' => (int) round($item['prix'] * 100), // Stripe utilise les centimes
                ],
                'quantity' => $item['quantity'],
            ];
        }

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $this->urlGenerator->generate('commande_stripe_success', [
                'id' => $commande->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->urlGenerator->generate('commande_stripe_cancel', [
                'id' => $commande->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
            'metadata' => [
                'commande_id' => $commande->getId(),
                'numero_commande' => $commande->getNumeroCommande(),
            ],
            'customer_email' => $commande->getUtilisateur()->getEmail(),
        ]);

        return $session;
    }

    /**
     * Vérifie et décode un événement webhook Stripe
     *
     * @param string $payload Le corps brut de la requête
     * @param string $sigHeader La signature Stripe (header Stripe-Signature)
     * @return \Stripe\Event L'événement Stripe vérifié
     * @throws SignatureVerificationException
     */
    public function handleWebhookEvent(string $payload, string $sigHeader): \Stripe\Event
    {
        return Webhook::constructEvent(
            $payload,
            $sigHeader,
            $this->stripeWebhookSecret
        );
    }

    /**
     * Récupère une session Stripe Checkout par son ID
     *
     * @param string $sessionId L'ID de la session
     * @return Session
     * @throws ApiErrorException
     */
    public function retrieveSession(string $sessionId): Session
    {
        return Session::retrieve($sessionId);
    }

    /**
     * Vérifie si un paiement Stripe a été complété avec succès
     *
     * @param string $sessionId L'ID de la session
     * @return bool
     */
    public function isPaymentSuccessful(string $sessionId): bool
    {
        try {
            $session = $this->retrieveSession($sessionId);
            return $session->payment_status === 'paid';
        } catch (ApiErrorException $e) {
            return false;
        }
    }
}
