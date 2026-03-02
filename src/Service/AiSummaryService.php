<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class AiSummaryService
{
    private const GROQ_API_URL = 'https://api.groq.com/openai/v1/chat/completions';

    /**
     *  Models optimized for AI summaries
     * Order: fast → powerful fallback
     */
    private const MODELS = [
        'openai/gpt-oss-20b',   //  Primary (fast + cheap)
        'openai/gpt-oss-120b',  //  Fallback (strong but heavy)
    ];

    private const MAX_CONTENT_LENGTH = 2000;
    private const TIMEOUT = 30;
    private const MAX_FALLBACK_SENTENCES = 3;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $groqApiKey
    ) {}

    /**
     * Generate AI summary (FR / EN)
     */
    public function generateSummary(string $content, string $language = 'fr'): ?string
    {
        $cleanContent = $this->prepareContent($content);

        if (empty($cleanContent)) {
            $this->logger->warning('Empty content provided for summary generation');
            return null;
        }

        if (empty($this->groqApiKey)) {
            $this->logger->warning('Groq API key not configured, using fallback');
            return $this->generateFallbackSummary($cleanContent, $language);
        }

        foreach (self::MODELS as $model) {
            $summary = $this->generateWithGroq($model, $cleanContent, $language);

            if ($this->isGoodSummary($summary, $cleanContent)) {
                $this->logger->info(' Summary generated successfully', [
                    'model' => $model,
                    'language' => $language,
                    'length' => mb_strlen($summary),
                ]);
                return $summary;
            }

            $this->logger->warning("Model $model failed quality check, trying next model");
        }

        // All API attempts failed, use fallback
        $this->logger->warning('All Groq models failed, using fallback summary');
        return $this->generateFallbackSummary($cleanContent, $language);
    }

    /**
     *  NEW: Generate fallback summary when API fails
     * Extracts first N sentences from content
     * WARNING: Does NOT translate - returns summary in original text language
     */
    public function generateFallbackSummary(string $content, string $language = 'fr'): string
    {
        $this->logger->info('🔄 Generating fallback summary', [
            'language' => $language,
            'content_length' => mb_strlen($content),
        ]);

        // Clean and normalize content
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', trim($content));

        // Split into sentences (supports French and English punctuation)
        $sentences = preg_split('/(?<=[.!?])\s+(?=[A-ZÀ-Ÿ])/', $content, -1, PREG_SPLIT_NO_EMPTY);
        
        if (empty($sentences)) {
            // If no sentences found, just truncate
            $summary = mb_substr($content, 0, 200) . '...';
        } else {
            // Take first N sentences
            $selectedSentences = array_slice($sentences, 0, self::MAX_FALLBACK_SENTENCES);
            $summary = implode(' ', $selectedSentences);
        }

        // Ensure proper ending
        if (!preg_match('/[.!?]$/', $summary)) {
            $summary .= '.';
        }

        // Add prefix based on requested language (note: content may not match)
        $prefix = $language === 'fr' 
            ? 'Résumé automatique : ' 
            : 'Automatic summary: ';

        $fallbackSummary = $prefix . $summary;

        $this->logger->warning(' Fallback summary generated - content is in ORIGINAL language, NOT translated', [
            'requested_language' => $language,
            'sentences_used' => count($sentences ?? []),
            'summary_length' => mb_strlen($fallbackSummary),
        ]);

        return $fallbackSummary;
    }

    /**
     * Call Groq API
     */
    private function generateWithGroq(string $model, string $content, string $language): ?string
    {
        try {
            if ($language === 'fr') {
                $systemMessage = 'Tu es un assistant expert en résumés clairs et concis. Tu reformules toujours avec tes propres mots et tu ne copies jamais le texte original.';
                $userPrompt = "Résume ce texte en français en 2 ou 3 phrases courtes et informatives.\n\nTexte:\n$content";
            } else {
                $systemMessage = 'You are an expert assistant for clear and concise summaries. You always rephrase in your own words and never copy the original text.';
                $userPrompt = "Summarize this text in English in 2-3 short, informative sentences.\n\nText:\n$content";
            }

            $this->logger->info("📡 Calling Groq API", [
                'model' => $model,
                'language' => $language,
            ]);

            $response = $this->httpClient->request('POST', self::GROQ_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemMessage],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.3,
                    'max_tokens'  => 250,
                    'top_p'       => 1,
                ],
                'timeout' => self::TIMEOUT,
            ]);

            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                $this->logger->error("Groq API returned non-200 status", [
                    'status_code' => $statusCode,
                    'model' => $model,
                ]);
                return null;
            }

            $data = $response->toArray();

            if (!isset($data['choices'][0]['message']['content'])) {
                $this->logger->error('Invalid Groq API response structure', [
                    'model' => $model,
                    'response_keys' => array_keys($data),
                ]);
                return null;
            }

            return $this->cleanSummary($data['choices'][0]['message']['content']);

        } catch (\Symfony\Component\HttpClient\Exception\TimeoutException $e) {
    $this->logger->error('Groq API timeout', [
        'model' => $model,
        'timeout' => self::TIMEOUT,
        'error' => $e->getMessage(),
    ]);
    return null;
} catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
            $this->logger->error('Groq API transport error', [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);
            return null;
        } catch (\Throwable $e) {
            $this->logger->error('Groq API unexpected error', [
                'model' => $model,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Clean summary text
     */
    private function cleanSummary(string $summary): string
    {
        // Remove HTML tags
        $summary = strip_tags($summary);
        
        // Normalize whitespace
        $summary = preg_replace('/\s+/', ' ', $summary);
        
        // Trim quotes and whitespace
        $summary = trim($summary, " \t\n\r\0\x0B\"'«»");

        // Ensure proper ending punctuation
        if (!preg_match('/[.!?]$/', $summary)) {
            $summary .= '.';
        }

        return $summary;
    }

    /**
     * Check summary quality
     */
    private function isGoodSummary(?string $summary, string $original): bool
    {
        if (!$summary) {
            return false;
        }

        $lenSummary  = mb_strlen($summary);
        $lenOriginal = mb_strlen($original);

        // Summary should be between 50 and 85% of original length
        if ($lenSummary < 50 || $lenSummary >= $lenOriginal * 0.85) {
            $this->logger->debug('Summary failed length check', [
                'summary_length' => $lenSummary,
                'original_length' => $lenOriginal,
                'ratio' => $lenOriginal > 0 ? round($lenSummary / $lenOriginal, 2) : 0,
            ]);
            return false;
        }

        // Check similarity - should not be too similar to original (anti-plagiarism)
        similar_text(mb_strtolower($summary), mb_strtolower($original), $similarity);
        
        if ($similarity >= 85) {
            $this->logger->debug('Summary failed similarity check (too similar to original)', [
                'similarity' => round($similarity, 2),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Prepare content before AI
     */
    private function prepareContent(string $content): string
    {
        // Remove HTML tags
        $content = strip_tags($content);
        
        // Normalize whitespace
        $content = preg_replace('/\s+/', ' ', trim($content));

        // Truncate if too long
        if (mb_strlen($content) > self::MAX_CONTENT_LENGTH) {
            $content = mb_substr($content, 0, self::MAX_CONTENT_LENGTH) . '...';
            $this->logger->debug('Content truncated', [
                'original_length' => mb_strlen($content),
                'truncated_to' => self::MAX_CONTENT_LENGTH,
            ]);
        }

        return $content;
    }

    /**
     * Check if content is long enough for summary
     */
    public function canGenerateSummary(string $content): bool
    {
        $cleanContent = strip_tags($content);
        $length = mb_strlen(trim($cleanContent));
        
        $canGenerate = $length >= 100;
        
        if (!$canGenerate) {
            $this->logger->debug('Content too short for summary', [
                'length' => $length,
                'minimum_required' => 100,
            ]);
        }
        
        return $canGenerate;
    }
}