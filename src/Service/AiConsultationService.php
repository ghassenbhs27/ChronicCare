<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiConsultationService
{
    private const DEFAULT_MODEL = 'gpt-4.1-mini';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $apiKey,
        private readonly ?string $model
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    /**
     * @return array{resume: string, conseils: string}|null
     */
    public function generateSummaryAndAdvice(string $notes, ?string $diagnostic): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $prompt = [
            'Tu es un assistant medical. A partir des notes et du diagnostic, genere un resume et des conseils.',
            'Contraintes:',
            '- Resume: 4 a 6 lignes, clair, non anxiogene.',
            '- Conseils: 3 a 5 points courts, pratiques.',
            '- Sortie JSON strict: {"resume":"...","conseils":"..."}',
            '',
            'Notes:',
            $notes,
            '',
            'Diagnostic:',
            $diagnostic ?? 'Aucun',
        ];

        $payload = [
            'model' => $this->model ?: self::DEFAULT_MODEL,
            'input' => implode("\n", $prompt),
            'max_output_tokens' => 350,
            'temperature' => 0.2,
        ];

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/responses', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $data = $response->toArray(false);
        $text = $this->extractText($data);
        if ($text === null) {
            return null;
        }

        $decoded = json_decode($text, true);
        if (!is_array($decoded) || !isset($decoded['resume'], $decoded['conseils'])) {
            return null;
        }

        return [
            'resume' => trim((string) $decoded['resume']),
            'conseils' => trim((string) $decoded['conseils']),
        ];
    }

    private function extractText(array $data): ?string
    {
        if (isset($data['output_text']) && is_string($data['output_text'])) {
            return $data['output_text'];
        }

        if (!isset($data['output']) || !is_array($data['output'])) {
            return null;
        }

        foreach ($data['output'] as $output) {
            if (!isset($output['content']) || !is_array($output['content'])) {
                continue;
            }
            foreach ($output['content'] as $content) {
                if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                    return (string) $content['text'];
                }
            }
        }

        return null;
    }
}
