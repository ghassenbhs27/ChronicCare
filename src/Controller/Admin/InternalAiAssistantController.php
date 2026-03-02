<?php

namespace App\Controller\Admin;

use App\Service\InternalAiAssistantService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ai-assistant', name: 'admin_ai_assistant_')]
#[IsGranted('ROLE_ADMIN')]
class InternalAiAssistantController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        InternalAiAssistantService $assistantService
    ): Response
    {
        $mode = (string) $request->request->get('mode', $request->query->get('mode', 'local'));
        $useExternalLlm = $mode === 'llm';

        $question = (string) $request->request->get(
            'question',
            $request->query->get('q', 'Quels sont les utilisateurs a risque cette semaine ?')
        );

        $result = null;
        if ($request->isMethod('POST')) {
            $result = $assistantService->answer($question, $useExternalLlm);
        } elseif ($request->query->has('q')) {
            $result = $assistantService->answer($question, $useExternalLlm);
        }

        return $this->render('back/ai_assistant/index.html.twig', [
            'question' => $question,
            'result' => $result,
            'mode' => $mode,
        ]);
    }
}
