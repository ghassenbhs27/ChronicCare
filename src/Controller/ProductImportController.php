<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Repository\ProduitRepository;
use App\Service\AIDataCleanerService;
use App\Service\ExcelImportService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/import', name: 'admin_import_')]
#[IsGranted('ROLE_ADMIN')]
class ProductImportController extends AbstractController
{
    public function __construct(
        private readonly ExcelImportService $excelImportService,
        private readonly AIDataCleanerService $aiCleanerService,
        private readonly ProduitRepository $produitRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Page d'upload du fichier Excel
     */
    #[Route('/products', name: 'products', methods: ['GET'])]
    public function index(): Response
    {
        $aiStatus = $this->aiCleanerService->testConnection();
        
        return $this->render('admin/import/index.html.twig', [
            'aiConfigured' => $aiStatus['success'],
            'aiMessage' => $aiStatus['message']
        ]);
    }

    /**
     * Traitement de l'upload et prévisualisation
     */
    #[Route('/products/upload', name: 'products_upload', methods: ['POST'])]
    public function upload(Request $request, SessionInterface $session): Response
    {
        $file = $request->files->get('excel_file');
        
        if (!$file) {
            $this->addFlash('error', 'Veuillez sélectionner un fichier à importer.');
            return $this->redirectToRoute('admin_import_products');
        }

        // Lire le fichier Excel
        $result = $this->excelImportService->readFile($file);
        
        if (!$result['success']) {
            $this->addFlash('error', $result['error']);
            return $this->redirectToRoute('admin_import_products');
        }

        // Obtenir les catégories existantes
        $existingCategories = $this->produitRepository->findAllCategories();
        
        // Déterminer le mode de traitement
        $useAI = $request->request->get('use_ai', '0') === '1';
        
        if ($useAI && $this->aiCleanerService->isConfigured()) {
            // Traitement avec AI
            $processed = $this->excelImportService->processWithAI($result['data'], $existingCategories);
        } else {
            // Traitement basique sans AI
            $processed = $this->excelImportService->processBasic($result['data']);
        }

        // Stocker les données en session pour la confirmation
        $session->set('import_products', $processed['products']);
        $session->set('import_stats', $processed['stats']);
        $session->set('import_filename', $file->getClientOriginalName());

        return $this->render('admin/import/preview.html.twig', [
            'products' => $processed['products'],
            'stats' => $processed['stats'],
            'filename' => $file->getClientOriginalName(),
            'usedAI' => $useAI && $this->aiCleanerService->isConfigured()
        ]);
    }

    /**
     * Confirmation et import final
     */
    #[Route('/products/confirm', name: 'products_confirm', methods: ['POST'])]
    public function confirm(Request $request, SessionInterface $session): Response
    {
        $products = $session->get('import_products');
        
        if (empty($products)) {
            $this->addFlash('error', 'Aucune donnée à importer. Veuillez recommencer.');
            return $this->redirectToRoute('admin_import_products');
        }

        $selectedIndexes = $request->request->all('selected_products');
        
        if (empty($selectedIndexes)) {
            $this->addFlash('warning', 'Aucun produit sélectionné pour l\'import.');
            return $this->redirectToRoute('admin_import_products');
        }

        $imported = 0;
        $errors = [];

        foreach ($products as $productData) {
            // Vérifier si ce produit est sélectionné
            if (!in_array((string)$productData['index'], $selectedIndexes)) {
                continue;
            }

            $cleanedData = $productData['cleaned'];
            
            // Valider le produit
            $validationErrors = $this->excelImportService->validateProduct($cleanedData);
            
            if (!empty($validationErrors)) {
                $errors[] = "Ligne {$productData['index']}: " . implode(', ', $validationErrors);
                continue;
            }

            try {
                // Créer le produit
                $produit = new Produit();
                $produit->setNom($cleanedData['nom']);
                $produit->setDescription($cleanedData['description']);
                $produit->setPrix($cleanedData['prix']);
                $produit->setStock((int)$cleanedData['stock']);
                $produit->setCategorie($cleanedData['categorie']);
                $produit->setIsActive(true);

                $this->entityManager->persist($produit);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Ligne {$productData['index']}: Erreur d'import - " . $e->getMessage();
            }
        }

        if ($imported > 0) {
            $this->entityManager->flush();
        }

        // Nettoyer la session
        $session->remove('import_products');
        $session->remove('import_stats');
        $session->remove('import_filename');

        if ($imported > 0) {
            $this->addFlash('success', "$imported produit(s) importé(s) avec succès.");
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->redirectToRoute('admin_import_products');
    }

    /**
     * Annuler l'import en cours
     */
    #[Route('/products/cancel', name: 'products_cancel', methods: ['GET'])]
    public function cancel(SessionInterface $session): Response
    {
        $session->remove('import_products');
        $session->remove('import_stats');
        $session->remove('import_filename');

        $this->addFlash('info', 'Import annulé.');
        return $this->redirectToRoute('admin_import_products');
    }

    /**
     * Télécharger le modèle Excel
     */
    #[Route('/products/template', name: 'products_template', methods: ['GET'])]
    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = $this->excelImportService->generateTemplate();
        
        $response = new StreamedResponse(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="modele_produits.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /**
     * Tester la connexion OpenAI
     */
    #[Route('/products/test-ai', name: 'products_test_ai', methods: ['GET'])]
    public function testAI(): Response
    {
        $result = $this->aiCleanerService->testConnection();
        
        return $this->json($result);
    }

    /**
     * Prévisualisation d'une correction AI en temps réel
     */
    #[Route('/products/preview-correction', name: 'products_preview_correction', methods: ['POST'])]
    public function previewCorrection(Request $request): Response
    {
        $text = $request->request->get('text', '');
        $type = $request->request->get('type', 'nom');

        if (empty($text)) {
            return $this->json(['error' => 'Texte vide']);
        }

        if (!$this->aiCleanerService->isConfigured()) {
            return $this->json(['error' => 'AI non configurée']);
        }

        $corrected = $this->aiCleanerService->correctSpelling($text, $type);

        return $this->json([
            'original' => $text,
            'corrected' => $corrected,
            'changed' => $text !== $corrected
        ]);
    }
}
