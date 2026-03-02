<?php

namespace App\Service;

use App\Entity\Produit;
use App\Entity\ProfilMedical;
use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\LigneCommandeRepository;
use App\Repository\ProduitRepository;
use App\Repository\ProfilMedicalRepository;
use OpenAI;
use OpenAI\Client;
use Psr\Log\LoggerInterface;

/**
 * Service de recommandations personnalisées avec IA
 */
class ProductRecommendationService
{
    private ?Client $openaiClient = null;

    public function __construct(
        private readonly string $openaiApiKey,
        private readonly ProduitRepository $produitRepository,
        private readonly CommandeRepository $commandeRepository,
        private readonly ProfilMedicalRepository $profilMedicalRepository,
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
     * Vérifie si l'IA est disponible
     */
    public function isAIAvailable(): bool
    {
        return !empty($this->openaiApiKey) && $this->openaiApiKey !== 'your_openai_api_key_here';
    }

    /**
     * Obtient des recommandations personnalisées pour un utilisateur
     * 
     * @param User $user L'utilisateur
     * @param int $limit Nombre de produits à recommander
     * @return array ['produits' => Produit[], 'raisons' => string[], 'alertes' => string[]]
     */
    public function getPersonalizedRecommendations(User $user, int $limit = 6): array
    {
        $result = [
            'produits' => [],
            'raisons' => [],
            'alertes' => [],
            'source' => 'basic'
        ];

        $profilMedical = $user->getProfilMedical();
        $historiqueAchats = $this->getHistoriqueAchats($user);

        // Récupère tous les produits actifs
        $produitsDisponibles = $this->produitRepository->findBy(['isActive' => true]);

        if ($this->isAIAvailable() && $profilMedical) {
            // Recommandations basées sur l'IA
            return $this->getAIRecommendations($user, $profilMedical, $produitsDisponibles, $historiqueAchats, $limit);
        }

        // Fallback: recommandations basiques
        return $this->getBasicRecommendations($user, $profilMedical, $produitsDisponibles, $historiqueAchats, $limit);
    }

    /**
     * Recommandations basées sur l'IA OpenAI
     */
    private function getAIRecommendations(
        User $user,
        ProfilMedical $profil,
        array $produits,
        array $historiqueAchats,
        int $limit
    ): array {
        $result = [
            'produits' => [],
            'raisons' => [],
            'alertes' => [],
            'source' => 'ai'
        ];

        try {
            $client = $this->getOpenAIClient();
            if (!$client) {
                return $this->getBasicRecommendations($user, $profil, $produits, $historiqueAchats, $limit);
            }

            // Prépare le contexte pour l'IA
            $profilSummary = $profil->toSummaryString();
            $produitsInfo = $this->formatProduitsForAI($produits);
            $historiqueInfo = $this->formatHistoriqueForAI($historiqueAchats);

            $prompt = $this->buildRecommendationPrompt($profilSummary, $produitsInfo, $historiqueInfo, $limit);

            $response = $client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un assistant santé spécialisé dans les recommandations de produits pour patients atteints de maladies chroniques. Tu dois recommander des produits adaptés au profil médical du patient et identifier les incompatibilités potentielles. Réponds uniquement en JSON valide.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => 1500
            ]);

            $aiResponse = $response->choices[0]->message->content;
            $parsed = $this->parseAIResponse($aiResponse, $produits);

            if (!empty($parsed['produits'])) {
                $result = $parsed;
                $result['source'] = 'ai';
            }

            $this->logger->info('Recommandations IA générées', [
                'user_id' => $user->getId(),
                'count' => count($result['produits'])
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur recommandations IA', [
                'error' => $e->getMessage()
            ]);
            return $this->getBasicRecommendations($user, $profil, $produits, $historiqueAchats, $limit);
        }

        // Fallback si aucun produit recommandé par l'IA
        if (empty($result['produits'])) {
            return $this->getBasicRecommendations($user, $profil, $produits, $historiqueAchats, $limit);
        }

