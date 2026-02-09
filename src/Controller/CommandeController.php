<?php

namespace App\Controller;

use App\Service\CommandeService;
use App\Service\CartService;
use App\Service\PaymentService;
use App\Repository\CommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commandes', name: 'commande_')]
#[IsGranted('ROLE_USER')]
class CommandeController extends AbstractController
{
    private $commandeService;
    private $commandeRepository;
    private $cartService;
    private $paymentService;

    public function __construct(
        CommandeService $commandeService,
        CommandeRepository $commandeRepository,
        CartService $cartService,
        PaymentService $paymentService
    ) {
        $this->commandeService = $commandeService;
        $this->commandeRepository = $commandeRepository;
        $this->cartService = $cartService;
        $this->paymentService = $paymentService;
    }

    /**
     * Historique des commandes
     */
    #[Route('/', name: 'history')]
    public function history(): Response
    {
        $user = $this->getUser();
        $commandes = $this->commandeService->getUserCommandes($user);

        return $this->render('commande/history.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    /**
     * Afficher les détails d'une commande
     */
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $commande = $this->commandeService->getCommandeById($id);

        if (!$commande) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        // Vérifier que l'utilisateur est propriétaire de la commande
        if ($commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé');
        }

        return $this->render('commande/show.html.twig', [
            'commande' => $commande,
        ]);
    }

    /**
     * Créer une nouvelle commande (checkout)
     */
    #[Route('/create', name: 'create')]
    public function create(Request $request): Response
    {
        $cart = $this->cartService->getCart();

        if (empty($cart)) {
            $this->addFlash('error', 'Le panier est vide');
            return $this->redirectToRoute('cart_show');
        }

        // Récupérer les produits du panier
        $items = [];
        foreach ($cart as $item) {
            // Rechercher le produit pour avoir l'objet complet
            // Pour simplicité, on utilise les données du panier
            $items[] = $item;
        }

        // Traiter le formulaire de paiement
        if ($request->isMethod('POST')) {
            $paymentData = [
                'cardHolder' => $request->request->get('cardHolder'),
                'cardNumber' => $request->request->get('cardNumber'),
                'cvv' => $request->request->get('cvv'),
            ];

            // Valider et traiter le paiement
            if ($this->paymentService->processPayment(null, $paymentData)) {
                // Créer la commande
                $user = $this->getUser();
                $commande = $this->commandeService->createCommande($user, $items);

                // Vider le panier
                $this->cartService->clearCart();

                $this->addFlash('success', 'Commande créée avec succès!');

                return $this->redirectToRoute('commande_show', ['id' => $commande->getId()]);
            } else {
                $this->addFlash('error', 'Paiement refusé');
            }
        }

        $total = $this->cartService->getCartTotal();

        return $this->render('commande/checkout.html.twig', [
            'cart' => $cart,
            'total' => $total,
        ]);
    }

    /**
     * Suivre une commande
     */
    #[Route('/track/{numero}', name: 'track')]
    public function track(string $numero): Response
    {
        $commande = $this->commandeRepository->findByNumeroCommande($numero);

        if (!$commande) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        return $this->render('commande/track.html.twig', [
            'commande' => $commande,
        ]);
    }
}
