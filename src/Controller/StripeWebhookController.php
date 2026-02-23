<?php

namespace App\Controller;

use App\Repository\CommandeRepository;
use App\Service\CommandeService;
use App\Service\StripePaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly StripePaymentService $stripePaymentService,
        private readonly CommandeRepository $commandeRepository,
        private readonly CommandeService $commandeService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Endpoint webhook Stripe — appelé par Stripe pour notifier les événements de paiement.
     * Ne nécessite pas d'authentification utilisateur.
     */
    #[Route('/webhook/stripe', name: 'stripe_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature', '');

        try {
            $event = $this->stripePaymentService->handleWebhookEvent($payload, $sigHeader);
        } catch (\Exception $e) {
            $this->logger->error('Webhook Stripe signature invalide: ' . $e->getMessage());
            return new Response('Signature invalide', Response::HTTP_BAD_REQUEST);
        }

        // Traiter les événements
        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutSessionCompleted($event->data->object);
                break;

            case 'checkout.session.expired':
                $this->handleCheckoutSessionExpired($event->data->object);
                break;

            default:
                $this->logger->info('Webhook Stripe: événement non géré: ' . $event->type);
        }

        return new Response('OK', Response::HTTP_OK);
    }

    /**
     * Traite l'événement checkout.session.completed
     */
    private function handleCheckoutSessionCompleted(object $session): void
    {
        $sessionId = $session->id;
        $commandeId = $session->metadata->commande_id ?? null;

        if (!$commandeId) {
            $this->logger->warning('Webhook Stripe: commande_id manquant dans metadata');
            return;
        }

        $commande = $this->commandeRepository->find((int) $commandeId);
        if (!$commande) {
            $this->logger->warning('Webhook Stripe: commande non trouvée: ' . $commandeId);
            return;
        }

        // Vérifier que c'est bien la même session
        if ($commande->getStripeSessionId() !== $sessionId) {
            $this->logger->warning('Webhook Stripe: session ID mismatch pour commande ' . $commandeId);
            return;
        }

        // Sauvegarder le PaymentIntent ID
        if (isset($session->payment_intent)) {
            $commande->setStripePaymentIntentId($session->payment_intent);
        }

        // Valider automatiquement la commande
        if ($commande->getStatut() === 'en_attente') {
            $this->commandeService->applyTransition($commande, 'valider');
        }

        $this->entityManager->flush();
        $this->logger->info('Webhook Stripe: commande ' . $commande->getNumeroCommande() . ' validée après paiement');
    }

    /**
     * Traite l'événement checkout.session.expired (session expirée sans paiement)
     */
    private function handleCheckoutSessionExpired(object $session): void
    {
        $commandeId = $session->metadata->commande_id ?? null;

        if (!$commandeId) {
            return;
        }

        $commande = $this->commandeRepository->find((int) $commandeId);
        if (!$commande) {
            return;
        }

        // Annuler la commande si elle est toujours en attente
        if ($commande->getStatut() === 'en_attente') {
            $this->commandeService->applyTransition($commande, 'annuler_depuis_attente');
            $this->entityManager->flush();
            $this->logger->info('Webhook Stripe: commande ' . $commande->getNumeroCommande() . ' annulée (session expirée)');
        }
    }
}