        return $result;
    }

    /**
     * Recommandations basiques (sans IA)
     */
    private function getBasicRecommendations(
        User $user,
        ?ProfilMedical $profil,
        array $produits,
        array $historiqueAchats,
        int $limit
    ): array {
        $result = [
            'produits' => [],
            'raisons' => [],
            'alertes' => [],
            'source' => 'basic'
        ];

        $scores = [];
        $produitsAchetes = array_column($historiqueAchats, 'produit_id');

        foreach ($produits as $produit) {
            // Exclure les produits déjà achetés récemment
            if (in_array($produit->getId(), $produitsAchetes)) {
                continue;
            }

            // Score de base pour tous les produits actifs
            $score = 10;
            $raison = [];

            // Bonus basé sur la popularité
            $score += $produit->getPopularite() * 0.5;

            if ($profil) {
                // Vérifier les contre-indications
                foreach ($profil->getConditionsChroniques() as $condition) {
                    if ($produit->hasContreIndication($condition)) {
                        $result['alertes'][] = "⚠️ {$produit->getNom()} est contre-indiqué pour {$condition}";
                        $score -= 100; // Pénalité forte
                    }
                }

                // Vérifier les allergènes
                foreach ($profil->getAllergies() as $allergie) {
                    if ($produit->hasAllergene($allergie)) {
                        $result['alertes'][] = "⚠️ {$produit->getNom()} contient {$allergie} (allergène)";
                        $score -= 100;
                    }
                }

                // Vérifier les interactions médicamenteuses
                foreach ($profil->getMedicamentsActuels() as $medicament) {
                    if ($produit->hasInteraction($medicament)) {
                        $result['alertes'][] = "⚠️ {$produit->getNom()} peut interagir avec {$medicament}";
                        $score -= 50;
                    }
                }

                // Bonus pour objectifs correspondants
                foreach ($profil->getObjectifsSante() as $objectif) {
                    if ($produit->matchesObjectif($objectif)) {
                        $score += 20;
                        $raison[] = "Correspond à votre objectif: {$objectif}";
                    }
                }
            }

            // Inclure les produits avec score positif (non contre-indiqués)
            if ($score > 0) {
                $scores[$produit->getId()] = [
                    'produit' => $produit,
                    'score' => $score,
                    'raison' => implode(', ', $raison) ?: 'Produit adapté à votre profil'
                ];
            }
        }

        // Trier par score décroissant
        uasort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

        // Prendre les meilleurs
        $count = 0;
        foreach ($scores as $data) {
            if ($count >= $limit) break;
            $result['produits'][] = $data['produit'];
            $result['raisons'][$data['produit']->getId()] = $data['raison'];
            $count++;
        }

        return $result;
    }

    /**
     * Obtient les produits similaires achetés par d'autres patients
     * "Patients comme vous ont aussi acheté..."
     */
    public function getSimilarPatientsPurchases(User $user, int $limit = 4): array
    {
        $result = [
            'produits' => [],
            'message' => ''
        ];

        $profil = $user->getProfilMedical();
        if (!$profil) {
            return $result;
        }

        try {
            // Trouver des patients avec des profils similaires
            $similarPatients = $this->findSimilarPatients($profil, $user);
            
            if (empty($similarPatients)) {
                return $result;
            }

            // Récupérer leurs achats
            $purchaseCounts = [];
            $userPurchases = $this->getHistoriqueAchats($user);
            $userProductIds = array_column($userPurchases, 'produit_id');

            foreach ($similarPatients as $patient) {
                $patientPurchases = $this->getHistoriqueAchats($patient);
                foreach ($patientPurchases as $purchase) {
                    $productId = $purchase['produit_id'];
                    // Exclure les produits déjà achetés par l'utilisateur actuel
                    if (!in_array($productId, $userProductIds)) {
                        $purchaseCounts[$productId] = ($purchaseCounts[$productId] ?? 0) + 1;
                    }
                }
            }

            // Trier par fréquence d'achat
            arsort($purchaseCounts);
            $topProductIds = array_slice(array_keys($purchaseCounts), 0, $limit);

            foreach ($topProductIds as $productId) {
                $produit = $this->produitRepository->find($productId);
                if ($produit && $produit->isActive()) {
                    // Vérifier compatibilité avec le profil
                    $compatible = $this->checkProductCompatibility($produit, $profil);
                    if ($compatible['compatible']) {
                        $result['produits'][] = $produit;
                    }
                }
            }
            

            $patientCount = count($similarPatients);
            $result['message'] = "{$patientCount} patient(s) avec un profil similaire ont aussi acheté ces produits";

        } catch (\Exception $e) {
            $this->logger->error('Erreur récupération achats similaires', [
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    /**
     * Vérifie la compatibilité d'un produit avec un profil médical
     */
    public function checkProductCompatibility(Produit $produit, ?ProfilMedical $profil): array
    {
        $result = [
            'compatible' => true,
            'alertes' => [],
            'warnings' => []
        ];

        if (!$profil) {
            return $result;
        }

        // Vérifier contre-indications
        foreach ($profil->getConditionsChroniques() as $condition) {
            if ($produit->hasContreIndication($condition)) {
                $result['compatible'] = false;
                $result['alertes'][] = [
                    'type' => 'contre-indication',
                    'message' => "Ce produit est contre-indiqué pour les personnes atteintes de {$condition}",
                    'severity' => 'danger'
                ];
            }
        }

        // Vérifier allergènes
        foreach ($profil->getAllergies() as $allergie) {
            if ($produit->hasAllergene($allergie)) {
                $result['compatible'] = false;
                $result['alertes'][] = [
                    'type' => 'allergie',
                    'message' => "Ce produit contient {$allergie}, auquel vous êtes allergique",
                    'severity' => 'danger'
                ];
            }
        }

        // Vérifier interactions médicamenteuses
        foreach ($profil->getMedicamentsActuels() as $medicament) {
            if ($produit->hasInteraction($medicament)) {
                $result['warnings'][] = [
                    'type' => 'interaction',
                    'message' => "Ce produit peut interagir avec {$medicament}. Consultez votre médecin.",
                    'severity' => 'warning'
                ];
            }
        }

        return $result;
    }

    /**
     * Vérifie les incompatibilités d'un panier complet
     */
    public function checkCartCompatibility(array $produits, ?ProfilMedical $profil): array
    {
        $result = [
            'compatible' => true,
            'alertes' => [],
            'interactions_panier' => []
        ];

        if (!$profil) {
            return $result;
        }

        // Vérifier chaque produit
        foreach ($produits as $produit) {
            $check = $this->checkProductCompatibility($produit, $profil);
            if (!$check['compatible']) {
                $result['compatible'] = false;
            }
            $result['alertes'] = array_merge($result['alertes'], $check['alertes'], $check['warnings']);
        }

        // Vérifier les interactions entre produits du panier
        for ($i = 0; $i < count($produits); $i++) {
            for ($j = $i + 1; $j < count($produits); $j++) {
                $interaction = $this->checkProductInteraction($produits[$i], $produits[$j]);
                if ($interaction) {
                    $result['interactions_panier'][] = $interaction;
                }
            }
        }

        return $result;
    }

    /**
     * Vérifie l'interaction entre deux produits
     */
    private function checkProductInteraction(Produit $p1, Produit $p2): ?array
    {
        $ingredients1 = $p1->getIngredientsActifs();
        $interactions2 = $p2->getInteractions();

        foreach ($ingredients1 as $ingredient) {
            if (in_array(strtolower($ingredient), array_map('strtolower', $interactions2))) {
                return [
                    'produit1' => $p1->getNom(),
                    'produit2' => $p2->getNom(),
                    'message' => "{$p1->getNom()} contient {$ingredient} qui peut interagir avec {$p2->getNom()}"
                ];
            }
        }

        return null;
    }

    /**
     * Trouve des patients avec des profils médicaux similaires
     */
    private function findSimilarPatients(ProfilMedical $profil, User $excludeUser): array
    {
        $similarPatients = [];
        $allProfiles = $this->profilMedicalRepository->findAll();

        foreach ($allProfiles as $otherProfile) {
            if ($otherProfile->getUtilisateur()->getId() === $excludeUser->getId()) {
                continue;
            }

            $similarity = $this->calculateProfileSimilarity($profil, $otherProfile);
            if ($similarity > 0.3) { // Seuil de similarité 30%
                $similarPatients[] = $otherProfile->getUtilisateur();
            }
        }

        return $similarPatients;
    }

    /**
     * Calcule la similarité entre deux profils médicaux
     */
    private function calculateProfileSimilarity(ProfilMedical $p1, ProfilMedical $p2): float
    {
        $score = 0;
        $total = 0;

        // Comparer les conditions chroniques
        $conditions1 = $p1->getConditionsChroniques();
        $conditions2 = $p2->getConditionsChroniques();
        if (!empty($conditions1) && !empty($conditions2)) {
            $common = count(array_intersect($conditions1, $conditions2));
            $union = count(array_unique(array_merge($conditions1, $conditions2)));
            $score += $union > 0 ? ($common / $union) * 3 : 0; // Poids x3
            $total += 3;
        }

        // Comparer les objectifs santé
        $objectifs1 = $p1->getObjectifsSante();
        $objectifs2 = $p2->getObjectifsSante();
        if (!empty($objectifs1) && !empty($objectifs2)) {
            $common = count(array_intersect($objectifs1, $objectifs2));
            $union = count(array_unique(array_merge($objectifs1, $objectifs2)));
            $score += $union > 0 ? ($common / $union) * 2 : 0; // Poids x2
            $total += 2;
        }

        // Comparer niveau activité
        if ($p1->getNiveauActivite() === $p2->getNiveauActivite()) {
            $score += 1;
        }
        $total += 1;

        // Comparer tranche d'âge
        if ($p1->getTrancheAge() === $p2->getTrancheAge()) {
            $score += 1;
        }
        $total += 1;

        return $total > 0 ? $score / $total : 0;
    }

    /**
     * Récupère l'historique des achats d'un utilisateur
     */
    private function getHistoriqueAchats(User $user): array
    {
        $commandes = $this->commandeRepository->findBy(
            ['utilisateur' => $user, 'statut' => ['validee', 'livree']],
            ['createdAt' => 'DESC'],
            20 // Dernières 20 commandes
        );

        $achats = [];
        foreach ($commandes as $commande) {
            foreach ($commande->getLigneCommandes() as $ligne) {
                $achats[] = [
                    'produit_id' => $ligne->getProduit()->getId(),
                    'produit_nom' => $ligne->getProduit()->getNom(),
                    'quantite' => $ligne->getQuantite(),
                    'date' => $commande->getCreatedAt()
                ];
            }
        }

        return $achats;
    }

    /**
     * Formate les produits pour l'IA
     */
    private function formatProduitsForAI(array $produits): string
    {
        $lines = [];
        foreach (array_slice($produits, 0, 30) as $produit) { // Limite à 30 produits
            $lines[] = sprintf(
                "ID:%d | %s | Cat:%s | Prix:%.2f€ | Objectifs:[%s] | CI:[%s]",
                $produit->getId(),
                $produit->getNom(),
                $produit->getCategorie(),
                (float)$produit->getPrix(),
                implode(',', $produit->getObjectifsAssocies()),
                implode(',', $produit->getContreIndications())
            );
        }
        return implode("\n", $lines);
    }

    /**
     * Formate l'historique pour l'IA
     */
    private function formatHistoriqueForAI(array $historique): string
    {
        if (empty($historique)) {
            return "Aucun achat précédent";
        }

        $lines = [];
        foreach (array_slice($historique, 0, 10) as $achat) {
            $lines[] = $achat['produit_nom'];
        }
        return "Achats récents: " . implode(', ', array_unique($lines));
    }

    /**
     * Construit le prompt pour les recommandations IA
     */
    private function buildRecommendationPrompt(string $profil, string $produits, string $historique, int $limit): string
    {
        return <<<PROMPT
Analyse le profil médical suivant et recommande {$limit} produits adaptés.

PROFIL MÉDICAL DU PATIENT:
{$profil}

HISTORIQUE D'ACHATS:
{$historique}

CATALOGUE PRODUITS DISPONIBLES:
{$produits}

INSTRUCTIONS:
1. Recommande des produits compatibles avec le profil médical
2. Évite les produits avec contre-indications liées aux conditions du patient
3. Privilégie les produits qui correspondent aux objectifs santé du patient
4. Signale toute alerte ou incompatibilité détectée

Réponds UNIQUEMENT avec ce JSON (sans texte avant ou après):
{
  "recommandations": [
    {"id": <product_id>, "raison": "<explication courte>"}
  ],
  "alertes": ["<message d'alerte si nécessaire>"]
}
PROMPT;
    }

    /**
     * Parse la réponse de l'IA
     */
    private function parseAIResponse(string $response, array $produits): array
    {
        $result = [
            'produits' => [],
            'raisons' => [],
            'alertes' => []
        ];

        try {
            // Nettoyer la réponse
            $response = trim($response);
            if (str_starts_with($response, '```json')) {
                $response = substr($response, 7);
            }
            if (str_starts_with($response, '```')) {
                $response = substr($response, 3);
            }
            if (str_ends_with($response, '```')) {
                $response = substr($response, 0, -3);
            }

            $data = json_decode($response, true);

            if (!$data) {
                throw new \Exception('JSON invalide');
            }

            // Mapper les IDs aux produits
            $produitsById = [];
            foreach ($produits as $p) {
                $produitsById[$p->getId()] = $p;
            }

            if (isset($data['recommandations'])) {
                foreach ($data['recommandations'] as $rec) {
                    $id = (int)$rec['id'];
                    if (isset($produitsById[$id])) {
                        $result['produits'][] = $produitsById[$id];
                        $result['raisons'][$id] = $rec['raison'] ?? 'Recommandé par l\'IA';
                    }
                }
            }

            if (isset($data['alertes'])) {
                $result['alertes'] = $data['alertes'];
            }

        } catch (\Exception $e) {
            $this->logger->warning('Erreur parsing réponse IA', ['error' => $e->getMessage()]);
        }

        return $result;
    }
}
