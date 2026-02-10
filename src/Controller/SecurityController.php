<?php
namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends BaseController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si l'utilisateur est déjà connecté
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('admin_dashboard');
            }
            if ($this->isGranted('ROLE_COACH') || $this->isGranted('ROLE_PATIENT')) {
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
        // Symfony gère le logout automatiquement via security.yaml
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
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('index');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $user->setRoles([]);
            $user->setIsActive(false);
            $user->setVerificationToken(bin2hex(random_bytes(32)));
            $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('+24 hours'));
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setUpdatedAt(new \DateTimeImmutable());

            try {
                $entityManager->persist($user);
                $entityManager->flush();

                $baseUrl = rtrim($this->getParameter('app_url'), '/');
                $path = $urlGenerator->generate('app_verify_email', [
                    'token' => $user->getVerificationToken(),
                ]);
                $signedUrl = $baseUrl . $path;

                $email = (new TemplatedEmail())
                    ->from(new Address($this->getParameter('mailer_from_address'), $this->getParameter('mailer_from_name')))
                    ->to((string) $user->getEmail())
                    ->subject('Vérifiez votre email - Chronic Care')
                    ->htmlTemplate('email/verification.html.twig')
                    ->context([
                        'user' => $user,
                        'signedUrl' => $signedUrl,
                    ]);

                try {
                    $mailer->send($email);
                } catch (TransportExceptionInterface $e) {
                    $this->addFlash('error', 'Impossible d\'envoyer l\'email de vérification. Veuillez réessayer plus tard.');
                    return $this->redirectToRoute('app_register');
                }

                return $this->redirectToRoute('app_register_success', [
                    'email' => $user->getEmail(),
                ]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            }
        }

        return $this->render('front/inscription.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}