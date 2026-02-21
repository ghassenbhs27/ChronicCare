<?php

namespace App\Service;

use App\Entity\Etat;
use App\Entity\User;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiEtatWeeklyAnalysisService
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * @param Etat[] $etats
     */
    public function buildWeeklyAnalysis(User $patient, array $etats): string
    {
        $fallback = $this->fallback($patient, $etats);
        $apiKey = trim((string) ($_ENV['OPENAI_API_KEY'] ?? ''));
        if ($apiKey === '') {
            return $fallback;
        }

        try {
            $etatRows = [];
            foreach ($etats as $etat) {
                $etatRows[] = [
                    'dateReleve' => $etat->getDateReleve()?->format('Y-m-d'),
                    'temperatureCorporelle' => $etat->getTemperatureCorporelle(),
                    'niveauHydratation' => $etat->getNiveauHydratation(),
                    'traitementEnCours' => $etat->getTraitementEnCours(),
                    'remarquesCliniques' => $etat->getRemarquesCliniques(),
                ];
            }

            $payload = [
                'model' => (string) ($_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini'),
                'temperature' => 0.2,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un assistant de suivi patient. Retourne un texte court, structure, sans diagnostic.',
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'task' => 'Analyser les etats des 7 derniers jours d un patient chronique.',
                            'format' => [
                                'Titre: Analyse IA 7 jours',
                                '1) Resume global (4 lignes max)',
                                '2) Points de vigilance',
                                '3) Recommandations pratiques pour les prochains jours',
                                '4) Message final a valider par coach/nutritionniste',
                            ],
                            'constraints' => [
                                'Pas de diagnostic',
                                'Texte professionnel et court',
                                'Francais',
                            ],
                            'patient' => [
                                'id' => $patient->getId(),
                                'nom' => $patient->getNom(),
                                'prenom' => $patient->getPrenom(),
                            ],
                            'etats' => $etatRows,
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
                'timeout' => 12,
            ]);

            $data = $response->toArray(false);
            $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
            if ($content === '') {
                return $fallback;
            }

            return $content;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * @param Etat[] $etats
     */
    private function fallback(User $patient, array $etats): string
    {
        if ($etats === []) {
            return "Analyse IA 7 jours\n\nAucun etat disponible sur les 7 derniers jours.\n\nRecommandation: inviter le patient a saisir ses releves quotidiens pour une analyse fiable.";
        }

        $temps = [];
        $hydrs = [];
        foreach ($etats as $e) {
            if ($e->getTemperatureCorporelle() !== null) {
                $temps[] = (float) $e->getTemperatureCorporelle();
            }
            if ($e->getNiveauHydratation() !== null) {
                $hydrs[] = (float) $e->getNiveauHydratation();
            }
        }
        $avgTemp = $temps !== [] ? array_sum($temps) / count($temps) : 0.0;
        $avgHydr = $hydrs !== [] ? array_sum($hydrs) / count($hydrs) : 0.0;

        $vigilance = [];
        if ($avgTemp >= 38.0) {
            $vigilance[] = '- Temperature moyenne elevee sur la periode.';
        }
        if ($avgHydr > 0 && $avgHydr < 1.5) {
            $vigilance[] = '- Hydratation moyenne insuffisante.';
        }
        if ($vigilance === []) {
            $vigilance[] = '- Aucun signal critique detecte dans les releves disponibles.';
        }

        return sprintf(
            "Analyse IA 7 jours - %s %s\n\nResume global:\n- %d releve(s) analyse(s).\n- Temperature moyenne: %.1f C.\n- Hydratation moyenne: %.1f L/jour.\n\nPoints de vigilance:\n%s\n\nRecommandations:\n- Maintenir un releve quotidien.\n- Adapter l activite selon les signes cliniques.\n- Validation finale par coach/nutritionniste obligatoire.",
            (string) $patient->getPrenom(),
            (string) $patient->getNom(),
            count($etats),
            $avgTemp,
            $avgHydr,
            implode("\n", $vigilance)
        );
    }
}
