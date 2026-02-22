<?php

namespace App\Controller;

use App\Service\TranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/translation')]
class TranslationController extends AbstractController
{
    public function __construct(
        private TranslationService $translationService
    ) {
    }

    /**
     * Traduit un texte
     * 
     * POST /api/translation/translate
     * Body: { "text": "...", "sourceLang": "fr", "targetLang": "en" }
     */
    #[Route('/translate', name: 'api_translation_translate', methods: ['POST'])]
    public function translate(Request $request): JsonResponse
    {
        // Récupérer les données JSON
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid JSON payload'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation
        $text = $data['text'] ?? '';
        $sourceLang = $data['sourceLang'] ?? 'fr';
        $targetLang = $data['targetLang'] ?? 'en';

        if (empty(trim($text))) {
            return $this->json([
                'success' => false,
                'error' => 'Le texte à traduire est requis.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Valider les codes de langue (ISO 639-1)
        $validLangs = ['fr', 'en', 'es', 'de', 'it', 'pt', 'nl', 'pl', 'ru', 'ja', 'zh', 'ar'];
        if (!in_array($sourceLang, $validLangs) || !in_array($targetLang, $validLangs)) {
            return $this->json([
                'success' => false,
                'error' => 'Code de langue invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Appeler le service de traduction
        $result = $this->translationService->translate($text, $sourceLang, $targetLang);

        // Retourner la réponse
        if ($result['success']) {
            return $this->json([
                'success' => true,
                'translatedText' => $result['translatedText'],
                'sourceLang' => $sourceLang,
                'targetLang' => $targetLang,
                'cached' => $result['cached'] ?? false
            ]);
        } else {
            return $this->json([
                'success' => false,
                'error' => $result['error'] ?? 'Erreur de traduction inconnue.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Détecte la langue d'un texte
     * 
     * POST /api/translation/detect
     * Body: { "text": "..." }
     */
    #[Route('/detect', name: 'api_translation_detect', methods: ['POST'])]
    public function detectLanguage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid JSON payload'
            ], Response::HTTP_BAD_REQUEST);
        }

        $text = $data['text'] ?? '';

        if (empty(trim($text))) {
            return $this->json([
                'success' => false,
                'error' => 'Le texte est requis.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $detectedLang = $this->translationService->detectLanguage($text);

        return $this->json([
            'success' => true,
            'detectedLanguage' => $detectedLang
        ]);
    }

    /**
     * Obtient des informations sur l'API de traduction
     * 
     * GET /api/translation/info
     */
    #[Route('/info', name: 'api_translation_info', methods: ['GET'])]
    public function getApiInfo(): JsonResponse
    {
        $info = $this->translationService->getApiInfo();

        return $this->json([
            'success' => true,
            'info' => $info
        ]);
    }
}