<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EvenementAiService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%ai_api_key%')]
        private string $apiKey,
        #[Autowire('%ai_api_base%')]
        private string $apiBase,
        #[Autowire('%ai_model%')]
        private string $model,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @return array{description: string, source: string}
     */
    public function suggestDescription(string $titre, ?string $lieu, ?string $dateDebut, ?string $dateFin): array
    {
        $titre = trim($titre);
        $lieu = trim((string) $lieu);
        $dateDebut = trim((string) $dateDebut);
        $dateFin = trim((string) $dateFin);

        if ($titre === '') {
            return ['description' => '', 'source' => 'fallback'];
        }

        if (trim($this->apiKey) === '') {
            return [
                'description' => $this->buildFallbackDescription($titre, $lieu, $dateDebut, $dateFin),
                'source' => 'fallback',
            ];
        }

        $text = $this->callModel(
            'Tu aides un coach sportif. Redige une description d evenement en francais, claire, motivante et professionnelle (4-6 phrases).',
            $this->buildPrompt($titre, $lieu, $dateDebut, $dateFin),
            260
        );

        if ($text === null || trim($text) === '') {
            return [
                'description' => $this->buildFallbackDescription($titre, $lieu, $dateDebut, $dateFin),
                'source' => 'fallback',
            ];
        }

        return [
            'description' => trim($text),
            'source' => 'ai',
        ];
    }

    /**
     * @return array{description: string, source: string}
     */
    public function improveDescription(string $description, ?string $titre = null): array
    {
        $description = trim($description);
        $titre = trim((string) $titre);

        if ($description === '') {
            return ['description' => '', 'source' => 'fallback'];
        }

        if (trim($this->apiKey) === '') {
            return [
                'description' => $this->buildImprovedFallbackDescription($description),
                'source' => 'fallback',
            ];
        }

        $prompt = [
            'Titre evenement: ' . ($titre !== '' ? $titre : 'non precise'),
            'Texte actuel: ' . $description,
            'Tache: ameliore le texte en francais (clair, pro, 3-5 phrases), sans promesse medicale.',
        ];

        $text = $this->callModel(
            'Tu ameliore des descriptions d evenement sportif. Tu gardes le sens, tu corriges et tu rends le texte plus professionnel.',
            implode("\n", $prompt),
            240
        );

        if ($text === null || trim($text) === '') {
            return [
                'description' => $this->buildImprovedFallbackDescription($description),
                'source' => 'fallback',
            ];
        }

        return [
            'description' => trim($text),
            'source' => 'ai',
        ];
    }

    private function callModel(string $systemPrompt, string $userPrompt, int $maxTokens): ?string
    {
        try {
            $response = $this->httpClient->request('POST', rtrim($this->apiBase, '/') . '/responses', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'temperature' => 0.5,
                    'max_output_tokens' => $maxTokens,
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt,
                        ],
                        [
                            'role' => 'user',
                            'content' => $userPrompt,
                        ],
                    ],
                ],
                'timeout' => 12,
                'max_duration' => 20,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray(false);
            $text = $this->extractText($data);
            return $text !== null ? trim($text) : null;
        } catch (\Throwable $exception) {
            if ($this->logger) {
                $this->logger->warning('Event AI request failed', [
                    'error' => $exception->getMessage(),
                ]);
            }

            return null;
        }
    }

    private function buildPrompt(string $titre, string $lieu, string $dateDebut, string $dateFin): string
    {
        $lines = [
            'Titre evenement: ' . $titre,
            'Lieu: ' . ($lieu !== '' ? $lieu : 'non precise'),
            'Date debut: ' . ($dateDebut !== '' ? $dateDebut : 'non precisee'),
            'Date fin: ' . ($dateFin !== '' ? $dateFin : 'non precisee'),
            'Contrainte: style professionnel, accessible, orientee bien-etre. Eviter toute promesse medicale.',
        ];

        return implode("\n", $lines);
    }

    private function buildFallbackDescription(string $titre, string $lieu, string $dateDebut, string $dateFin): string
    {
        $parts = [];
        $parts[] = sprintf('L evenement "%s" propose une seance encadree pour progresser dans un cadre motive et bienveillant.', $titre);
        if ($dateDebut !== '') {
            $parts[] = sprintf('Le rendez-vous debute le %s', $dateDebut);
            if ($dateFin !== '') {
                $parts[] = sprintf('et se termine le %s.', $dateFin);
            } else {
                $parts[] = '.';
            }
        }
        if ($lieu !== '') {
            $parts[] = sprintf('La seance aura lieu a %s.', $lieu);
        }
        $parts[] = 'Le programme inclut echauffement, activites adaptees et retour au calme.';
        $parts[] = 'Chaque participant est invite a respecter son rythme et a suivre les consignes du coach.';

        return implode(' ', $parts);
    }

    private function buildImprovedFallbackDescription(string $description): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($description)) ?? trim($description);
        if ($normalized === '') {
            return '';
        }

        $normalized = ucfirst($normalized);
        if (!preg_match('/[.!?]$/', $normalized)) {
            $normalized .= '.';
        }

        return $normalized;
    }

    private function extractText(array $data): ?string
    {
        if (isset($data['output_text']) && is_string($data['output_text'])) {
            return $data['output_text'];
        }

        if (!isset($data['output']) || !is_array($data['output'])) {
            return null;
        }

        foreach ($data['output'] as $item) {
            if (!is_array($item) || !isset($item['content']) || !is_array($item['content'])) {
                continue;
            }

            foreach ($item['content'] as $content) {
                if (is_array($content) && isset($content['text']) && is_string($content['text'])) {
                    return $content['text'];
                }
            }
        }

        return null;
    }
}
