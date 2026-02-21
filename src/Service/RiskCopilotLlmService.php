<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class RiskCopilotLlmService
{
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    private const OPENROUTER_API_URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    private ?string $lastError = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%ai_provider%')]
        private readonly string $provider,
        #[Autowire('%ai_openai_api_key%')]
        private readonly ?string $openAiApiKey,
        #[Autowire('%ai_openai_model%')]
        private readonly string $openAiModel,
        #[Autowire('%ai_openrouter_api_key%')]
        private readonly ?string $openRouterApiKey,
        #[Autowire('%ai_openrouter_model%')]
        private readonly string $openRouterModel,
        #[Autowire('%ai_openrouter_site_url%')]
        private readonly ?string $openRouterSiteUrl,
        #[Autowire('%ai_openrouter_app_name%')]
        private readonly ?string $openRouterAppName,
        #[Autowire('%ai_gemini_api_key%')]
        private readonly ?string $geminiApiKey,
        #[Autowire('%ai_gemini_model%')]
        private readonly string $geminiModel,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $riskUsers
     * @param array<int, array<int, array<string, mixed>>> $openAlertsByUser
     * @return array{
     *   provider:string,
     *   executive_summary:string,
     *   top_user_email:string,
     *   top_user_reason:string,
     *   global_actions:array<int,string>,
     *   users:array<int, array<string, mixed>>
     * }|null
     */
    public function analyzeRiskUsers(string $question, array $riskUsers, array $openAlertsByUser): ?array
    {
        $this->lastError = null;

        if ($riskUsers === []) {
            return null;
        }

        $activeProvider = $this->resolveProvider();
        if ($activeProvider === null) {
            $this->lastError = 'Aucune cle API IA configuree.';
            return null;
        }

        $dataset = [
            'question' => $question,
            'period' => '7 derniers jours',
            'risk_users' => $riskUsers,
            'open_alert_signals_by_user_id' => $openAlertsByUser,
        ];

        $systemPrompt = <<<PROMPT
Tu es Risk Copilot pour un back-office medical.
Analyse le JSON fourni et retourne UNIQUEMENT un JSON valide.
Pas de markdown.
Format exact:
{
  "executive_summary": "string court en francais",
  "top_user_email": "email de l utilisateur le plus critique",
  "top_user_reason": "raison principale en une phrase",
  "global_actions": ["action 1", "action 2", "action 3"],
  "users": [
    {
      "email": "email",
      "risk_level": "critical|high|medium",
      "why": ["cause 1", "cause 2"],
      "actions": ["action concrete 1", "action concrete 2"]
    }
  ]
}
Les actions doivent etre concretes et operationnelles pour un admin (surveiller, bloquer temporairement, reset mdp, contacter utilisateur, etc.).
PROMPT;

        $userPrompt = json_encode($dataset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($userPrompt)) {
            return null;
        }

        try {
            $content = match ($activeProvider) {
                'gemini' => $this->callGemini($systemPrompt, $userPrompt),
                'openrouter' => $this->callOpenRouter($systemPrompt, $userPrompt),
                default => $this->callOpenAi($systemPrompt, $userPrompt),
            };
        } catch (\Throwable $exception) {
            if ($this->lastError === null) {
                $this->lastError = $exception->getMessage();
            }
            $this->logger?->warning('Risk Copilot LLM request failed', [
                'provider' => $activeProvider,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($content === null || trim($content) === '') {
            if ($this->lastError === null) {
                $this->lastError = 'Reponse vide depuis le fournisseur IA.';
            }
            return null;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            $extracted = $this->extractFirstJsonObject($content);
            if ($extracted !== null) {
                $decoded = json_decode($extracted, true);
            }
            if (!is_array($decoded)) {
                $this->lastError = 'Reponse IA invalide (JSON attendu).';
                return null;
            }
        }

        return $this->normalizeOutput($decoded, $riskUsers, $activeProvider);
    }

    private function resolveProvider(): ?string
    {
        $provider = strtolower(trim($this->provider));
        if ($provider === 'gemini') {
            return trim((string) $this->geminiApiKey) !== '' ? 'gemini' : null;
        }

        if ($provider === 'openai') {
            return trim((string) $this->openAiApiKey) !== '' ? 'openai' : null;
        }

        if ($provider === 'openrouter') {
            return trim((string) $this->openRouterApiKey) !== '' ? 'openrouter' : null;
        }

        if (trim((string) $this->openAiApiKey) !== '') {
            return 'openai';
        }

        if (trim((string) $this->geminiApiKey) !== '') {
            return 'gemini';
        }

        if (trim((string) $this->openRouterApiKey) !== '') {
            return 'openrouter';
        }

        return null;
    }

    private function callOpenAi(string $systemPrompt, string $userPrompt): ?string
    {
        $response = $this->httpClient->request('POST', self::OPENAI_API_URL, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . (string) $this->openAiApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->openAiModel !== '' ? $this->openAiModel : 'gpt-4o-mini',
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ],
        ]);

        $data = $response->toArray(false);
        if (!is_array($data)) {
            return null;
        }

        $choices = $data['choices'] ?? null;
        if (!is_array($choices) || !isset($choices[0]) || !is_array($choices[0])) {
            return null;
        }

        $message = $choices[0]['message'] ?? null;
        if (!is_array($message)) {
            return null;
        }

        $content = $message['content'] ?? null;
        return is_string($content) ? $content : null;
    }

    private function callGemini(string $systemPrompt, string $userPrompt): ?string
    {
        $configured = $this->normalizeGeminiModelName($this->geminiModel);
        $candidates = array_values(array_unique(array_filter([
            $configured !== '' ? $configured : null,
            'gemini-2.0-flash',
            'gemini-flash-latest',
        ])));

        foreach ($candidates as $model) {
            $url = sprintf(self::GEMINI_API_URL, rawurlencode($model));
            $response = $this->httpClient->request('POST', $url, [
                'timeout' => 20,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => (string) $this->geminiApiKey,
                ],
                'json' => [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $systemPrompt . "\n\nDATA:\n" . $userPrompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'responseMimeType' => 'application/json',
                    ],
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $raw = (string) $response->getContent(false);
                if ($statusCode === 429) {
                    $this->lastError = 'Quota Gemini depasse (HTTP 429). Activez la facturation ou attendez le reset du quota.';
                    return null;
                }
                if ($statusCode === 401 || $statusCode === 403) {
                    $this->lastError = 'Cle API Gemini invalide ou non autorisee (HTTP ' . $statusCode . ').';
                    return null;
                }
                if ($statusCode === 404) {
                    // Try next model candidate.
                    continue;
                }
                $this->lastError = 'Erreur Gemini HTTP ' . $statusCode . '.';
                if ($raw !== '') {
                    $this->logger?->warning('Gemini error payload', [
                        'status' => $statusCode,
                        'body' => mb_substr($raw, 0, 300),
                    ]);
                }
                continue;
            }

            $data = $response->toArray(false);
            if (!is_array($data)) {
                continue;
            }

            $entries = $data['candidates'] ?? null;
            if (!is_array($entries) || !isset($entries[0]) || !is_array($entries[0])) {
                continue;
            }

            $content = $entries[0]['content'] ?? null;
            if (!is_array($content)) {
                continue;
            }

            $parts = $content['parts'] ?? null;
            if (!is_array($parts) || !isset($parts[0]) || !is_array($parts[0])) {
                continue;
            }

            $text = $parts[0]['text'] ?? null;
            if (is_string($text) && trim($text) !== '') {
                return $text;
            }
        }

        if ($this->lastError === null) {
            $this->lastError = 'Modele Gemini indisponible ou non accessible.';
        }

        return null;
    }

    private function callOpenRouter(string $systemPrompt, string $userPrompt): ?string
    {
        $headers = [
            'Authorization' => 'Bearer ' . (string) $this->openRouterApiKey,
            'Content-Type' => 'application/json',
        ];
        $site = trim((string) $this->openRouterSiteUrl);
        $appName = trim((string) $this->openRouterAppName);
        if ($site !== '') {
            $headers['HTTP-Referer'] = $site;
        }
        if ($appName !== '') {
            $headers['X-Title'] = $appName;
        }

        $response = $this->httpClient->request('POST', self::OPENROUTER_API_URL, [
            'timeout' => 20,
            'headers' => $headers,
            'json' => [
                'model' => $this->openRouterModel !== '' ? $this->openRouterModel : 'openrouter/auto',
                'temperature' => 0.2,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            if ($statusCode === 429) {
                $this->lastError = 'Quota OpenRouter depasse (HTTP 429).';
                return null;
            }
            if ($statusCode === 401 || $statusCode === 403) {
                $this->lastError = 'Cle API OpenRouter invalide ou non autorisee (HTTP ' . $statusCode . ').';
                return null;
            }

            $this->lastError = 'Erreur OpenRouter HTTP ' . $statusCode . '.';
            return null;
        }

        $data = $response->toArray(false);
        if (!is_array($data)) {
            return null;
        }

        $choices = $data['choices'] ?? null;
        if (!is_array($choices) || !isset($choices[0]) || !is_array($choices[0])) {
            return null;
        }

        $message = $choices[0]['message'] ?? null;
        if (!is_array($message)) {
            return null;
        }

        $content = $message['content'] ?? null;
        return is_string($content) ? $content : null;
    }

    /**
     * @param array<string, mixed> $decoded
     * @param array<int, array<string, mixed>> $riskUsers
     * @return array{
     *   provider:string,
     *   executive_summary:string,
     *   top_user_email:string,
     *   top_user_reason:string,
     *   global_actions:array<int,string>,
     *   users:array<int, array<string, mixed>>
     * }
     */
    private function normalizeOutput(array $decoded, array $riskUsers, string $provider): array
    {
        $byEmail = [];
        foreach ($riskUsers as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '') {
                continue;
            }
            $byEmail[$email] = $row;
        }

        $llmUsers = is_array($decoded['users'] ?? null) ? $decoded['users'] : [];
        $users = [];
        foreach ($llmUsers as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $email = strtolower(trim((string) ($entry['email'] ?? '')));
            if ($email === '' || !isset($byEmail[$email])) {
                continue;
            }
            $base = $byEmail[$email];

            $why = $this->normalizeStringList($entry['why'] ?? []);
            $actions = $this->normalizeStringList($entry['actions'] ?? []);

            $users[] = array_merge($base, [
                'risk_level' => $this->normalizeRiskLevel((string) ($entry['risk_level'] ?? 'medium')),
                'why' => $why,
                'actions' => $actions,
            ]);
        }

        if ($users === []) {
            foreach ($riskUsers as $row) {
                $users[] = array_merge($row, [
                    'risk_level' => (int) ($row['max_risk'] ?? 0) >= 70 ? 'high' : 'medium',
                    'why' => ['Plusieurs alertes de securite sur la periode.'],
                    'actions' => ['Surveiller le compte et verifier les dernieres connexions.'],
                ]);
            }
        }

        usort($users, static function (array $a, array $b): int {
            $diff = ((int) ($b['unresolved_alerts'] ?? 0)) <=> ((int) ($a['unresolved_alerts'] ?? 0));
            if ($diff !== 0) {
                return $diff;
            }

            return ((int) ($b['max_risk'] ?? 0)) <=> ((int) ($a['max_risk'] ?? 0));
        });

        return [
            'provider' => $provider,
            'executive_summary' => $this->normalizeSummary((string) ($decoded['executive_summary'] ?? '')),
            'top_user_email' => strtolower(trim((string) ($decoded['top_user_email'] ?? ''))),
            'top_user_reason' => trim((string) ($decoded['top_user_reason'] ?? '')),
            'global_actions' => $this->normalizeStringList($decoded['global_actions'] ?? []),
            'users' => $users,
        ];
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $out[] = $item;
        }

        return array_values(array_unique($out));
    }

    private function normalizeRiskLevel(string $riskLevel): string
    {
        $riskLevel = strtolower(trim($riskLevel));

        return in_array($riskLevel, ['critical', 'high', 'medium'], true) ? $riskLevel : 'medium';
    }

    private function normalizeSummary(string $summary): string
    {
        $summary = trim($summary);

        return $summary !== '' ? $summary : 'Synthese IA generee a partir des alertes de securite.';
    }

    private function normalizeGeminiModelName(string $model): string
    {
        $model = trim($model);
        if ($model === '') {
            return '';
        }

        if (str_starts_with($model, 'models/')) {
            return substr($model, 7);
        }

        return $model;
    }

    private function extractFirstJsonObject(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (preg_match('/```json\s*(\{.*\})\s*```/is', $text, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $length = strlen($text);
        for ($i = $start; $i < $length; $i++) {
            $ch = $text[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $escape = true;
                continue;
            }
            if ($ch === '"') {
                $inString = !$inString;
                continue;
            }
            if ($inString) {
                continue;
            }
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
