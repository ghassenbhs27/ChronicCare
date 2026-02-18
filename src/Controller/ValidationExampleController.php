<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Produit;
use App\Entity\User;
use App\Form\AdvancedCommandeFormType;
use App\Form\AdvancedProduitFormType;
use App\Form\AdvancedUserFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur d'exemple pour la validation côté serveur
 * 
 * PRINCIPES APPLIQUÉS:
 * ✅ Validation côté serveur avec Symfony Validator
 * ✅ Validateurs personnalisés (ValidAddress)
 * ✅ FormTypes avancés avec contraintes
 * ✅ Affichage des erreurs de validation au niveau du formulaire
 * ❌ PAS de JavaScript de validation
 * ❌ PAS d'attributs HTML5 de validation (sauf type="email" pour accessibilité)
 * 
 * FLUX:
 * 1. Utilisateur soumet le formulaire via POST
 * 2. Symfony valide les données côté serveur
 * 3. Si erreurs => rerendre le formulaire avec messages d'erreur
 * 4. Si valide => traiter et rediriger
 */
#[Route('/api/validation-examples')]
class ValidationExampleController extends AbstractController
{
    /**
     * Exemple: Créer un produit avec validation
     */
    #[Route('/produit', name: 'create_produit', methods: ['GET', 'POST'])]
    public function createProduit(Request $request, EntityManagerInterface $em): Response
    {
        $produit = new Produit();
        
        // Créer le formulaire avec validations
        $form = $this->createForm(AdvancedProduitFormType::class, $produit);
        
        // Gérer la soumission
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // ✅ Les données sont validées côté serveur à ce stade
            try {
                $em->persist($produit);
                $em->flush();
                
                $this->addFlash('success', 'Produit créé avec succès !');
                return $this->redirectToRoute('produit_show', ['id' => $produit->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue: ' . $e->getMessage());
            }
        }
        
        return $this->render('produit/create.html.twig', [
            'form' => $form->createView(),
            'errors' => $form->getErrors(true), // Collecter toutes les erreurs
        ]);
    }

    /**
     * Exemple: Créer une commande avec validation
     */
    #[Route('/commande', name: 'create_commande', methods: ['GET', 'POST'])]
    public function createCommande(Request $request, EntityManagerInterface $em): Response
    {
        $commande = new Commande();
        
        // Créer le formulaire avec validations
        $form = $this->createForm(AdvancedCommandeFormType::class, $commande);
        
        // Gérer la soumission
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // ✅ Les données sont validées côté serveur
            try {
                // Ici, vous pouvez ajouter la logique métier
                $em->persist($commande);
                $em->flush();
                
                $this->addFlash('success', 'Commande créée avec succès !');
                return $this->redirectToRoute('commande_show', ['id' => $commande->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur: ' . $e->getMessage());
            }
        }
        
        return $this->render('commande/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Exemple: Éditer un utilisateur avec validation
     */
    #[Route('/user/{id}', name: 'edit_user', methods: ['GET', 'POST'])]
    public function editUser(User $user, Request $request, EntityManagerInterface $em): Response
    {
        // Créer le formulaire avec validations
        $form = $this->createForm(AdvancedUserFormType::class, $user);
        
        // Gérer la soumission
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // ✅ Les données sont validées côté serveur
            try {
                $user->setUpdatedAt(new \DateTimeImmutable());
                $em->flush();
                
                $this->addFlash('success', 'Profil mis à jour avec succès !');
                return $this->redirectToRoute('user_profile');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur: ' . $e->getMessage());
            }
        }
        
        return $this->render('user/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }
}
