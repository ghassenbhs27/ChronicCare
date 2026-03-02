<?php
namespace App\Controller;

use App\Entity\MedicalDossier;
use App\Entity\MedicalDossierAuditLog;
use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\MedicalDossierAiService;
use App\Service\MedicalDossierAuditService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SecurityController extends BaseController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser() && $this->isGranted('IS_AUTHENTICATED_FULLY')) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('admin_dashboard');
            }
            if ($this->isGranted('ROLE_COACH') || $this->isGranted('ROLE_NUTRITIONNISTE') || $this->isGranted('ROLE_PATIENT')) {
                return $this->redirectToRoute('app_statistics');
            }
            return $this->redirectToRoute('index');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastEmail = $authenticationUtils->getLastUsername();
        $googleEnabled = $this->readEnv('OAUTH_GOOGLE_CLIENT_ID') !== ''
            && $this->readEnv('OAUTH_GOOGLE_CLIENT_SECRET') !== '';

        return $this->render('front/connexion.html.twig', [
            'last_email' => $lastEmail,
            'error' => $error,
            'google_enabled' => $googleEnabled,
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
    }

    #[Route('/register/success', name: 'app_register_success', methods: ['GET'])]
    public function registerSuccess(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('index');
        }

        $email = $request->query->get('email', '');

        return $this->render('front/inscription_success.html.twig', [
            'email' => $email,
        ]);
    }

    #[Route('/register/medical-questionnaire', name: 'app_register_medical_questionnaire', methods: ['POST'])]
    public function medicalQuestionnaire(Request $request, MedicalDossierAiService $medicalDossierAiService): JsonResponse
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

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService,
        UserRepository $userRepository,
        Security $security,
        SluggerInterface $slugger,
        MedicalDossierAiService $medicalDossierAiService,
        MedicalDossierAuditService $medicalDossierAuditService
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('index');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            $requestedRole = (string) ($form->get('requestedRole')->getData() ?? 'ROLE_PATIENT');
            $medicalCondition = strtolower(trim((string) ($form->get('medicalCondition')->getData() ?? '')));
            $medicalDossierPayloadRaw = trim((string) $form->get('medicalDossierPayload')->getData());
            /** @var UploadedFile|null $photoFile */
            $photoFile = $form->get('photoProfil')->getData();

            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $isProfessionalRequest = in_array($requestedRole, ['ROLE_COACH', 'ROLE_NUTRITIONNISTE'], true);
            $isPatientRequest = $requestedRole === 'ROLE_PATIENT';
            $medicalDossierPayload = [];

            if ($isPatientRequest) {
                if ($medicalCondition === '') {
                    $form->get('medicalCondition')->addError(new FormError('Veuillez choisir votre maladie chronique.'));
                } else {
                    $user->setMedicalCondition($medicalCondition);
                }

                if ($medicalDossierPayloadRaw === '') {
                    $form->get('medicalDossierPayload')->addError(new FormError('Veuillez completer le dossier medical avec l assistant IA.'));
                } else {
                    $decoded = json_decode($medicalDossierPayloadRaw, true);
                    if (!is_array($decoded)) {
                        $form->get('medicalDossierPayload')->addError(new FormError('Format du dossier medical invalide.'));
                    } else {
                        $payloadDisease = strtolower(trim((string) ($decoded['disease'] ?? '')));
                        if ($payloadDisease !== '' && $payloadDisease !== $medicalCondition) {
                            $form->get('medicalDossierPayload')->addError(new FormError('Le dossier IA ne correspond pas a la maladie selectionnee.'));
                            $decoded = [];
                        }
                    }

                    if (\is_array($decoded) && $decoded !== []) {
                        $normalizedQuestions = $this->normalizeMedicalQuestions($decoded['questions'] ?? []);
                        $normalizedAnswers = $this->normalizeMedicalAnswers($decoded['answers'] ?? []);
                        if ($normalizedQuestions === [] || $normalizedAnswers === []) {
                            $form->get('medicalDossierPayload')->addError(new FormError('Le dossier medical IA est incomplet.'));
                        } else {
                            $medicalDossierPayload = [
                                'questions' => $normalizedQuestions,
                                'answers' => $normalizedAnswers,
                            ];
                        }
                    }
                }
            } else {
                $user->setMedicalCondition(null);
            }

            if (\count($form->getErrors(true)) > 0) {
                return $this->render('front/inscription.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $user->setRoles([$requestedRole]);
            $user->setIsActive(true);
            $user->setRequestedRole($requestedRole);
            $user->setApprovalStatus(User::APPROVAL_PENDING);
            $user->setVerificationToken(null);
            $user->setVerificationTokenExpiresAt(null);
            if ($photoFile instanceof UploadedFile) {
                $safeName = $slugger->slug(pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename = $safeName . '-' . bin2hex(random_bytes(6)) . '.' . $photoFile->guessExtension();
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profile';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }
                $photoFile->move($uploadDir, $newFilename);
                $user->setPhotoProfil($newFilename);
            }
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setUpdatedAt(new \DateTimeImmutable());

            try {
                $entityManager->persist($user);
                $entityManager->flush();

                if ($isPatientRequest && $medicalDossierPayload !== []) {
                    $answers = $this->normalizeMedicalAnswers($medicalDossierPayload['answers'] ?? []);
                    $questions = $this->normalizeMedicalQuestions($medicalDossierPayload['questions'] ?? []);
                    if ($answers !== [] && $questions !== []) {
                        $analysis = $medicalDossierAiService->analyzeEmergencyRisk(
                            $user->getMedicalCondition() ?? 'autre',
                            $answers
                        );

                        $dossier = (new MedicalDossier())
                            ->setUser($user)
                            ->setCreatedAt(new \DateTimeImmutable())
                            ->setUpdatedAt(new \DateTimeImmutable());
                        $dossier->upsertDiseaseRecord(
                            disease: $user->getMedicalCondition() ?? 'autre',
                            questionnaire: $questions,
                            answers: $answers,
                            analysis: $analysis,
                            source: 'registration'
                        );

                        $entityManager->persist($dossier);
                        $entityManager->flush();

                        $medicalDossierAuditService->log(
                            dossier: $dossier,
                            actor: $user,
                            action: MedicalDossierAuditLog::ACTION_CREATION,
                            request: $request,
                            targetDisease: $user->getMedicalCondition() ?? 'autre',
                            changedFields: [
                                'questions_count' => count($questions),
                                'answers_count' => count($answers),
                                'risk_level' => (string) ($analysis['risk_level'] ?? 'unknown'),
                                'emergency_possible' => (bool) ($analysis['emergency_possible'] ?? false),
                            ],
                            context: ['route' => 'app_register', 'mode' => (string) ($analysis['mode'] ?? 'FALLBACK')]
                        );
                    }
                }

                if ($isProfessionalRequest) {
                    $notificationService->sendAdminApprovalRequest($user, $userRepository->findActiveAdminEmails());
                    $this->addFlash('info', 'Votre demande professionnelle a ete envoyee aux administrateurs pour approbation.');
                }

                $this->addFlash('success', 'Inscription terminee. Bienvenue sur Chronic Care.');

                return $security->login($user, 'form_login', 'main') ?? $this->redirectToRoute('index');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            }
        }

        return $this->render('front/inscription.html.twig', [
            'form' => $form->createView(),
        ]);
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

    private function readEnv(string $key): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_string($value) ? trim($value) : '';
    }
}
