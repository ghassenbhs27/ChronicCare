<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Service\CartService;
use App\Service\CommandeService;
use App\Service\PaymentService;
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
        private readonly PaymentService $paymentService
    ) {
    }

    #[Route('', name: 'history', methods: ['GET'])]
    public function history(): Response
    {
        $this->denyIfNotPatient();
        $commandes = $this->commandeService->getUserCommandes($this->getAuthenticatedUser());

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

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('checkout', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de securite invalide.');
                return $this->redirectToRoute('commande_create');
            }

            $adresse = trim((string) $request->request->get('adresse', ''));
            $paymentData = [
                'cardHolder' => trim((string) $request->request->get('cardHolder', '')),
                'cardNumber' => preg_replace('/\s+/', '', (string) $request->request->get('cardNumber', '')),
                'cvv' => trim((string) $request->request->get('cvv', '')),
            ];

            $errors = $this->validateCheckoutInput($adresse, $paymentData);
            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }

                return $this->render('commande/checkout.html.twig', [
                    'cart' => $cart,
                    'total' => $this->cartService->getCartTotal(),
                ]);
            }

            if ($this->paymentService->processPayment(null, $paymentData)) {
                $commande = $this->commandeService->createCommande(
                    $this->getAuthenticatedUser(),
                    array_values($cart),
                    $adresse
                );
                if (!$commande) {
                    $this->addFlash('error', 'Stock insuffisant pour certains produits. Verifiez le panier.');
                    return $this->redirectToRoute('cart_show');
                }
                $this->cartService->clearCart();
                $this->addFlash('success', 'Commande creee avec succes !');
                return $this->redirectToRoute('commande_show', ['id' => $commande->getId()]);
            }

            $this->addFlash('error', 'Paiement refuse. Verifiez les informations.');
        }

        return $this->render('commande/checkout.html.twig', [
            'cart' => $cart,
            'total' => $this->cartService->getCartTotal(),
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

    /**
     * @return string[]
     */
    private function validateCheckoutInput(string $adresse, array $paymentData): array
    {
        $errors = [];

        if ($adresse === '') {
            $errors[] = 'L\'adresse de livraison est obligatoire.';
        } elseif (mb_strlen($adresse) < 5 || mb_strlen($adresse) > 1000) {
            $errors[] = 'L\'adresse doit contenir entre 5 et 1000 caracteres.';
        }

        $cardHolder = (string) ($paymentData['cardHolder'] ?? '');
        if (!preg_match('/^[\p{L} .\'-]{3,100}$/u', $cardHolder)) {
            $errors[] = 'Le nom sur la carte est invalide.';
        }

        $cardNumber = (string) ($paymentData['cardNumber'] ?? '');
        if (!preg_match('/^\d{14,16}$/', $cardNumber)) {
            $errors[] = 'Le numero de carte doit contenir 14 a 16 chiffres.';
        }

        $cvv = (string) ($paymentData['cvv'] ?? '');
        if (!preg_match('/^\d{3,4}$/', $cvv)) {
            $errors[] = 'Le CVV doit contenir 3 ou 4 chiffres.';
        }

        return $errors;
    }

    private function denyIfNotPatient(): void
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_COACH') || $this->isGranted('ROLE_NUTRITIONNISTE')) {
            throw $this->createAccessDeniedException('Les commandes sont reservees aux patients.');
        }
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        return $user;
    }
}
