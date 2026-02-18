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
            // Validation côté serveur
            $errors = [];
            
            // Validation adresse
            $adresse = trim((string) $request->request->get('adresse', ''));
            if (empty($adresse)) {
                $errors[] = 'L\'adresse de livraison est obligatoire';
            } elseif (strlen($adresse) < 10) {
                $errors[] = 'L\'adresse doit contenir au moins 10 caracteres';
            } elseif (preg_match('/[<>"\'{};]/', $adresse)) {
                $errors[] = 'L\'adresse contient des caracteres non autorises';
            }

            // Determiner le mode de paiement
            $paymentMethod = $request->request->get('paymentMethod', 'card');
            
            if ($paymentMethod === 'card') {
                // Validation nom sur carte
                $cardHolder = trim((string) $request->request->get('cardHolder', ''));
                if (empty($cardHolder)) {
                    $errors[] = 'Le nom sur la carte est obligatoire';
                } elseif (strlen($cardHolder) < 2) {
                    $errors[] = 'Le nom sur la carte doit contenir au moins 2 caracteres';
                } elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\'-]+$/', $cardHolder)) {
                    $errors[] = 'Le nom sur la carte ne peut contenir que des lettres';
                }
                
                // Validation numero de carte
                $cardNumber = preg_replace('/\s+/', '', (string) $request->request->get('cardNumber', ''));
                if (empty($cardNumber)) {
                    $errors[] = 'Le numero de carte est obligatoire';
                } elseif (!preg_match('/^\d{16}$/', $cardNumber)) {
                    $errors[] = 'Le numero de carte doit contenir exactement 16 chiffres';
                }
                
                // Validation CVV
                $cvv = trim((string) $request->request->get('cvv', ''));
                if (empty($cvv)) {
                    $errors[] = 'Le code CVV est obligatoire';
                } elseif (!preg_match('/^\d{3,4}$/', $cvv)) {
                    $errors[] = 'Le code CVV doit contenir 3 ou 4 chiffres';
                }
            }
            
            // Si erreurs de validation
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->render('commande/checkout.html.twig', [
                    'cart' => $cart,
                    'total' => $this->cartService->getCartTotal(),
                ]);
            }
            
            // Donnees validees, continuer le traitement
            $paymentSuccess = false;

            if ($paymentMethod === 'cash_on_delivery') {
                $paymentSuccess = true;
            } else {
                $paymentData = [
                    'cardHolder' => $cardHolder,
                    'cardNumber' => $cardNumber,
                    'cvv' => $cvv,
                ];
                $paymentSuccess = $this->paymentService->processPayment(null, $paymentData);
            }

            if ($paymentSuccess) {
                $commande = $this->commandeService->createCommande(
                    $this->getUser(),
                    array_values($cart),
                    $adresse,
                    $paymentMethod
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
            return $this->redirectToRoute('commande_create');
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
}
