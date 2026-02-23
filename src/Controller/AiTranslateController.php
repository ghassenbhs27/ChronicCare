<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiTranslateController extends AbstractController
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    #[Route('/ai/translate', name: 'ai_translate', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function translate(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['ok' => false], 401);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $text = trim((string) ($payload['text'] ?? ''));
        $target = trim((string) ($payload['target'] ?? 'fr'));
        if ($text === '') {
            return new JsonResponse(['ok' => false, 'error' => 'Texte vide.'], 400);
        }

        $translated = $this->translateWithGoogle($text, $target);
        if ($translated === null) {
            return new JsonResponse([
                'ok' => true,
                'translatedText' => $text,
                'fallback' => true,
            ]);
        }

        return new JsonResponse([
            'ok' => true,
            'translatedText' => $translated,
            'fallback' => false,
        ]);
    }

    private function translateWithGoogle(string $text, string $target): ?string
    {
        $lang = strtolower(explode('-', $target)[0] ?? 'fr');
        try {
            $response = $this->httpClient->request('GET', 'https://translate.googleapis.com/translate_a/single', [
                'query' => [
                    'client' => 'gtx',
                    'sl' => 'auto',
                    'tl' => $lang,
                    'dt' => 't',
                    'q' => $text,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray(false);
            if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
                return null;
            }

            $out = '';
            foreach ($data[0] as $segment) {
                if (is_array($segment) && isset($segment[0])) {
                    $out .= (string) $segment[0];
                }
            }

            $out = trim($out);
            return $out !== '' ? $out : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

