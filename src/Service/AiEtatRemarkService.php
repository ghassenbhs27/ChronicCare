<?php

namespace App\Service;

use App\Entity\Etat;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiEtatRemarkService
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * @return array{
     *   resume:string,
     *   consignes:string[],
     *   traitement7Jours:string[],
     *   repas7Jours:string[],
     *   vigilance:string[],
     * }
     */
    public function buildEtatReport(Etat $etat): array
    {
        $fallback = $this->fallback($etat);
        $apiKey = trim((string) ($_ENV['OPENAI_API_KEY'] ?? ''));
        if ($apiKey === '') {
            return $fallback;
        }

        try {
            $payload = [
                'model' => (string) ($_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini'),
                'temperature' => 0.3,
                'messages' => [
                    ['role' => 'system', 'content' => 'Tu es un assistant de support sante. Retourne seulement JSON valide.'],
                    ['role' => 'user', 'content' => json_encode([
                        'task' => 'Generer une fiche breve et professionnelle a partir d un etat patient.',
                        'constraints' => [
                            '4 sections max',
                            '3 a 5 puces par section',
                            'traitement7Jours doit contenir 7 lignes (Jour 1 ... Jour 7)',
                            'repas7Jours doit contenir 7 lignes (Jour 1 ... Jour 7)',
                            'pas de diagnostic',
                            'rappeler validation coach/nutritionniste/medecin',
                        ],
                        'schema' => [
                            'resume' => 'string',
                            'consignes' => ['string'],
                            'traitement7Jours' => ['string'],
                            'repas7Jours' => ['string'],
                            'vigilance' => ['string'],
                        ],
                        'etat' => [
                            'traitementEnCours' => $etat->getTraitementEnCours(),
                            'remarquesCliniques' => $etat->getRemarquesCliniques(),
                            'temperatureCorporelle' => $etat->getTemperatureCorporelle(),
                            'niveauHydratation' => $etat->getNiveauHydratation(),
                            'dateReleve' => $etat->getDateReleve()?->format('Y-m-d'),
                        ],
                    ], JSON_UNESCAPED_UNICODE)],
                ],
            ];

            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 10,
            ]);

            $data = $response->toArray(false);
            $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
            if (str_starts_with($content, '```')) {
                $content = preg_replace('/^```[a-zA-Z0-9]*\s*/', '', $content) ?? $content;
                $content = preg_replace('/\s*```$/', '', $content) ?? $content;
            }

            $parsed = json_decode($content, true);
            if (!is_array($parsed)) {
                return $fallback;
            }

            return [
                'resume' => (string) ($parsed['resume'] ?? $fallback['resume']),
                'consignes' => $this->toList($parsed['consignes'] ?? [], $fallback['consignes']),
                'traitement7Jours' => $this->toList($parsed['traitement7Jours'] ?? [], $fallback['traitement7Jours'], 7),
                'repas7Jours' => $this->toList($parsed['repas7Jours'] ?? [], $fallback['repas7Jours'], 7),
                'vigilance' => $this->toList($parsed['vigilance'] ?? [], $fallback['vigilance']),
            ];
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * @return array<int,string>
     */
    private function toList(mixed $value, array $fallback, int $max = 5): array
    {
        if (!is_array($value)) {
            return $fallback;
        }
        $out = [];
        foreach ($value as $item) {
            $line = trim((string) $item);
            if ($line !== '') {
                $out[] = $line;
            }
            if (count($out) >= $max) {
                break;
            }
        }

        return $out !== [] ? $out : $fallback;
    }

    /**
     * @return array{
     *   resume:string,
     *   consignes:string[],
     *   traitement7Jours:string[],
     *   repas7Jours:string[],
     *   vigilance:string[],
     * }
     */
    private function fallback(Etat $etat): array
    {
        $temp = (float) ($etat->getTemperatureCorporelle() ?? '36.5');
        $hydr = (float) ($etat->getNiveauHydratation() ?? '2.0');

        $resume = 'Etat a surveiller avec adaptation de l activite et suivi regulier.';
        if ($temp >= 38.0) {
            $resume = 'Temperature elevee: priorite au repos, hydratation et suivi professionnel rapide.';
        } elseif ($hydr < 1.5) {
            $resume = 'Hydratation basse: priorite a la rehydratation et effort reduit.';
        }

        $traitement7Jours = [];
        $repas7Jours = [];
        for ($i = 1; $i <= 7; $i++) {
            if ($temp >= 38.0) {
                $traitement7Jours[] = sprintf(
                    'Jour %d: Repos actif court, hydratation renforcee, controle temperature matin/soir, validation pro.',
                    $i
                );
                $repas7Jours[] = sprintf(
                    'Jour %d: Bouillon/legumes cuits + proteine legere (poisson/oeuf) + fruits riches en eau.',
                    $i
                );
            } elseif ($hydr < 1.5) {
                $traitement7Jours[] = sprintf(
                    'Jour %d: Rehydratation fractionnee (eau + electrolytes), effort limite, suivi des signes.',
                    $i
                );
                $repas7Jours[] = sprintf(
                    'Jour %d: Repas hydratants (soupe, crudites, yaourt nature), limiter sel excessif.',
                    $i
                );
            } else {
                $traitement7Jours[] = sprintf(
                    'Jour %d: Traitement habituel + activite moderee + sommeil 7-8h avec suivi quotidien.',
                    $i
                );
                $repas7Jours[] = sprintf(
                    'Jour %d: Assiette equilibree (1/2 legumes, 1/4 proteines, 1/4 glucides complets).',
                    $i
                );
            }
        }

        return [
            'resume' => $resume,
            'consignes' => [
                'Mesurer les signes 2 a 3 fois dans la journee.',
                'Eviter l effort intense tant que les indicateurs ne sont pas stables.',
                'Adapter l activite avec validation du coach/nutritionniste.',
            ],
            'traitement7Jours' => $traitement7Jours,
            'repas7Jours' => $repas7Jours,
            'vigilance' => [
                'Suivre fatigue, vertiges, essoufflement, douleurs.',
                'Dormir 7-8 heures avec horaire stable.',
                'Contacter medecin si fievre persistante ou symptomes importants.',
            ],
        ];
    }
}
