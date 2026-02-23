<?php

namespace App\Service;

use App\Entity\Produit;
use App\Entity\ProfilMedical;
use OpenAI;
use OpenAI\Client;
use Psr\Log\LoggerInterface;

/**
 * Service de vérification de compatibilité médicamenteuse avec IA
 */
class DrugCompatibilityService
{
    private ?Client $openaiClient = null;

    // Base de données locale d'interactions connues (fallback si pas d'IA)
    private const KNOWN_INTERACTIONS = [
        'aspirine' => ['ibuprofène', 'anticoagulants', 'warfarine', 'héparine'],
        'ibuprofène' => ['aspirine', 'anticoagulants', 'lithium', 'méthotrexate'],
        'paracétamol' => ['alcool', 'warfarine'],
        'metformine' => ['alcool', 'produits iodés'],
        'insuline' => ['bêtabloquants', 'alcool'],
        'warfarine' => ['aspirine', 'ibuprofène', 'vitamine K', 'ginkgo'],
        'oméprazole' => ['clopidogrel', 'méthotrexate'],
        'simvastatine' => ['pamplemousse', 'érythromycine', 'clarithromycine'],
        'amlodipine' => ['pamplemousse', 'simvastatine'],
        'lisinopril' => ['potassium', 'spironolactone', 'ibuprofène'],
    ];

    private const CONDITION_CONTRAINDICATIONS = [
        'diabète' => ['sucre ajouté', 'sirop glucose', 'maltodextrine'],
        'hypertension' => ['sodium élevé', 'réglisse', 'caféine forte'],
        'insuffisance rénale' => ['potassium', 'phosphore', 'protéines élevées'],
        'insuffisance hépatique' => ['alcool', 'paracétamol haute dose'],
        'grossesse' => ['vitamine A haute dose', 'caféine', 'alcool'],
        'allaitement' => ['alcool', 'caféine forte'],
        'asthme' => ['aspirine', 'ibuprofène', 'bêtabloquants'],
        'ulcère gastrique' => ['aspirine', 'ibuprofène', 'anti-inflammatoires'],
    ];

    private const ALLERGEN_GROUPS = [
        'lactose' => ['lait', 'lactose', 'caséine', 'lactosérum', 'petit-lait'],
        'gluten' => ['blé', 'orge', 'seigle', 'avoine', 'épeautre'],
        'arachide' => ['cacahuète', 'arachide', 'huile d\'arachide'],
        'fruits à coque' => ['noix', 'amande', 'noisette', 'cajou', 'pistache'],
        'soja' => ['soja', 'lécithine de soja', 'protéine de soja'],
        'œuf' => ['œuf', 'albumine', 'ovalbumine'],
        'poisson' => ['poisson', 'huile de poisson', 'gélatine de poisson'],
        'crustacés' => ['crevette', 'crabe', 'homard', 'langoustine'],
    ];

