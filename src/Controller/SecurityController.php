<?php
namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
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
        if ($this->getUser()) {
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

        return $this->render('front/connexion.html.twig', [
            'last_email' => $lastEmail,
            'error' => $error,
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

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService,
        UserRepository $userRepository,
        Security $security,
        SluggerInterface $slugger
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
            /** @var UploadedFile|null $photoFile */
            $photoFile = $form->get('photoProfil')->getData();

            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $isProfessionalRequest = in_array($requestedRole, ['ROLE_COACH', 'ROLE_NUTRITIONNISTE'], true);

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
}
