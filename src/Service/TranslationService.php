<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de traduction utilisant MyMemory Translation API
 * API gratuite : 5000 mots/jour sans clé, 10000 avec clé gratuite
 */
class TranslationService
{
    private const API_BASE_URL = 'https://api.mymemory.translated.net/get';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Traduit un texte d'une langue source vers une langue cible
     *
     * @param string $text Texte à traduire
     * @param string $sourceLang Code langue source (fr, en, etc.)
     * @param string $targetLang Code langue cible (fr, en, etc.)
     * @return array{success: bool, translatedText: string|null, error: string|null, cached: bool}
     */
    public function translate(string $text, string $sourceLang = 'fr', string $targetLang = 'en'): array
    {
        if (empty(trim($text))) {
            return [
                'success' => false,
                'translatedText' => null,
                'error' => 'Le texte à traduire est vide.',
                'cached' => false,
            ];
        }

        if ($sourceLang === $targetLang) {
            return [
                'success' => true,
                'translatedText' => $text,
                'error' => null,
                'cached' => false,
            ];
        }

        $maxLength = 3000;
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength) . '...';
        }

        try {
            $langPair = "{$sourceLang}|{$targetLang}";
            $url = self::API_BASE_URL . '?' . http_build_query([
                'q' => $text,
                'langpair' => $langPair,
            ]);

            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'ChronicCare-Forum/1.0',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray();

            if ($statusCode !== 200) {
                $this->logger->error('MyMemory API returned non-200 status', [
                    'status' => $statusCode,
                    'data' => $data,
                ]);

                return [
                    'success' => false,
                    'translatedText' => null,
                    'error' => 'Erreur API de traduction (HTTP ' . $statusCode . ')',
                    'cached' => false,
                ];
            }

            if (!isset($data['responseData']['translatedText'])) {
                $this->logger->error('Invalid MyMemory API response structure', [
                    'data' => $data,
                ]);

                return [
                    'success' => false,
                    'translatedText' => null,
                    'error' => 'Structure de réponse invalide de l\'API',
                    'cached' => false,
                ];
            }

            $translatedText = $data['responseData']['translatedText'];

            if (empty($translatedText)) {
                return [
                    'success' => false,
                    'translatedText' => null,
                    'error' => 'La traduction a échoué.',
                    'cached' => false,
                ];
            }

            return [
                'success' => true,
                'translatedText' => $translatedText,
                'error' => null,
                'cached' => false,
            ];
        } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
            $this->logger->error('Translation API transport error', [
                'exception' => $e->getMessage(),
                'text_length' => strlen($text),
            ]);

            return [
                'success' => false,
                'translatedText' => null,
                'error' => 'Erreur de connexion à l\'API de traduction.',
                'cached' => false,
            ];
        } catch (\Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface $e) {
            $this->logger->error('Translation API HTTP error', [
                'exception' => $e->getMessage(),
                'status' => $e->getResponse()->getStatusCode(),
            ]);

            return [
                'success' => false,
                'translatedText' => null,
                'error' => 'Erreur HTTP lors de la traduction.',
                'cached' => false,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Translation service error', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'translatedText' => null,
                'error' => 'Erreur inattendue lors de la traduction.',
                'cached' => false,
            ];
        }
    }

    /**
     * Traduit un texte vers l'anglais (depuis le français par défaut)
     *
     * @return array{success: bool, translatedText: string|null, error: string|null, cached: bool}
     */
    public function translateToEnglish(string $text, string $sourceLang = 'fr'): array
    {
        return $this->translate($text, $sourceLang, 'en');
    }

    /**
     * Traduit un texte vers le français (depuis l'anglais par défaut)
     *
     * @return array{success: bool, translatedText: string|null, error: string|null, cached: bool}
     */
    public function translateToFrench(string $text, string $sourceLang = 'en'): array
    {
        return $this->translate($text, $sourceLang, 'fr');
    }

    public function detectLanguage(string $text): string
    {
        $frenchWords = ['le', 'la', 'les', 'de', 'et', 'un', 'une', 'est', 'dans', 'pour', 'que', 'qui', 'par'];
        $englishWords = ['the', 'is', 'and', 'of', 'to', 'in', 'for', 'that', 'with', 'on', 'at', 'from'];

        $text = strtolower($text);

        $frenchScore = 0;
        $englishScore = 0;

        foreach ($frenchWords as $word) {
            if (str_contains($text, ' ' . $word . ' ') || str_starts_with($text, $word . ' ')) {
                $frenchScore++;
            }
        }

        foreach ($englishWords as $word) {
            if (str_contains($text, ' ' . $word . ' ') || str_starts_with($text, $word . ' ')) {
                $englishScore++;
            }
        }

        if (preg_match('/[éèêëàâäùûüôöîïç]/i', $text)) {
            $frenchScore += 2;
        }

        return $frenchScore >= $englishScore ? 'fr' : 'en';
    }

    /**
     * Obtient des informations sur les limites de l'API
     *
     * @return array{
     *   provider: string,
     *   free_tier: string,
     *   free_with_key: string,
     *   supported_languages: list<string>,
     *   rate_limit: string,
     *   max_text_length: int
     * }
     */
    public function getApiInfo(): array
    {
        return [
            'provider' => 'MyMemory Translation API',
            'free_tier' => '5000 mots/jour sans clé API',
            'free_with_key' => '10000 mots/jour avec clé gratuite',
            'supported_languages' => ['fr', 'en', 'es', 'de', 'it', 'pt', 'nl', 'pl', 'ru', 'ja', 'zh', 'ar'],
            'rate_limit' => 'Aucune limite de requêtes/seconde',
            'max_text_length' => 500,
        ];
    }
}