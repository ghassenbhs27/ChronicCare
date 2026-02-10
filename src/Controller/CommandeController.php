<?php

namespace App\Controller;

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
#[IsGranted('ROLE_USER')]
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
        $commandes = $this->commandeService->getUserCommandes($this->getUser());

        return $this->render('commande/history.html.twig', ['commandes' => $commandes]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $commande = $this->commandeService->getCommandeById($id);
        if (!$commande || $commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        return $this->render('commande/show.html.twig', ['commande' => $commande]);
    }

    #[Route('/checkout', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $cart = $this->cartService->getCart();
        if ($this->cartService->isEmpty()) {
            $this->addFlash('error', 'Le panier est vide');
            return $this->redirectToRoute('cart_show');
        }

        if ($request->isMethod('POST') && $this->isCsrfTokenValid('checkout', $request->request->get('_token'))) {
            $paymentData = [
                'cardHolder' => $request->request->get('cardHolder'),
                'cardNumber' => $request->request->get('cardNumber'),
                'cvv' => $request->request->get('cvv'),
            ];

            if ($this->paymentService->processPayment(null, $paymentData)) {
                $commande = $this->commandeService->createCommande(
                    $this->getUser(),
                    array_values($cart),
                    $request->request->get('adresse')
                );
                if (!$commande) {
                    $this->addFlash('error', 'Stock insuffisant pour certains produits. Vérifiez le panier.');
                    return $this->redirectToRoute('cart_show');
                }
                $this->cartService->clearCart();
                $this->addFlash('success', 'Commande créée avec succès !');
                return $this->redirectToRoute('commande_show', ['id' => $commande->getId()]);
            }
            $this->addFlash('error', 'Paiement refusé. Vérifiez les informations.');
        }

        return $this->render('commande/checkout.html.twig', [
            'cart' => $cart,
            'total' => $this->cartService->getCartTotal(),
        ]);
    }

    #[Route('/track/{numero}', name: 'track', methods: ['GET'])]
    public function track(string $numero): Response
    {
        $commande = $this->commandeRepository->findByNumeroCommande($numero);
        if (!$commande) {
            throw $this->createNotFoundException('Commande non trouvée');
        }
        if ($commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé');
        }

        return $this->render('commande/track.html.twig', ['commande' => $commande]);
    }
}
