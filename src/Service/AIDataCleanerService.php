<?php

namespace App\Service;

use OpenAI;
use Psr\Log\LoggerInterface;

/**
 * Service pour nettoyer et corriger les données produits avec l'IA OpenAI
 */
class AIDataCleanerService
{
    private ?object $client = null;
    /**
     * @var string[]
     */
    private array $existingCategories = [];
    
    public function __construct(
        private string $openaiApiKey,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Initialise le client OpenAI
     */
    private function getClient(): object
    {
        if ($this->client === null) {
            $this->client = OpenAI::client($this->openaiApiKey);
        }
        return $this->client;
    }

    /**
     * Définir les catégories existantes pour la normalisation
     */
    /**
     * @param string[] $categories
     */
    public function setExistingCategories(array $categories): void
    {
        $this->existingCategories = $categories;
    }

    /**
     * Nettoie et corrige un lot de produits
     * @param array $products Tableau de produits bruts
     * @return array Produits nettoyés avec détails des modifications
     */
    /**
     * @param array<int, array<string, mixed>> $products
     * @return array<int, array<string, mixed>>
     */
    public function cleanProducts(array $products): array
    {
        $cleanedProducts = [];
        
        foreach ($products as $index => $product) {
            try {
                $cleanedProducts[] = $this->cleanSingleProduct($product, $index);
            } catch (\Exception $e) {
                $this->logger->error('Erreur nettoyage produit', [
                    'index' => $index,
                    'error' => $e->getMessage()
                ]);
                // Retourner le produit original avec une erreur
                $cleanedProducts[] = [
                    'original' => $product,
                    'cleaned' => $product,
                    'changes' => [],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $cleanedProducts;
    }

    /**
     * Nettoie un seul produit
     */
    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function cleanSingleProduct(array $product, int $index): array
    {
        $changes = [];
        $cleaned = $product;
        
        // 1. Nettoyer le nom
        if (!empty($product['nom'])) {
            $cleanedNom = $this->correctSpelling($product['nom'], 'nom de produit');
            if ($cleanedNom !== $product['nom']) {
                $changes['nom'] = [
                    'before' => $product['nom'],
                    'after' => $cleanedNom,
                    'reason' => 'Correction orthographique'
                ];
                $cleaned['nom'] = $cleanedNom;
            }
        }
        
        // 2. Nettoyer la description
        if (!empty($product['description'])) {
            $cleanedDesc = $this->correctSpelling($product['description'], 'description produit');
            if ($cleanedDesc !== $product['description']) {
                $changes['description'] = [
                    'before' => $product['description'],
                    'after' => $cleanedDesc,
                    'reason' => 'Correction orthographique'
                ];
                $cleaned['description'] = $cleanedDesc;
            }
        }
        
        // 3. Normaliser le prix
        if (isset($product['prix'])) {
            $normalizedPrix = $this->normalizePrice($product['prix']);
            if ((string)$normalizedPrix !== (string)$product['prix']) {
                $changes['prix'] = [
                    'before' => $product['prix'],
                    'after' => $normalizedPrix,
                    'reason' => 'Normalisation format prix'
                ];
                $cleaned['prix'] = $normalizedPrix;
            }
        }
        
        // 4. Normaliser la catégorie
        if (!empty($product['categorie'])) {
            $normalizedCat = $this->normalizeCategory($product['categorie']);
            if ($normalizedCat !== $product['categorie']) {
                $changes['categorie'] = [
                    'before' => $product['categorie'],
                    'after' => $normalizedCat,
                    'reason' => 'Normalisation catégorie'
                ];
                $cleaned['categorie'] = $normalizedCat;
            }
        }
        
        // 5. Normaliser le stock
        if (isset($product['stock'])) {
            $normalizedStock = $this->normalizeStock($product['stock']);
            if ((int)$normalizedStock !== (int)$product['stock']) {
                $changes['stock'] = [
                    'before' => $product['stock'],
                    'after' => $normalizedStock,
                    'reason' => 'Normalisation stock'
                ];
                $cleaned['stock'] = $normalizedStock;
            }
        }
        
        return [
            'index' => $index,
            'original' => $product,
            'cleaned' => $cleaned,
            'changes' => $changes,
            'hasChanges' => !empty($changes)
        ];
    }

    /**
     * Corrige l'orthographe d'un texte avec OpenAI
     */
    public function correctSpelling(string $text, string $context = 'texte'): string
    {
        if (empty(trim($text))) {
            return $text;
        }

        try {
            $response = $this->getClient()->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Tu es un correcteur orthographique expert en français pour des produits de santé et bien-être (compléments alimentaires, vitamines, équipements médicaux). 
                        
Règles:
- Corrige UNIQUEMENT les fautes d'orthographe et de grammaire
- Conserve la mise en forme originale (majuscules si présentes)
- Ne modifie PAS les noms de marques, dosages ou unités
- Retourne UNIQUEMENT le texte corrigé, sans explication
- Si le texte est déjà correct, retourne-le tel quel"
                    ],
                    [
                        'role' => 'user',
                        'content' => "Corrige ce {$context}: \"{$text}\""
                    ]
                ],
                'temperature' => 0.1,
                'max_tokens' => 500
            ]);

            $corrected = $response->choices[0]->message->content ?? $text;
            
            // Nettoyer les guillemets ajoutés par GPT
            $corrected = trim($corrected, '"\'');
            
            return $corrected;
        } catch (\Exception $e) {
            $this->logger->warning('Erreur correction orthographe OpenAI', [
                'error' => $e->getMessage(),
                'text' => substr($text, 0, 100)
            ]);
            return $text;
        }
    }

