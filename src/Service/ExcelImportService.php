<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Service pour l'import de fichiers Excel de produits
 */
class ExcelImportService
{
    // Colonnes attendues dans le fichier Excel
    public const EXPECTED_COLUMNS = [
        'nom' => ['nom', 'name', 'produit', 'product', 'titre', 'title', 'libelle', 'designation'],
        'description' => ['description', 'desc', 'details', 'detail', 'info', 'information'],
        'prix' => ['prix', 'price', 'tarif', 'cout', 'cost', 'montant', 'pu', 'prix_unitaire'],
        'stock' => ['stock', 'quantite', 'quantity', 'qty', 'qte', 'inventaire', 'inventory'],
        'categorie' => ['categorie', 'category', 'cat', 'type', 'famille', 'rayon', 'section']
    ];

    public function __construct(
        private AIDataCleanerService $aiCleaner,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Lit un fichier Excel et retourne les données brutes
     * @return array{success: bool, data?: array, error?: string, mapping?: array}
     */
    public function readFile(UploadedFile $file): array
    {
        try {
            $extension = strtolower($file->getClientOriginalExtension());
            
            // Vérifier l'extension
            if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                return [
                    'success' => false,
                    'error' => 'Format de fichier non supporté. Utilisez .xlsx, .xls ou .csv'
                ];
            }

            // Charger le fichier
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Récupérer toutes les données
            $data = $worksheet->toArray(null, true, true, true);
            
            if (empty($data)) {
                return [
                    'success' => false,
                    'error' => 'Le fichier est vide'
                ];
            }

            // La première ligne contient les en-têtes
            $headers = array_shift($data);
            $headers = array_map(function($h) {
                return mb_strtolower(trim((string)$h));
            }, $headers);

            // Mapper les colonnes
            $mapping = $this->mapColumns($headers);
            
            if (empty($mapping)) {
                return [
                    'success' => false,
                    'error' => 'Impossible de détecter les colonnes. Vérifiez que votre fichier contient les en-têtes: nom, description, prix, stock, categorie'
                ];
            }

            // Convertir les données avec le mapping
            $products = [];
            foreach ($data as $rowIndex => $row) {
                // Ignorer les lignes vides
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $product = [];
                foreach ($mapping as $field => $columnKey) {
                    $product[$field] = isset($row[$columnKey]) ? trim((string)$row[$columnKey]) : '';
                }
                
                // Vérifier qu'au moins le nom est présent
                if (!empty($product['nom'])) {
                    $products[] = $product;
                }
            }

            if (empty($products)) {
                return [
                    'success' => false,
                    'error' => 'Aucun produit valide trouvé dans le fichier'
                ];
            }

            return [
                'success' => true,
                'data' => $products,
                'mapping' => $mapping,
                'totalRows' => count($products)
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erreur lecture fichier Excel', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName()
            ]);
            
            return [
                'success' => false,
                'error' => 'Erreur lors de la lecture du fichier: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Mappe les colonnes du fichier aux champs attendus
     */
    private function mapColumns(array $headers): array
    {
        $mapping = [];
        
        foreach (self::EXPECTED_COLUMNS as $field => $possibleNames) {
            foreach ($headers as $columnKey => $headerValue) {
                if (in_array($headerValue, $possibleNames)) {
                    $mapping[$field] = $columnKey;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Vérifie si une ligne est vide
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (!empty(trim((string)$cell))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Traite les données avec nettoyage AI
     */
    public function processWithAI(array $products, array $existingCategories = []): array
    {
        // Configurer les catégories existantes pour la normalisation
        $this->aiCleaner->setExistingCategories($existingCategories);
        
        // Nettoyer les produits avec l'AI
        $cleanedProducts = $this->aiCleaner->cleanProducts($products);
        
        // Analyser et suggérer des améliorations
        $originalProducts = array_column($cleanedProducts, 'cleaned');
        $suggestions = $this->aiCleaner->analyzeAndSuggest($originalProducts);
        
        // Ajouter les suggestions aux produits
        foreach ($cleanedProducts as $index => &$product) {
            $product['suggestions'] = $suggestions[$index] ?? [];
        }
        
        // Calculer les statistiques
        $stats = $this->calculateStats($cleanedProducts);
        
        return [
            'products' => $cleanedProducts,
            'stats' => $stats
        ];
    }

    /**
     * Traitement sans AI (nettoyage basique)
     */
    public function processBasic(array $products): array
    {
        $cleanedProducts = [];
        
        foreach ($products as $index => $product) {
            $changes = [];
            $cleaned = $product;
            
            // Nettoyer le nom
            $cleanedNom = $this->basicCleanText($product['nom'] ?? '');
            if ($cleanedNom !== ($product['nom'] ?? '')) {
                $changes['nom'] = [
                    'before' => $product['nom'] ?? '',
                    'after' => $cleanedNom,
                    'reason' => 'Nettoyage basique'
                ];
                $cleaned['nom'] = $cleanedNom;
            }
            
            // Nettoyer la description
            $cleanedDesc = $this->basicCleanText($product['description'] ?? '');
            if ($cleanedDesc !== ($product['description'] ?? '')) {
                $changes['description'] = [
                    'before' => $product['description'] ?? '',
                    'after' => $cleanedDesc,
                    'reason' => 'Nettoyage basique'
                ];
                $cleaned['description'] = $cleanedDesc;
            }
            
            // Normaliser le prix
            $normalizedPrix = $this->aiCleaner->normalizePrice($product['prix'] ?? 0);
            if ($normalizedPrix !== (string)($product['prix'] ?? '')) {
                $changes['prix'] = [
                    'before' => $product['prix'] ?? '',
                    'after' => $normalizedPrix,
                    'reason' => 'Normalisation prix'
                ];
                $cleaned['prix'] = $normalizedPrix;
            }
            
            // Normaliser le stock
            $normalizedStock = $this->aiCleaner->normalizeStock($product['stock'] ?? 0);
            $cleaned['stock'] = $normalizedStock;
            
            // Normaliser la catégorie
            $normalizedCat = ucfirst(mb_strtolower(trim($product['categorie'] ?? 'Non classé')));
            if ($normalizedCat !== ($product['categorie'] ?? '')) {
                $changes['categorie'] = [
                    'before' => $product['categorie'] ?? '',
                    'after' => $normalizedCat,
                    'reason' => 'Normalisation catégorie'
                ];
                $cleaned['categorie'] = $normalizedCat;
            }
            
            $cleanedProducts[] = [
                'index' => $index,
                'original' => $product,
                'cleaned' => $cleaned,
                'changes' => $changes,
                'hasChanges' => !empty($changes),
                'suggestions' => []
            ];
        }
        
        return [
            'products' => $cleanedProducts,
            'stats' => $this->calculateStats($cleanedProducts)
        ];
    }

    /**
     * Nettoyage basique d'un texte
     */
    private function basicCleanText(string $text): string
    {
        // Supprimer les espaces multiples
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Supprimer les espaces en début/fin
        $text = trim($text);
        
        // Première lettre en majuscule si c'est une lettre
        if (!empty($text) && ctype_alpha($text[0])) {
            $text = ucfirst($text);
        }
        
        return $text;
    }

    /**
     * Calcule les statistiques du traitement
     */
    private function calculateStats(array $cleanedProducts): array
    {
        $totalProducts = count($cleanedProducts);
        $productsWithChanges = 0;
        $totalChanges = 0;
        $changesByField = [
            'nom' => 0,
            'description' => 0,
            'prix' => 0,
            'stock' => 0,
            'categorie' => 0
        ];
        $errors = 0;
        $warnings = 0;

        foreach ($cleanedProducts as $product) {
            if ($product['hasChanges']) {
                $productsWithChanges++;
            }
            
            $totalChanges += count($product['changes']);
            
            foreach ($product['changes'] as $field => $change) {
                if (isset($changesByField[$field])) {
                    $changesByField[$field]++;
                }
            }
            
            foreach ($product['suggestions'] ?? [] as $suggestion) {
                if ($suggestion['type'] === 'error') {
                    $errors++;
                } elseif ($suggestion['type'] === 'warning') {
                    $warnings++;
                }
            }
        }

        return [
            'totalProducts' => $totalProducts,
            'productsWithChanges' => $productsWithChanges,
            'productsUnchanged' => $totalProducts - $productsWithChanges,
            'totalChanges' => $totalChanges,
            'changesByField' => $changesByField,
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Génère un fichier Excel modèle
     */
    public function generateTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Modèle Produits');

        // En-têtes
        $headers = ['nom', 'description', 'prix', 'stock', 'categorie'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', ucfirst($header));
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        // Style des en-têtes
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4CAF50']
            ]
        ]);

        // Exemples de données
        $examples = [
            ['Vitamine C 1000mg', 'Complément alimentaire vitamine C naturelle', '19.99', '100', 'Vitamines'],
            ['Omega 3 Fish Oil', 'Huile de poisson riche en oméga 3', '24.50', '50', 'Compléments'],
            ['Crème hydratante', 'Crème pour peau sèche', '15.00', '75', 'Cosmétiques'],
        ];

        $row = 2;
        foreach ($examples as $example) {
            $col = 'A';
            foreach ($example as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }

        return $spreadsheet;
    }

    /**
     * Valide un produit avant import final
     */
    public function validateProduct(array $product): array
    {
        $errors = [];

        // Nom obligatoire
        if (empty($product['nom'])) {
            $errors[] = 'Le nom est obligatoire';
        } elseif (strlen($product['nom']) < 3) {
            $errors[] = 'Le nom doit contenir au moins 3 caractères';
        }

        // Description obligatoire
        if (empty($product['description'])) {
            $errors[] = 'La description est obligatoire';
        } elseif (strlen($product['description']) < 10) {
            $errors[] = 'La description doit contenir au moins 10 caractères';
        }

        // Prix valide
        if (!isset($product['prix']) || !is_numeric($product['prix'])) {
            $errors[] = 'Le prix doit être un nombre valide';
        } elseif ((float)$product['prix'] <= 0) {
            $errors[] = 'Le prix doit être supérieur à 0';
        }

        // Stock valide
        if (!isset($product['stock']) || !is_numeric($product['stock'])) {
            $errors[] = 'Le stock doit être un nombre valide';
        } elseif ((int)$product['stock'] < 0) {
            $errors[] = 'Le stock ne peut pas être négatif';
        }

        // Catégorie
        if (empty($product['categorie'])) {
            $errors[] = 'La catégorie est obligatoire';
        }

        return $errors;
    }
}
