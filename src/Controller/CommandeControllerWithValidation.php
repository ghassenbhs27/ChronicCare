<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Form\AdvancedCommandeFormType;
use App\Repository\CommandeRepository;
use App\Service\CartService;
use App\Service\CommandeService;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Psr\Log\LoggerInterface;

/**
 * Contrôleur de Commande avec Validation Côté Serveur
 * 
 * ✅ VALIDATION: Toutes les validations sont effectuées côté serveur
 * ✅ SÉCURITÉ: Impossible de contourner les validations sans JavaScript
 * ✅ UX: Messages d'erreur clairs et personnalisés en français
 */
#[Route('/commandes', name: 'commande_')]
#[IsGranted('ROLE_USER')]
class CommandeControllerWithValidation extends AbstractController
{
    public function __construct(
        private readonly CommandeService $commandeService,
        private readonly CommandeRepository $commandeRepository,
        private readonly CartService $cartService,
        private readonly PaymentService $paymentService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Créer une commande avec validation côté serveur
     * 
     * Pattern:
     * 1. Créer l'entité et le formulaire
     * 2. Lier la requête au formulaire
     * 3. Vérifier isSubmitted() && isValid()
     * 4. Si valide: persister et rediriger
     * 5. Si invalide: afficher formulaire + erreurs
     */
    #[Route('/new', name: 'new_validated', methods: ['GET', 'POST'])]
    public function createWithValidation(Request $request): Response
    {
        // 1️⃣ Créer l'entité
        $commande = new Commande();
        $commande->setUtilisateur($this->getUser());

        // 2️⃣ Créer le formulaire avec le FormType avancé
        $form = $this->createForm(AdvancedCommandeFormType::class, $commande);

        // 3️⃣ Traiter la requête
        $form->handleRequest($request);

        // 4️⃣ Vérifier si le formulaire est soumis ET valide
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // ✅ Les données sont validées côté serveur à ce stade
                try {
                    // Appliquer la logique métier
                    $commande->setCreatedAt(new \DateTimeImmutable());
                    
                    // Persister en base de données
                    $this->em->persist($commande);
                    $this->em->flush();

                    // Logger l'action
                    $this->logger->info('Commande créée', [
                        'user_id' => $this->getUser()->getId(),
                        'commande_id' => $commande->getId(),
                        'numero' => $commande->getNumeroCommande(),
                    ]);

                    // Flash message
                    $this->addFlash('success', 
                        'Commande créée avec succès ! Numéro: ' . $commande->getNumeroCommande()
                    );

                    // Rediriger
                    return $this->redirectToRoute('commande_show', [
                        'id' => $commande->getId()
                    ]);

                } catch (\Exception $e) {
                    $this->logger->error('Erreur création commande', [
                        'error' => $e->getMessage()
                    ]);
                    $this->addFlash('error', 'Une erreur est survenue lors de la création.');
                }
            } else {
                // ❌ Le formulaire a des erreurs
                // Les erreurs sont automatiquement disponibles dans form.vars.errors
                $this->logger->warning('Validation échouée', [
                    'errors' => $this->getFormErrors($form)
                ]);
                $this->addFlash('warning', 'Veuillez corriger les erreurs du formulaire.');
            }
        }

        // 5️⃣ Afficher le formulaire
        return $this->render('commande/new_validated.html.twig', [
            'form' => $form->createView(),
            'commande' => $commande,
        ]);
    }

    /**
     * Éditer une commande avec validation
     */
    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editWithValidation(Commande $commande, Request $request): Response
    {
        // Vérifier la propriété
        if ($commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas éditer cette commande.');
        }

        // Créer le formulaire
        $form = $this->createForm(AdvancedCommandeFormType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->em->flush();

                $this->logger->info('Commande modifiée', [
                    'commande_id' => $commande->getId(),
                    'user_id' => $this->getUser()->getId(),
                ]);

                $this->addFlash('success', 'Commande modifiée avec succès !');
                return $this->redirectToRoute('commande_show', ['id' => $commande->getId()]);

            } catch (\Exception $e) {
                $this->logger->error('Erreur modification commande', ['error' => $e->getMessage()]);
                $this->addFlash('error', 'Une erreur est survenue.');
            }
        }

        return $this->render('commande/edit_validated.html.twig', [
            'form' => $form->createView(),
            'commande' => $commande,
        ]);
    }

    /**
     * Afficher une commande
     */
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Commande $commande): Response
    {
        if ($commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('commande/show.html.twig', [
            'commande' => $commande,
        ]);
    }

    /**
     * Lister les commandes de l'utilisateur
     */
    #[Route('', name: 'history', methods: ['GET'])]
    public function history(): Response
    {
        $commandes = $this->commandeRepository->findBy(
            ['utilisateur' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('commande/history.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    /**
     * Supprimer une commande (avec validation)
     */
    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Commande $commande, Request $request): Response
    {
        if ($commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('delete' . $commande->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token invalide.');
        }

        try {
            $this->em->remove($commande);
            $this->em->flush();

            $this->logger->info('Commande supprimée', [
                'commande_id' => $commande->getId(),
                'user_id' => $this->getUser()->getId(),
            ]);

            $this->addFlash('success', 'Commande supprimée avec succès.');
        } catch (\Exception $e) {
            $this->logger->error('Erreur suppression', ['error' => $e->getMessage()]);
            $this->addFlash('error', 'Erreur lors de la suppression.');
        }

        return $this->redirectToRoute('commande_history');
    }

    // ========================================
    // Méthodes utilitaires
    // ========================================

    /**
     * Extraire les erreurs d'un formulaire pour le logging
     */
    private function getFormErrors($form, array &$errors = []): array
    {
        // Erreurs globales
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        // Erreurs par champ
        foreach ($form as $child) {
            if ($child->isSubmitted()) {
                foreach ($child->getErrors() as $error) {
                    $errors[$child->getName()] = $error->getMessage();
                }
            }
        }

        return $errors;
    }
}