    public function __construct(
        private readonly string $openaiApiKey,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Initialise le client OpenAI
     */
    private function getOpenAIClient(): ?Client
    {
        if ($this->openaiClient === null && !empty($this->openaiApiKey) && $this->openaiApiKey !== 'your_openai_api_key_here') {
            $this->openaiClient = OpenAI::client($this->openaiApiKey);
        }
        return $this->openaiClient;
    }

    /**
     * Analyse complète de compatibilité d'un produit avec un profil médical
     */
    public function analyzeCompatibility(Produit $produit, ProfilMedical $profil): array
    {
        $result = [
            'compatible' => true,
            'score' => 100,
            'alertes' => [],
            'warnings' => [],
            'recommendations' => [],
            'analysisSource' => 'local'
        ];

        // 1. Vérification des contre-indications (conditions chroniques)
        $contraindications = $this->checkContraindications($produit, $profil);
        if (!empty($contraindications)) {
            $result['compatible'] = false;
            $result['score'] -= 40;
            $result['alertes'] = array_merge($result['alertes'], $contraindications);
        }

        // 2. Vérification des allergènes
        $allergies = $this->checkAllergies($produit, $profil);
        if (!empty($allergies)) {
            $result['compatible'] = false;
            $result['score'] -= 50;
            $result['alertes'] = array_merge($result['alertes'], $allergies);
        }

        // 3. Vérification des interactions médicamenteuses
        $interactions = $this->checkDrugInteractions($produit, $profil);
        if (!empty($interactions['critical'])) {
            $result['compatible'] = false;
            $result['score'] -= 30;
            $result['alertes'] = array_merge($result['alertes'], $interactions['critical']);
        }
        if (!empty($interactions['moderate'])) {
            $result['score'] -= 15;
            $result['warnings'] = array_merge($result['warnings'], $interactions['moderate']);
        }

        // 4. Analyse IA approfondie si disponible
        if ($this->getOpenAIClient() && ($result['compatible'] === false || !empty($result['warnings']))) {
            $aiAnalysis = $this->getAIAnalysis($produit, $profil);
            if ($aiAnalysis) {
                $result['analysisSource'] = 'ai';
                $result['recommendations'] = $aiAnalysis['recommendations'] ?? [];
                if (!empty($aiAnalysis['additional_warnings'])) {
                    $result['warnings'] = array_merge($result['warnings'], $aiAnalysis['additional_warnings']);
                }
            }
        }

        $result['score'] = max(0, $result['score']);

        return $result;
    }

    /**
     * Vérifie les contre-indications basées sur les conditions chroniques
     */
    private function checkContraindications(Produit $produit, ProfilMedical $profil): array
    {
        $alertes = [];
        $conditions = $profil->getConditionsChroniques();
        $produitCI = array_map('strtolower', $produit->getContreIndications());
        $produitIngredients = array_map('strtolower', $produit->getIngredientsActifs());

        foreach ($conditions as $condition) {
            $conditionLower = strtolower($condition);

            // Vérifier les CI déclarées sur le produit
            if (in_array($conditionLower, $produitCI)) {
                $alertes[] = [
                    'type' => 'contre-indication',
                    'severity' => 'danger',
                    'title' => 'Contre-indication médicale',
                    'message' => "Ce produit est contre-indiqué pour les personnes atteintes de {$condition}.",
                    'action' => 'Ne pas utiliser ce produit sans avis médical.'
                ];
            }

            // Vérifier les CI connues basées sur les ingrédients
            if (isset(self::CONDITION_CONTRAINDICATIONS[$conditionLower])) {
                foreach (self::CONDITION_CONTRAINDICATIONS[$conditionLower] as $ciIngredient) {
                    foreach ($produitIngredients as $ingredient) {
                        if (str_contains($ingredient, strtolower($ciIngredient))) {
                            $alertes[] = [
                                'type' => 'contre-indication',
                                'severity' => 'warning',
                                'title' => 'Ingrédient à éviter',
                                'message' => "Ce produit contient '{$ingredient}' qui peut être problématique pour les personnes atteintes de {$condition}.",
                                'action' => 'Consultez votre médecin avant utilisation.'
                            ];
                        }
                    }
                }
            }
        }

        return $alertes;
    }

    /**
     * Vérifie les allergènes présents dans le produit
     */
    private function checkAllergies(Produit $produit, ProfilMedical $profil): array
    {
        $alertes = [];
        $allergiesPatient = array_map('strtolower', $profil->getAllergies());
        $allergenesProduit = array_map('strtolower', $produit->getAllergenes());
        $ingredientsProduit = array_map('strtolower', $produit->getIngredientsActifs());

        foreach ($allergiesPatient as $allergie) {
            // Vérification directe
            if (in_array($allergie, $allergenesProduit)) {
                $alertes[] = [
                    'type' => 'allergie',
                    'severity' => 'danger',
                    'title' => 'Allergène détecté',
                    'message' => "Ce produit contient '{$allergie}' auquel vous êtes allergique.",
                    'action' => 'NE PAS UTILISER ce produit.'
                ];
                continue;
            }

            // Vérification par groupe d'allergènes
            foreach (self::ALLERGEN_GROUPS as $group => $members) {
                if ($allergie === $group || in_array($allergie, $members)) {
                    foreach ($members as $member) {
                        if (in_array($member, $allergenesProduit) || in_array($member, $ingredientsProduit)) {
                            $alertes[] = [
                                'type' => 'allergie',
                                'severity' => 'danger',
                                'title' => 'Allergène apparenté détecté',
                                'message' => "Ce produit contient '{$member}' (groupe: {$group}) auquel vous pourriez être sensible.",
                                'action' => 'Évitez ce produit par précaution.'
                            ];
                        }
                    }
                }
            }
        }

        return $alertes;
    }

    /**
     * Vérifie les interactions médicamenteuses
     */
    private function checkDrugInteractions(Produit $produit, ProfilMedical $profil): array
    {
        $result = [
            'critical' => [],
            'moderate' => []
        ];

        $medicaments = array_map('strtolower', $profil->getMedicamentsActuels());
        $interactionsProduit = array_map('strtolower', $produit->getInteractions());
        $ingredientsProduit = array_map('strtolower', $produit->getIngredientsActifs());

        foreach ($medicaments as $medicament) {
            // Vérifier les interactions déclarées sur le produit
            if (in_array($medicament, $interactionsProduit)) {
                $result['critical'][] = [
                    'type' => 'interaction',
                    'severity' => 'danger',
                    'title' => 'Interaction médicamenteuse',
                    'message' => "Ce produit est déconseillé avec {$medicament}.",
                    'action' => 'Consultez votre médecin ou pharmacien.'
                ];
                continue;
            }

            // Vérifier les interactions connues
            if (isset(self::KNOWN_INTERACTIONS[$medicament])) {
                foreach (self::KNOWN_INTERACTIONS[$medicament] as $interaction) {
                    foreach ($ingredientsProduit as $ingredient) {
                        if (str_contains($ingredient, $interaction) || str_contains($interaction, $ingredient)) {
                            $result['moderate'][] = [
                                'type' => 'interaction',
                                'severity' => 'warning',
                                'title' => 'Interaction potentielle',
                                'message' => "L'ingrédient '{$ingredient}' peut interagir avec {$medicament}.",
                                'action' => 'Demandez conseil à votre pharmacien.'
                            ];
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Analyse approfondie avec l'IA
     */
    private function getAIAnalysis(Produit $produit, ProfilMedical $profil): ?array
    {
        try {
            $client = $this->getOpenAIClient();
            if (!$client) {
                return null;
            }

            $prompt = $this->buildAnalysisPrompt($produit, $profil);

            $response = $client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un pharmacien expert en interactions médicamenteuses et compatibilité des produits de santé. Analyse les risques et donne des recommandations pratiques. Réponds en JSON.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.2,
                'max_tokens' => 800
            ]);

            $content = $response->choices[0]->message->content;
            return $this->parseAIResponse($content);

        } catch (\Exception $e) {
            $this->logger->error('Erreur analyse IA compatibilité', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Construit le prompt d'analyse
     */
    private function buildAnalysisPrompt(Produit $produit, ProfilMedical $profil): string
    {
        return <<<PROMPT
Analyse la compatibilité de ce produit avec le profil médical du patient.

PRODUIT:
- Nom: {$produit->getNom()}
- Ingrédients actifs: {$this->arrayToString($produit->getIngredientsActifs())}
- Contre-indications déclarées: {$this->arrayToString($produit->getContreIndications())}
- Interactions connues: {$this->arrayToString($produit->getInteractions())}
- Allergènes: {$this->arrayToString($produit->getAllergenes())}

PROFIL PATIENT:
- Conditions chroniques: {$this->arrayToString($profil->getConditionsChroniques())}
- Allergies: {$this->arrayToString($profil->getAllergies())}
- Médicaments actuels: {$this->arrayToString($profil->getMedicamentsActuels())}
- Objectifs santé: {$this->arrayToString($profil->getObjectifsSante())}

Réponds UNIQUEMENT en JSON:
{
  "additional_warnings": ["<avertissement supplémentaire si nécessaire>"],
  "recommendations": ["<conseil pratique d'utilisation>"],
  "safe_alternatives": ["<suggestion d'alternative si problème>"]
}
PROMPT;
    }

    /**
     * Parse la réponse IA
     */
    private function parseAIResponse(string $response): ?array
    {
        try {
            $response = trim($response);
            if (str_starts_with($response, '```')) {
                $response = preg_replace('/^```(json)?|```$/m', '', $response);
            }
            return json_decode(trim($response), true);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Convertit un tableau en chaîne
     */
    private function arrayToString(array $arr): string
    {
        return empty($arr) ? 'Aucun' : implode(', ', $arr);
    }

    /**
     * Vérifie rapidement si un produit est compatible (sans analyse complète)
     */
    public function quickCheck(Produit $produit, ProfilMedical $profil): bool
    {
        // Vérification rapide des CI et allergies
        foreach ($profil->getConditionsChroniques() as $condition) {
            if ($produit->hasContreIndication($condition)) {
                return false;
            }
        }

        foreach ($profil->getAllergies() as $allergie) {
            if ($produit->hasAllergene($allergie)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtient un résumé des alertes pour affichage rapide
     */
    public function getAlertSummary(array $analysis): array
    {
        $summary = [
            'status' => 'ok',
            'icon' => '✓',
            'color' => 'success',
            'message' => 'Produit compatible avec votre profil'
        ];

        if (!$analysis['compatible']) {
            $summary = [
                'status' => 'danger',
                'icon' => '⚠️',
                'color' => 'danger',
                'message' => 'Attention: incompatibilité détectée'
            ];
        } elseif (!empty($analysis['warnings'])) {
            $summary = [
                'status' => 'warning',
                'icon' => '⚡',
                'color' => 'warning',
                'message' => 'Précautions à prendre'
            ];
        }

        $summary['alertCount'] = count($analysis['alertes'] ?? []);
        $summary['warningCount'] = count($analysis['warnings'] ?? []);

        return $summary;
    }
}
