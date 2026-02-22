<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExerciceAiService
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
    public function suggestDescription(string $nom, ?int $duree, ?int $repetitions, ?string $evenement): array
    {
        $nom = trim($nom);
        if ($nom === '') {
            return [
                'description' => '',
                'source' => 'fallback',
            ];
        }

        if (trim($this->apiKey) === '') {
            return [
                'description' => $this->buildFallbackDescription($nom, $duree, $repetitions, $evenement),
                'source' => 'fallback',
            ];
        }

        $text = $this->callModel(
            'Tu aides un coach sportif. Redige une description concise, claire et actionnable en francais (3-5 phrases).',
            $this->buildPrompt($nom, $duree, $repetitions, $evenement),
            220
        );

        if ($text === null || trim($text) === '') {
            return [
                'description' => $this->buildFallbackDescription($nom, $duree, $repetitions, $evenement),
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
    public function improveDescription(string $description, ?string $nom = null): array
    {
        $description = trim($description);
        $nom = trim((string) $nom);

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
            'Nom exercice: ' . ($nom !== '' ? $nom : 'non precise'),
            'Texte actuel: ' . $description,
            'Tache: ameliore le texte en francais (clair, actionnable, 3-5 phrases) sans promesse medicale.',
        ];

        $text = $this->callModel(
            'Tu ameliore des descriptions d exercices sportifs. Tu gardes le sens, tu clarifies, tu rends le texte professionnel.',
            implode("\n", $prompt),
            220
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
                $this->logger->warning('Exercise AI request failed', [
                    'error' => $exception->getMessage(),
                ]);
            }

            return null;
        }
    }

    private function buildPrompt(string $nom, ?int $duree, ?int $repetitions, ?string $evenement): string
    {
        $lines = [
            'Nom exercice: ' . $nom,
            'Duree (minutes): ' . ($duree !== null ? (string) $duree : 'non precisee'),
            'Repetitions: ' . ($repetitions !== null ? (string) $repetitions : 'non precisees'),
            'Evenement: ' . ($evenement !== null && trim($evenement) !== '' ? trim($evenement) : 'non precise'),
            'Contrainte: ton professionnel, orientee securite et progression, sans promesse medicale.',
        ];

        return implode("\n", $lines);
    }

    private function buildFallbackDescription(string $nom, ?int $duree, ?int $repetitions, ?string $evenement): string
    {
        $parts = [];
        $parts[] = sprintf('Cet exercice "%s" vise a ameliorer la condition physique de facon progressive.', $nom);
        if ($duree !== null) {
            $parts[] = sprintf('La duree recommandee est d\'environ %d minute%s par sequence.', $duree, $duree > 1 ? 's' : '');
        } else {
            $parts[] = 'La duree est a adapter selon le niveau des participants.';
        }
        if ($repetitions !== null) {
            $parts[] = sprintf('Realiser %d repetition%s avec un rythme controle et une execution propre.', $repetitions, $repetitions > 1 ? 's' : '');
        }
        if ($evenement !== null && trim($evenement) !== '') {
            $parts[] = sprintf('Cet exercice est integre a l\'evenement "%s".', trim($evenement));
        }
        $parts[] = 'Prevoir un echauffement, une posture stable et des pauses si necessaire.';

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
