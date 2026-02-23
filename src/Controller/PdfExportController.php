<?php

namespace App\Controller;

use App\Repository\CommandeRepository;
use App\Repository\EvenementRepository;
use App\Repository\InscriptionEvenementRepository;
use App\Repository\ProduitRepository;
use App\Repository\RDVRepository;
use App\Service\PdfGeneratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur pour les exports PDF
 */
#[Route('/pdf', name: 'pdf_')]
class PdfExportController extends AbstractController
{
    public function __construct(
        private readonly PdfGeneratorService $pdfGenerator,
        private readonly CommandeRepository $commandeRepository,
        private readonly ProduitRepository $produitRepository,
        private readonly RDVRepository $rdvRepository,
        private readonly InscriptionEvenementRepository $inscriptionRepository
    ) {
    }

    // ==========================================
    // COMMANDES / FACTURES
    // ==========================================

    /**
     * Télécharger la facture d'une commande (patient)
     */
    #[Route('/facture/{id}', name: 'invoice', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_PATIENT')]
    public function downloadInvoice(int $id): Response
    {
        $commande = $this->commandeRepository->find($id);
        
        if (!$commande) {
            throw $this->createNotFoundException('Commande non trouvée');
        }
        
        // Vérifier que l'utilisateur est propriétaire de la commande
        if ($commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette commande');
        }
        
        return $this->pdfGenerator->generateInvoice($commande);
    }

    /**
     * Télécharger la facture (admin)
     */
    #[Route('/admin/facture/{id}', name: 'admin_invoice', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function downloadInvoiceAdmin(int $id): Response
    {
        $commande = $this->commandeRepository->find($id);
        
        if (!$commande) {
            throw $this->createNotFoundException('Commande non trouvée');
        }
        
        return $this->pdfGenerator->generateInvoice($commande);
    }

    // ==========================================
    // PRODUITS
    // ==========================================

    /**
     * Télécharger la fiche d'un produit
     */
    #[Route('/produit/{id}', name: 'product_sheet', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadProductSheet(int $id): Response
    {
        $produit = $this->produitRepository->find($id);
        
        if (!$produit) {
            throw $this->createNotFoundException('Produit non trouvé');
        }
        
        return $this->pdfGenerator->generateProductSheet($produit);
    }

    /**
     * Télécharger le catalogue complet des produits
     */
    #[Route('/catalogue', name: 'catalog', methods: ['GET'])]
    public function downloadCatalog(): Response
    {
        $produits = $this->produitRepository->findBy(['isActive' => true], ['categorie' => 'ASC', 'nom' => 'ASC']);
        
        if (empty($produits)) {
            $this->addFlash('warning', 'Aucun produit actif à exporter');
            return $this->redirectToRoute('boutique');
        }
        
        return $this->pdfGenerator->generateCatalog($produits);
    }

    /**
     * Télécharger le catalogue par catégorie
     */
    #[Route('/catalogue/{categorie}', name: 'catalog_category', methods: ['GET'])]
    public function downloadCatalogByCategory(string $categorie): Response
    {
        $produits = $this->produitRepository->findBy(
            ['isActive' => true, 'categorie' => $categorie],
            ['nom' => 'ASC']
        );
        
        if (empty($produits)) {
            $this->addFlash('warning', 'Aucun produit dans cette catégorie');
            return $this->redirectToRoute('boutique');
        }
        
        return $this->pdfGenerator->generateCatalog($produits);
    }

    // ==========================================
    // RENDEZ-VOUS
    // ==========================================

    /**
     * Télécharger la confirmation de RDV (patient)
     */
    #[Route('/rdv/{id}', name: 'rdv_confirmation', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_PATIENT')]
    public function downloadRdvConfirmation(int $id): Response
    {
        $rdv = $this->rdvRepository->find($id);
        
        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous non trouvé');
        }
        
        // Vérifier que l'utilisateur est le patient du RDV
        if ($rdv->getPatient() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce rendez-vous');
        }
        
        return $this->pdfGenerator->generateRdvConfirmation($rdv);
    }

    /**
     * Télécharger la confirmation de RDV (professionnel)
     */
    #[Route('/pro/rdv/{id}', name: 'pro_rdv_confirmation', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_PROFESSIONNEL')]
    public function downloadRdvConfirmationPro(int $id): Response
    {
        $rdv = $this->rdvRepository->find($id);
        
        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous non trouvé');
        }
        
        // Vérifier que l'utilisateur est le professionnel du RDV
        if ($rdv->getProfessionnel() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce rendez-vous');
        }
        
        return $this->pdfGenerator->generateRdvConfirmation($rdv);
    }

    // ==========================================
    // ÉVÉNEMENTS
    // ==========================================

    /**
     * Télécharger le ticket d'un événement
     */
    #[Route('/ticket/{id}', name: 'event_ticket', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadEventTicket(int $id): Response
    {
        $inscription = $this->inscriptionRepository->find($id);
        
        if (!$inscription) {
            throw $this->createNotFoundException('Inscription non trouvée');
        }
        
        // Si l'utilisateur est connecté, vérifier qu'il est bien inscrit
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        if ($user && $inscription->getEmail() !== $user->getEmail()) {
            // Permettre à l'admin de télécharger n'importe quel ticket
            if (!$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce ticket');
            }
        }
        
        return $this->pdfGenerator->generateEventTicket($inscription);
    }

    /**
     * Télécharger le ticket par email et code (pour non-connectés)
     */
    #[Route('/ticket/verify/{inscriptionId}/{code}', name: 'event_ticket_verify', methods: ['GET'])]
    public function downloadEventTicketByCode(int $inscriptionId, string $code): Response
    {
        $inscription = $this->inscriptionRepository->find($inscriptionId);
        
        if (!$inscription) {
            throw $this->createNotFoundException('Inscription non trouvée');
        }
        
        // Vérifier le code (hash simple basé sur l'email et l'ID)
        $expectedCode = substr(md5($inscription->getEmail() . $inscription->getId()), 0, 8);
        
        if ($code !== $expectedCode) {
            throw $this->createAccessDeniedException('Code de vérification invalide');
        }
        
        return $this->pdfGenerator->generateEventTicket($inscription);
    }
}