    /**
     * Normalise un prix en format décimal standard
     */
    public function normalizePrice(mixed $price): string
    {
        if (is_numeric($price)) {
            return number_format((float)$price, 2, '.', '');
        }

        $priceStr = (string)$price;
        
        // Supprimer les symboles de devise et espaces
        $priceStr = preg_replace('/[€$£\s]/', '', $priceStr);
        $priceStr = preg_replace('/EUR|USD|GBP/i', '', $priceStr);
        
        // Convertir virgule en point
        $priceStr = str_replace(',', '.', $priceStr);
        
        // Supprimer les points multiples (garder le dernier comme séparateur décimal)
        if (substr_count($priceStr, '.') > 1) {
            $parts = explode('.', $priceStr);
            $lastPart = array_pop($parts);
            $priceStr = implode('', $parts) . '.' . $lastPart;
        }

        if (is_numeric($priceStr)) {
            return number_format((float)$priceStr, 2, '.', '');
        }

        return '0.00';
    }

    /**
     * Normalise une catégorie
     */
    public function normalizeCategory(string $category): string
    {
        $category = trim($category);
        
        // Si on a des catégories existantes, essayer de faire correspondre
        if (!empty($this->existingCategories)) {
            $normalized = $this->findMatchingCategory($category);
            if ($normalized) {
                return $normalized;
            }
        }
        
        // Normalisation par défaut: première lettre majuscule
        return ucfirst(mb_strtolower($category));
    }

    /**
     * Trouve une catégorie existante correspondante
     */
    private function findMatchingCategory(string $input): ?string
    {
        $inputLower = mb_strtolower(trim($input));
        
        foreach ($this->existingCategories as $existing) {
            if (mb_strtolower($existing) === $inputLower) {
                return $existing;
            }
            
            // Correspondance partielle (similitude > 80%)
            similar_text($inputLower, mb_strtolower($existing), $percent);
            if ($percent > 80) {
                return $existing;
            }
        }
        
        return null;
    }

    /**
     * Normalise le stock en entier positif
     */
    public function normalizeStock(mixed $stock): int
    {
        if (is_numeric($stock)) {
            return max(0, (int)$stock);
        }
        
        // Extraire les chiffres
        $stockStr = preg_replace('/[^0-9]/', '', (string)$stock);
        
        return $stockStr !== '' ? (int)$stockStr : 0;
    }

    /**
     * Analyse un lot de produits et suggère des améliorations
     */
    /**
     * @param array<int, array<string, mixed>> $products
     * @return array<int, array<string, mixed>>
     */
    public function analyzeAndSuggest(array $products): array
    {
        $suggestions = [];
        
        foreach ($products as $index => $product) {
            $productSuggestions = [];
            
            // Vérifier les descriptions courtes
            if (!empty($product['description']) && strlen($product['description']) < 50) {
                $productSuggestions[] = [
                    'type' => 'warning',
                    'field' => 'description',
                    'message' => 'Description très courte, considérez l\'enrichir'
                ];
            }
            
            // Vérifier les prix suspects
            if (isset($product['prix'])) {
                $prix = $this->normalizePrice($product['prix']);
                if ((float)$prix === 0.0) {
                    $productSuggestions[] = [
                        'type' => 'error',
                        'field' => 'prix',
                        'message' => 'Prix invalide ou égal à 0'
                    ];
                } elseif ((float)$prix > 1000) {
                    $productSuggestions[] = [
                        'type' => 'warning',
                        'field' => 'prix',
                        'message' => 'Prix élevé, veuillez vérifier'
                    ];
                }
            }
            
            // Vérifier le stock
            if (isset($product['stock'])) {
                $stock = $this->normalizeStock($product['stock']);
                if ($stock === 0) {
                    $productSuggestions[] = [
                        'type' => 'info',
                        'field' => 'stock',
                        'message' => 'Stock à 0, produit sera indisponible'
                    ];
                }
            }
            
            if (!empty($productSuggestions)) {
                $suggestions[$index] = $productSuggestions;
            }
        }
        
        return $suggestions;
    }

    /**
     * Vérifie si l'API OpenAI est configurée et accessible
     */
    public function isConfigured(): bool
    {
        return !empty($this->openaiApiKey) && $this->openaiApiKey !== 'your_openai_api_key_here';
    }

    /**
     * Teste la connexion à l'API OpenAI
     */
    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Clé API OpenAI non configurée'
            ];
        }

        try {
            $response = $this->getClient()->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => 'Test']
                ],
                'max_tokens' => 5
            ]);

            return [
                'success' => true,
                'message' => 'Connexion OpenAI réussie'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur connexion: ' . $e->getMessage()
            ];
        }
    }
}
