<?php

namespace App\Controller;

use App\Repository\CommandeRepository;
use App\Service\CartService;
use App\Service\CommandeService;
use App\Service\PaymentService;
use App\Service\StripePaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commandes', name: 'commande_')]
#[IsGranted('ROLE_PATIENT')]
class CommandeController extends AbstractController
{
    public function __construct(
        private readonly CommandeService $commandeService,
        private readonly CommandeRepository $commandeRepository,
        private readonly CartService $cartService,
        private readonly PaymentService $paymentService,
        private readonly StripePaymentService $stripePaymentService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('', name: 'history', methods: ['GET'])]
    public function history(): Response
    {
        $this->denyIfNotPatient();
        $commandes = $this->commandeService->getUserCommandes($this->getUser());

        return $this->render('commande/history.html.twig', ['commandes' => $commandes]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $this->denyIfNotPatient();
        $commande = $this->commandeService->getCommandeById($id);
        if (!$commande || $commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createNotFoundException('Commande non trouvee');
        }

        return $this->render('commande/show.html.twig', ['commande' => $commande]);
    }

    #[Route('/checkout', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->denyIfNotPatient();
        $cart = $this->cartService->getCart();
        if ($this->cartService->isEmpty()) {
            $this->addFlash('error', 'Le panier est vide');
            return $this->redirectToRoute('cart_show');
        }

        if ($request->isMethod('POST') && $this->isCsrfTokenValid('checkout', (string) $request->request->get('_token'))) {
            // Récupérer l'IP du client
            $clientIp = $request->getClientIp();

            // Déterminer le mode de paiement
            $paymentMethod = $request->request->get('paymentMethod', 'stripe');
            $isCashPayment = in_array($paymentMethod, ['cash', 'cash_on_delivery'], true);

            // Créer la commande avec géolocalisation automatique
            $commande = $this->commandeService->createCommande(
                $this->getUser(),
                array_values($cart),
                $clientIp,
                $isCashPayment ? 'cash' : 'stripe'
            );

            if (!$commande) {
                $this->addFlash('error', 'Stock insuffisant pour certains produits. Vérifiez le panier.');
                return $this->redirectToRoute('cart_show');
            }

            // Paiement à la livraison : pas besoin de Stripe
            if ($isCashPayment) {
                $this->cartService->clearCart();
                $this->addFlash('success', 'Commande créée avec succès ! Paiement à la livraison.');
                return $this->redirectToRoute('commande_show', ['id' => $commande->getId()]);
            }

            // Paiement Stripe : créer une session Checkout
            try {
                $session = $this->stripePaymentService->createCheckoutSession($commande, array_values($cart));

                // Sauvegarder l'ID de session Stripe dans la commande
                $commande->setStripeSessionId($session->id);
                $this->entityManager->flush();

                // Vider le panier maintenant (la commande est créée)
                $this->cartService->clearCart();

                // Rediriger vers la page de paiement Stripe
                return $this->redirect($session->url);
            } catch (\Exception $e) {
                $this->logger->error('Erreur Stripe Checkout: ' . $e->getMessage());
                $this->addFlash('error', 'Erreur lors de la création de la session de paiement. Veuillez réessayer.');
                return $this->redirectToRoute('commande_create');
            }
        }

        return $this->render('commande/checkout.html.twig', [
            'cart' => $cart,
            'total' => $this->cartService->getCartTotal(),
            'stripe_public_key' => $this->stripePaymentService->getPublicKey(),
        ]);
    }

    /**
     * Page de succès après paiement Stripe
     */
    #[Route('/stripe/success/{id}', name: 'stripe_success', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function stripeSuccess(int $id, Request $request): Response
    {
        $this->denyIfNotPatient();
        $commande = $this->commandeService->getCommandeById($id);

        if (!$commande || $commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        $sessionId = $request->query->get('session_id');

        if ($sessionId && $commande->getStripeSessionId() === $sessionId) {
            // Vérifier le paiement auprès de Stripe
            if ($this->stripePaymentService->isPaymentSuccessful($sessionId)) {
                // Récupérer le PaymentIntent ID
                try {
                    $session = $this->stripePaymentService->retrieveSession($sessionId);
                    $commande->setStripePaymentIntentId($session->payment_intent);

                    // Valider automatiquement la commande
                    if ($commande->getStatut() === 'en_attente') {
                        $this->commandeService->applyTransition($commande, 'valider');
                    }
                    $this->entityManager->flush();
                } catch (\Exception $e) {
                    $this->logger->error('Erreur récupération session Stripe: ' . $e->getMessage());
                }

                $this->addFlash('success', 'Paiement effectué avec succès ! Votre commande a été validée.');
            } else {
                $this->addFlash('warning', 'Le paiement est en cours de traitement. Vous recevrez une confirmation bientôt.');
            }
        }

        return $this->render('commande/stripe_success.html.twig', [
            'commande' => $commande,
        ]);
    }

    /**
     * Page d'annulation du paiement Stripe
     */
    #[Route('/stripe/cancel/{id}', name: 'stripe_cancel', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function stripeCancel(int $id): Response
    {
        $this->denyIfNotPatient();
        $commande = $this->commandeService->getCommandeById($id);

        if (!$commande || $commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        $this->addFlash('warning', 'Le paiement a été annulé. Votre commande reste en attente.');

        return $this->render('commande/stripe_cancel.html.twig', [
            'commande' => $commande,
        ]);
    }

    #[Route('/track/{numero}', name: 'track', requirements: ['numero' => '[A-Za-z0-9_-]{3,50}'], methods: ['GET'])]
    public function track(string $numero): Response
    {
        $this->denyIfNotPatient();
        $commande = $this->commandeRepository->findByNumeroCommande($numero);
        if (!$commande) {
            throw $this->createNotFoundException('Commande non trouvee');
        }
        if ($commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Acces refuse');
        }

        return $this->render('commande/track.html.twig', ['commande' => $commande]);
    }

    private function denyIfNotPatient(): void
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_COACH') || $this->isGranted('ROLE_NUTRITIONNISTE')) {
            throw $this->createAccessDeniedException('Les commandes sont reservees aux patients.');
        }
    }
}
