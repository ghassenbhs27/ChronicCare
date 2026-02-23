<?php

namespace App\Service;

use App\Entity\Activite;
use App\Entity\Etat;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiActiviteRecommendationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    /**
     * @return array{type:string,duree:int,distanceKm:float,heuresRepos:int,calories:int,notes:string}
     */
    public function recommendForEtat(Etat $etat): array
    {
        $apiKey = trim((string) ($_ENV['OPENAI_API_KEY'] ?? ''));
        if ($apiKey === '') {
            return $this->fallbackRecommendation($etat);
        }

        try {
            $payload = [
                'model' => (string) ($_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini'),
                'temperature' => 0.2,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un assistant medical de support non diagnostique. Retourne uniquement du JSON.',
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'task' => 'Proposer une activite adaptee a un patient chronique',
                            'constraints' => [
                                'type doit etre marche, course ou velo',
                                'duree entre 10 et 90',
                                'distanceKm entre 0.2 et 20',
                                'heuresRepos entre 6 et 12',
                                'calories >= 0',
                                'donner notes courtes',
                                'si temperature >= 38: pas d effort intense, pas de course, repos prioritaire',
                            ],
                            'etat' => [
                                'traitementEnCours' => $etat->getTraitementEnCours(),
                                'remarquesCliniques' => $etat->getRemarquesCliniques(),
                                'temperatureCorporelle' => $etat->getTemperatureCorporelle(),
                                'niveauHydratation' => $etat->getNiveauHydratation(),
                            ],
                            'json_schema' => [
                                'type' => 'string',
                                'duree' => 'integer',
                                'distanceKm' => 'number',
                                'heuresRepos' => 'integer',
                                'calories' => 'integer',
                                'notes' => 'string',
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ];

            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 8,
            ]);

            $data = $response->toArray(false);
            $content = (string) ($data['choices'][0]['message']['content'] ?? '');
            $content = trim($content);
            if (str_starts_with($content, '```')) {
                $content = preg_replace('/^```[a-zA-Z0-9]*\s*/', '', $content) ?? $content;
                $content = preg_replace('/\s*```$/', '', $content) ?? $content;
            }
            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                return $this->fallbackRecommendation($etat);
            }

            return $this->normalizeRecommendation($decoded, $etat);
        } catch (\Throwable) {
            return $this->fallbackRecommendation($etat);
        }
    }

    /**
     * @param array<string,mixed> $raw
     * @return array{type:string,duree:int,distanceKm:float,heuresRepos:int,calories:int,notes:string}
     */
    private function normalizeRecommendation(array $raw, Etat $etat): array
    {
        $type = (string) ($raw['type'] ?? 'marche');
        if (!in_array($type, Activite::TYPE_CHOICES, true)) {
            $type = 'marche';
        }

        $temperature = (float) ($etat->getTemperatureCorporelle() ?? '36.5');
        $hydration = (float) ($etat->getNiveauHydratation() ?? '2.0');

        $duree = max(10, min(90, (int) ($raw['duree'] ?? 30)));
        $distance = max(0.2, min(20.0, (float) ($raw['distanceKm'] ?? 2.0)));
        $repos = max(6, min(12, (int) ($raw['heuresRepos'] ?? 8)));
        $calories = max(0, (int) ($raw['calories'] ?? 0));
        if ($calories === 0) {
            $rate = $type === 'course' ? 10 : ($type === 'velo' ? 8 : 5);
            $calories = $duree * $rate;
        }

        $notes = trim((string) ($raw['notes'] ?? 'Recommandation IA selon votre etat du jour.'));
        if ($notes === '') {
            $notes = 'Recommandation IA selon votre etat du jour.';
        }

        // Safety overrides: when temperature is elevated, enforce very light activity.
        if ($temperature >= 38.5) {
            $type = 'marche';
            $duree = 10;
            $distance = 0.2;
            $repos = max($repos, 11);
            $calories = 35;
            $notes = 'Temperature elevee: effort minimal, repos prioritaire et contact rapide avec coach/nutritionniste.';
        } elseif ($temperature >= 38.0) {
            $type = 'marche';
            $duree = 12;
            $distance = 0.3;
            $repos = max($repos, 10);
            $calories = 45;
            $notes = 'Temperature haute: activite tres legere uniquement, hydratation et surveillance des symptomes.';
        } elseif ($temperature >= 37.5) {
            $type = 'marche';
            $duree = min($duree, 20);
            $distance = min($distance, 1.0);
            $repos = max($repos, 9);
        } elseif ($hydration < 1.5) {
            $type = 'marche';
            $duree = min($duree, 20);
            $distance = min($distance, 1.0);
            $repos = max($repos, 9);
            $notes = 'Hydratation faible: activite douce, priorite a la rehydratation.';
        }

        return [
            'type' => $type,
            'duree' => $duree,
            'distanceKm' => $distance,
            'heuresRepos' => $repos,
            'calories' => $calories,
            'notes' => $notes,
        ];
    }

    /**
     * @return array{type:string,duree:int,distanceKm:float,heuresRepos:int,calories:int,notes:string}
     */
    private function fallbackRecommendation(Etat $etat): array
    {
        $temperature = (float) ($etat->getTemperatureCorporelle() ?? '36.5');
        $hydration = (float) ($etat->getNiveauHydratation() ?? '2.0');

        if ($temperature >= 38.5) {
            $type = 'marche';
            $duree = 10;
            $distance = 0.2;
            $repos = 11;
            $notes = 'Temperature tres elevee: effort minimal, repos et avis medical recommande.';
        } elseif ($temperature >= 38.0) {
            $type = 'marche';
            $duree = 12;
            $distance = 0.3;
            $repos = 10;
            $notes = 'Temperature elevee: activite tres legere, hydratation et surveillance.';
        } elseif ($hydration < 1.5) {
            $type = 'marche';
            $duree = 15;
            $distance = 0.8;
            $repos = 10;
            $notes = 'Hydratation insuffisante: rehydratation avant intensification de l effort.';
        } elseif ($temperature >= 37.0) {
            $type = 'marche';
            $duree = 30;
            $distance = 2.5;
            $repos = 9;
            $notes = 'Activite moderee et progressive avec controle des symptomes.';
        } elseif ($hydration >= 2.5) {
            $type = 'velo';
            $duree = 40;
            $distance = 8.0;
            $repos = 8;
            $notes = 'Bon niveau hydrique: activite reguliere possible.';
        } else {
            $type = 'course';
            $duree = 25;
            $distance = 4.0;
            $repos = 8;
            $notes = 'Etat stable: activite cardio moderee possible.';
        }

        $rate = $type === 'course' ? 10 : ($type === 'velo' ? 8 : 5);

        return [
            'type' => $type,
            'duree' => $duree,
            'distanceKm' => $distance,
            'heuresRepos' => $repos,
            'calories' => $duree * $rate,
            'notes' => $notes,
        ];
    }
}
