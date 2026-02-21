<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\MedicalDossier;
use App\Entity\MedicalDossierAuditLog;
use App\Entity\User;
use App\Repository\MedicalDossierAuditLogRepository;
use App\Repository\MedicalDossierRepository;
use App\Service\MedicalDossierAiService;
use App\Service\MedicalDossierAuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/patient/medical-dossier', name: 'patient_medical_dossier_')]
#[IsGranted('ROLE_PATIENT')]
class MedicalDossierController extends AbstractController
{
    #[Route('', name: 'show', methods: ['GET'])]
    public function show(
        Request $request,
        MedicalDossierRepository $medicalDossierRepository,
        MedicalDossierAuditLogRepository $medicalDossierAuditLogRepository,
        MedicalDossierAuditService $medicalDossierAuditService,
        EntityManagerInterface $entityManager,
        MedicalDossierAiService $medicalDossierAiService
    ): Response {
        $user = $this->getAuthenticatedUser();
        $dossier = $medicalDossierRepository->findOneByUser($user);
        $auditLogs = [];

        if ($dossier !== null) {
            $before = $dossier->getDiseaseHistory();
            $dossier->ensureDiseaseHistoryInitialized('legacy');
            if ($before !== $dossier->getDiseaseHistory()) {
                $entityManager->flush();
            }

            $medicalDossierAuditService->log(
                dossier: $dossier,
                actor: $user,
                action: MedicalDossierAuditLog::ACTION_CONSULTATION,
                request: $request,
                targetDisease: $dossier->getDisease(),
                context: ['route' => 'patient_medical_dossier_show', 'channel' => 'patient']
            );

            $auditLogs = $medicalDossierAuditLogRepository->findRecentForDossier($dossier, 120);
        }

        return $this->render('front/medical_dossier/show.html.twig', [
            'dossier' => $dossier,
            'audit_logs' => $auditLogs,
            'supported_diseases' => $medicalDossierAiService->getSupportedDiseases(),
        ]);
    }

    #[Route('/questionnaire', name: 'questionnaire', methods: ['POST'])]
    public function questionnaire(Request $request, MedicalDossierAiService $medicalDossierAiService): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        $disease = '';
        if (is_array($payload)) {
            $disease = trim((string) ($payload['disease'] ?? ''));
        }

        if ($disease === '') {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Maladie manquante.',
            ], 400);
        }

        $questionnaire = $medicalDossierAiService->buildQuestionnaire($disease);

        return new JsonResponse(array_merge(['ok' => true], $questionnaire));
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function save(
        Request $request,
        MedicalDossierRepository $medicalDossierRepository,
        MedicalDossierAiService $medicalDossierAiService,
        MedicalDossierAuditService $medicalDossierAuditService,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('patient_medical_dossier_save', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('patient_medical_dossier_show');
        }

        $user = $this->getAuthenticatedUser();
        $selectedDisease = strtolower(trim((string) $request->request->get('medical_condition', '')));
        $payloadRaw = trim((string) $request->request->get('medical_dossier_payload', ''));
        if ($selectedDisease === '') {
            $this->addFlash('error', 'Veuillez choisir une maladie.');

            return $this->redirectToRoute('patient_medical_dossier_show');
        }

        if ($payloadRaw === '') {
            $this->addFlash('error', 'Veuillez completer le formulaire IA avant de sauvegarder.');

            return $this->redirectToRoute('patient_medical_dossier_show');
        }

        $decoded = json_decode($payloadRaw, true);
        if (!is_array($decoded)) {
            $this->addFlash('error', 'Format du dossier IA invalide.');

            return $this->redirectToRoute('patient_medical_dossier_show');
        }

        $payloadDisease = strtolower(trim((string) ($decoded['disease'] ?? '')));
        if ($payloadDisease !== '' && $payloadDisease !== $selectedDisease) {
            $this->addFlash('error', 'Le dossier IA ne correspond pas a la maladie selectionnee.');

            return $this->redirectToRoute('patient_medical_dossier_show');
        }

        $questions = $this->normalizeMedicalQuestions($decoded['questions'] ?? []);
        $answers = $this->normalizeMedicalAnswers($decoded['answers'] ?? []);
        if ($questions === [] || $answers === []) {
            $this->addFlash('error', 'Le questionnaire IA est incomplet.');

            return $this->redirectToRoute('patient_medical_dossier_show');
        }

        $analysis = $medicalDossierAiService->analyzeEmergencyRisk($selectedDisease, $answers);

        $dossier = $medicalDossierRepository->findOneByUser($user);
        if ($dossier === null) {
            $dossier = (new MedicalDossier())
                ->setUser($user)
                ->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($dossier);
        }

        $beforeDiseases = $dossier->getKnownDiseases();
        $isNewDisease = $dossier->upsertDiseaseRecord(
            disease: $selectedDisease,
            questionnaire: $questions,
            answers: $answers,
            analysis: $analysis,
            source: 'patient_portal'
        );

        $user->setMedicalCondition($selectedDisease);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->flush();

        $medicalDossierAuditService->log(
            dossier: $dossier,
            actor: $user,
            action: $isNewDisease ? MedicalDossierAuditLog::ACTION_AJOUT_MALADIE : MedicalDossierAuditLog::ACTION_MODIFICATION,
            request: $request,
            targetDisease: $selectedDisease,
            changedFields: [
                'questions_count' => count($questions),
                'answers_count' => count($answers),
                'risk_level' => (string) ($analysis['risk_level'] ?? 'unknown'),
                'emergency_possible' => (bool) ($analysis['emergency_possible'] ?? false),
                'diseases_before' => $beforeDiseases,
                'diseases_after' => $dossier->getKnownDiseases(),
            ],
            context: [
                'route' => 'patient_medical_dossier_save',
                'mode' => (string) ($analysis['mode'] ?? 'FALLBACK'),
            ]
        );

        $this->addFlash(
            'success',
            $isNewDisease
                ? 'Nouvelle maladie ajoutee et analysee avec succes.'
                : 'Dossier medical mis a jour avec succes.'
        );

        return $this->redirectToRoute('patient_medical_dossier_show');
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /**
     * @param mixed $raw
     * @return array<int, array{question:string,answer:string}>
     */
    private function normalizeMedicalAnswers(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $answers = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }

            $answers[] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        return $answers;
    }

    /**
     * @param mixed $raw
     * @return array<int, array<string, mixed>>
     */
    private function normalizeMedicalQuestions(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $questions = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $question = [
                'id' => trim((string) ($item['id'] ?? '')),
                'label' => $label,
                'type' => trim((string) ($item['type'] ?? 'text')),
            ];

            $placeholder = trim((string) ($item['placeholder'] ?? ''));
            if ($placeholder !== '') {
                $question['placeholder'] = $placeholder;
            }

            if (is_array($item['options'] ?? null)) {
                $options = $this->normalizeStringList($item['options']);
                if ($options !== []) {
                    $question['options'] = $options;
                }
            }

            $questions[] = $question;
        }

        return $questions;
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $item) {
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
}
