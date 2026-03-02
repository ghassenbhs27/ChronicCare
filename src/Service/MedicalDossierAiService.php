<?php
declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MedicalDossierAiService
{
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    private const OPENROUTER_API_URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /** @var array<string, array<int, array<string, mixed>>> */
    private const LOCAL_QUESTION_TEMPLATES = [
        'diabete' => [
            ['id' => 'glycemia', 'label' => 'Derniere glycemie mesuree (mg/dL)', 'type' => 'number', 'placeholder' => 'ex: 165'],
            ['id' => 'insulin', 'label' => 'Prenez-vous de l insuline ?', 'type' => 'choice', 'options' => ['oui', 'non']],
            ['id' => 'hypo_signs', 'label' => 'Avez-vous eu tremblements, sueurs ou malaise recemment ?', 'type' => 'choice', 'options' => ['oui', 'non']],
            ['id' => 'diet', 'label' => 'Decrivez vos habitudes alimentaires actuelles', 'type' => 'text', 'placeholder' => 'Repas, sucre, horaires'],
            ['id' => 'followup', 'label' => 'Date de votre dernier suivi medical', 'type' => 'text', 'placeholder' => 'ex: 10/02/2026'],
        ],
        'hypertension' => [
            ['id' => 'tension', 'label' => 'Derniere tension arterielle (ex: 14/9)', 'type' => 'text', 'placeholder' => 'ex: 16/10'],
            ['id' => 'headache', 'label' => 'Maux de tete intenses ou vertiges recents ?', 'type' => 'choice', 'options' => ['oui', 'non']],
            ['id' => 'chest_pain', 'label' => 'Douleurs thoraciques recentes ?', 'type' => 'choice', 'options' => ['oui', 'non']],
            ['id' => 'medication', 'label' => 'Traitement antihypertenseur pris regulierement ?', 'type' => 'choice', 'options' => ['oui', 'non']],
            ['id' => 'salt', 'label' => 'Consommation de sel et d aliments transformes', 'type' => 'text', 'placeholder' => 'faible, moyenne, elevee'],
        ],
        'asthme' => [
            ['id' => 'crisis_freq', 'label' => 'Frequence des crises cette semaine', 'type' => 'text', 'placeholder' => 'ex: 2 crises'],
            ['id' => 'night_symptoms', 'label' => 'Symptomes nocturnes (toux, gene respiratoire) ?', 'type' => 'choice', 'options' => ['oui', 'non']],
            ['id' => 'inhaler', 'label' => 'Inhalateur de secours utilise aujourd hui ?', 'type' => 'choice', 'options' => ['oui', 'non']],
            ['id' => 'oxygen', 'label' => 'Sensation de manque d air actuelle (0-10)', 'type' => 'number', 'placeholder' => 'ex: 4'],
            ['id' => 'triggers', 'label' => 'Declencheurs identifies (allergenes, effort, fumee)', 'type' => 'text', 'placeholder' => 'decrivez'],
        ],
        'insuffisance_cardiaque' => [
            ['id' => 'weight_gain', 'label' => 'Prise de poids recente rapide ?', 'type' => 'choice', 'options' => ['oui', 'non']],
            ['id' => 'edema', 'label' => 'Gonflement des jambes/pieds ?', 'type' => 'choice', 'options' => ['oui', 'non']],
            ['id' => 'breathless', 'label' => 'Essoufflement au repos ?', 'type' => 'choice', 'options' => ['oui', 'non']],
            ['id' => 'fatigue', 'label' => 'Niveau de fatigue (0-10)', 'type' => 'number', 'placeholder' => 'ex: 7'],
            ['id' => 'treatment', 'label' => 'Traitement cardio suivi regulierement ?', 'type' => 'choice', 'options' => ['oui', 'non']],
        ],
    ];

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
     * @return array<int, string>
     */
    public function getSupportedDiseases(): array
    {
        return [
            'diabete',
            'hypertension',
            'asthme',
            'insuffisance_cardiaque',
            'epilepsie',
            'autre',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildQuestionnaire(string $disease): array
    {
        $disease = $this->normalizeDisease($disease);
        $this->lastError = null;

        $provider = $this->resolveProvider();
        if ($provider !== null) {
            try {
                $response = $this->buildQuestionnaireWithLlm($provider, $disease);
                if ($response !== null) {
                    return $response;
                }
            } catch (\Throwable $exception) {
                $this->lastError = $exception->getMessage();
                $this->logger?->warning('Medical questionnaire AI failed, fallback local.', [
                    'provider' => $provider,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $this->buildLocalQuestionnaire($disease);
    }

    /**
     * @param array<int, array<string, mixed>> $answers
     * @return array<string, mixed>
     */
    public function analyzeEmergencyRisk(string $disease, array $answers): array
    {
        $disease = $this->normalizeDisease($disease);
        $normalizedAnswers = $this->normalizeAnswers($answers);

        $provider = $this->resolveProvider();
        if ($provider !== null) {
            try {
                $result = $this->analyzeRiskWithLlm($provider, $disease, $normalizedAnswers);
                if ($result !== null) {
                    return $result;
                }
            } catch (\Throwable $exception) {
                $this->logger?->warning('Medical risk AI failed, fallback local.', [
                    'provider' => $provider,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $this->analyzeRiskLocally($disease, $normalizedAnswers);
    }

    private function normalizeDisease(string $disease): string
    {
        $disease = strtolower(trim($disease));
        if ($disease === '') {
            return 'autre';
        }

        return $disease;
    }

    private function resolveProvider(): ?string
    {
        $provider = strtolower(trim($this->provider));
        if ($provider === 'openrouter' && trim((string) $this->openRouterApiKey) !== '') {
            return 'openrouter';
        }
        if ($provider === 'openai' && trim((string) $this->openAiApiKey) !== '') {
            return 'openai';
        }
        if ($provider === 'gemini' && trim((string) $this->geminiApiKey) !== '') {
            return 'gemini';
        }

        if (trim((string) $this->openRouterApiKey) !== '') {
            return 'openrouter';
        }
        if (trim((string) $this->openAiApiKey) !== '') {
            return 'openai';
        }
        if (trim((string) $this->geminiApiKey) !== '') {
            return 'gemini';
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildQuestionnaireWithLlm(string $provider, string $disease): ?array
    {
        $systemPrompt = <<<PROMPT
Tu es un assistant medical pour pre-remplir un dossier patient.
Retourne UNIQUEMENT un JSON valide.
Format:
{
  "intro": "message court et clair",
  "questions": [
    {"id":"q1","label":"...","type":"text|number|choice","placeholder":"...","options":["..."]}
  ]
}
Contraintes:
- 5 questions maximum
- Questions pratiques et non redondantes
- Si type != choice, ne pas inclure options
- Pas de markdown
PROMPT;

        $userPrompt = json_encode([
            'task' => 'Generate questionnaire for chronic disease registration',
            'disease' => $disease,
            'language' => 'fr',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($userPrompt)) {
            return null;
        }

        $content = $this->callProvider($provider, $systemPrompt, $userPrompt, true);
        if ($content === null) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            $json = $this->extractFirstJsonObject($content);
            if ($json !== null) {
                $decoded = json_decode($json, true);
            }
        }
        if (!is_array($decoded)) {
            return null;
        }

        $intro = trim((string) ($decoded['intro'] ?? ''));
        $questions = $this->sanitizeQuestions($decoded['questions'] ?? []);
        if ($questions === []) {
            return null;
        }

        return [
            'disease' => $disease,
            'intro' => $intro !== '' ? $intro : 'Je vais vous aider a remplir votre dossier medical.',
            'questions' => $questions,
            'mode' => strtoupper($provider),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $answers
     * @return array<string, mixed>|null
     */
    private function analyzeRiskWithLlm(string $provider, string $disease, array $answers): ?array
    {
        $systemPrompt = <<<PROMPT
Tu es un assistant pour l admin medical.
Analyse les reponses d un patient et retourne UNIQUEMENT un JSON valide:
{
  "summary":"resume court",
  "risk_level":"low|medium|high",
  "emergency_possible":true,
  "reason":"raison medicale principale",
  "recommended_actions":["action 1","action 2","action 3"]
}
Regles:
- emergency_possible=true seulement en presence de signaux potentiellement critiques.
- Reponses courtes et actionnables.
- Pas de markdown.
PROMPT;

        $userPrompt = json_encode([
            'disease' => $disease,
            'answers' => $answers,
            'language' => 'fr',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($userPrompt)) {
            return null;
        }

        $content = $this->callProvider($provider, $systemPrompt, $userPrompt, true);
        if ($content === null) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            $json = $this->extractFirstJsonObject($content);
            if ($json !== null) {
                $decoded = json_decode($json, true);
            }
        }
        if (!is_array($decoded)) {
            return null;
        }

        $riskLevel = $this->normalizeRiskLevel((string) ($decoded['risk_level'] ?? 'medium'));
        $reason = trim((string) ($decoded['reason'] ?? ''));
        $summary = trim((string) ($decoded['summary'] ?? ''));
        $recommended = $this->normalizeStringList($decoded['recommended_actions'] ?? []);

        return [
            'summary' => $summary !== '' ? $summary : 'Synthese IA du dossier medical.',
            'risk_level' => $riskLevel,
            'emergency_possible' => (bool) ($decoded['emergency_possible'] ?? false),
            'reason' => $reason !== '' ? $reason : 'Analyse basee sur les reponses patient.',
            'recommended_actions' => $recommended,
            'mode' => strtoupper($provider),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLocalQuestionnaire(string $disease): array
    {
        $questions = self::LOCAL_QUESTION_TEMPLATES[$disease] ?? [
            ['id' => 'symptoms', 'label' => 'Symptomes principaux actuels', 'type' => 'text', 'placeholder' => 'decrivez'],
            ['id' => 'pain_level', 'label' => 'Niveau de douleur/inconfort (0-10)', 'type' => 'number', 'placeholder' => 'ex: 5'],
            ['id' => 'treatment', 'label' => 'Traitement actuellement suivi', 'type' => 'text', 'placeholder' => 'medicaments, doses'],
            ['id' => 'history', 'label' => 'Antecedents et crises recentes', 'type' => 'text', 'placeholder' => 'dates et contexte'],
            ['id' => 'doctor_followup', 'label' => 'Dernier suivi medical', 'type' => 'text', 'placeholder' => 'date, specialite'],
        ];

        return [
            'disease' => $disease,
            'intro' => sprintf('Dossier medical pour %s: je vous pose quelques questions pour evaluer le risque.', str_replace('_', ' ', $disease)),
            'questions' => $questions,
            'mode' => 'FALLBACK',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $answers
     * @return array<string, mixed>
     */
    private function analyzeRiskLocally(string $disease, array $answers): array
    {
        $signalScore = 0;
        $signals = [];
        foreach ($answers as $answer) {
            $value = strtolower(trim((string) ($answer['answer'] ?? '')));
            if ($value === '') {
                continue;
            }

            if (str_contains($value, 'oui')) {
                $signalScore += 1;
            }
            if (preg_match('/\b(8|9|10)\b/', $value) === 1) {
                $signalScore += 2;
                $signals[] = 'Niveau de symptomes eleve';
            }
            if (preg_match('/douleur thoracique|essoufflement|malaise|convulsion|perte connaissance|urgence|severe|grave/', $value) === 1) {
                $signalScore += 3;
                $signals[] = 'Signal clinique critique mentionne';
            }
        }

        $riskLevel = 'low';
        $emergency = false;
        if ($signalScore >= 6) {
            $riskLevel = 'high';
            $emergency = true;
        } elseif ($signalScore >= 3) {
            $riskLevel = 'medium';
        }

        $reason = $signals !== [] ? implode('; ', array_unique($signals)) : 'Aucun signal critique explicite detecte.';
        $summary = sprintf(
            'Analyse locale dossier (%s): risque %s.',
            str_replace('_', ' ', $disease),
            strtoupper($riskLevel)
        );

        $actions = $emergency
            ? [
                'Contacter le patient immediatement.',
                'Orienter vers une evaluation medicale urgente.',
                'Verifier observance du traitement et signes vitaux.',
            ]
            : [
                'Programmer un suivi medical rapproche.',
                'Verifier adherence au traitement.',
                'Surveiller l evolution des symptomes.',
            ];

        return [
            'summary' => $summary,
            'risk_level' => $riskLevel,
            'emergency_possible' => $emergency,
            'reason' => $reason,
            'recommended_actions' => $actions,
            'mode' => 'FALLBACK',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $answers
     * @return array<int, array<string, mixed>>
     */
    private function normalizeAnswers(array $answers): array
    {
        $out = [];
        foreach ($answers as $row) {
            if (!is_array($row)) {
                continue;
            }
            $question = trim((string) ($row['question'] ?? ''));
            $answer = trim((string) ($row['answer'] ?? ''));
            if ($question === '' && $answer === '') {
                continue;
            }
            $out[] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $raw
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeQuestions(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $questions = [];
        foreach ($raw as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $type = strtolower(trim((string) ($item['type'] ?? 'text')));
            if (!in_array($type, ['text', 'number', 'choice'], true)) {
                $type = 'text';
            }
            $question = [
                'id' => trim((string) ($item['id'] ?? ('q' . ($index + 1)))) ?: ('q' . ($index + 1)),
                'label' => $label,
                'type' => $type,
                'placeholder' => trim((string) ($item['placeholder'] ?? '')),
            ];

            if ($type === 'choice') {
                $options = $this->normalizeStringList($item['options'] ?? []);
                if ($options === []) {
                    $options = ['oui', 'non'];
                }
                $question['options'] = array_slice($options, 0, 6);
            }

            $questions[] = $question;
            if (\count($questions) >= 5) {
                break;
            }
        }

        return $questions;
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
        $items = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $items[] = $item;
        }

        return array_values(array_unique($items));
    }

    private function normalizeRiskLevel(string $riskLevel): string
    {
        $riskLevel = strtolower(trim($riskLevel));
        if (!in_array($riskLevel, ['low', 'medium', 'high'], true)) {
            return 'medium';
        }

        return $riskLevel;
    }

    private function normalizeGeminiModelName(string $model): string
    {
        $model = trim($model);
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

    private function callProvider(string $provider, string $systemPrompt, string $userPrompt, bool $jsonOutput): ?string
    {
        return match ($provider) {
            'openrouter' => $this->callOpenRouter($systemPrompt, $userPrompt),
            'openai' => $this->callOpenAi($systemPrompt, $userPrompt, $jsonOutput),
            'gemini' => $this->callGemini($systemPrompt, $userPrompt),
            default => null,
        };
    }

    private function callOpenAi(string $systemPrompt, string $userPrompt, bool $jsonOutput): ?string
    {
        $payload = [
            'model' => $this->openAiModel !== '' ? $this->openAiModel : 'gpt-4o-mini',
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];
        if ($jsonOutput) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = $this->httpClient->request('POST', self::OPENAI_API_URL, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . (string) $this->openAiApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $data = $response->toArray(false);
        if (!is_array($data) || !isset($data['choices'][0]['message']['content'])) {
            return null;
        }

        $content = $data['choices'][0]['message']['content'];
        return is_string($content) ? $content : null;
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
            return null;
        }

        $data = $response->toArray(false);
        if (!is_array($data) || !isset($data['choices'][0]['message']['content'])) {
            return null;
        }

        $content = $data['choices'][0]['message']['content'];
        return is_string($content) ? $content : null;
    }

    private function callGemini(string $systemPrompt, string $userPrompt): ?string
    {
        $candidates = array_values(array_unique(array_filter([
            $this->normalizeGeminiModelName($this->geminiModel),
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
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [[
                            'text' => $systemPrompt . "\n\nDATA:\n" . $userPrompt,
                        ]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'responseMimeType' => 'application/json',
                    ],
                ],
            ]);

            $status = $response->getStatusCode();
            if ($status >= 400) {
                if ($status === 404) {
                    continue;
                }
                return null;
            }

            $data = $response->toArray(false);
            if (!is_array($data) || !isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                continue;
            }

            $text = $data['candidates'][0]['content']['parts'][0]['text'];
            if (is_string($text) && trim($text) !== '') {
                return $text;
            }
        }

        return null;
    }
}
