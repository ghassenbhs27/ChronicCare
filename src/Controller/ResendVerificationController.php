<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/resend-verification')]
class ResendVerificationController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_resend_verification', methods: ['GET', 'POST'])]
    public function resend(
        Request $request,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('index');
        }

        $email = $request->request->get('email') ?? $request->query->get('email', '');
        $sent = false;
        $error = null;

        if ($request->isMethod('POST') && $email) {
            $user = $this->userRepository->findOneBy(['email' => trim($email)]);

            if (!$user) {
                $error = 'Aucun compte trouvé avec cette adresse email.';
            } elseif ($user->isActive()) {
                $error = 'Ce compte est déjà actif. Vous pouvez vous connecter.';
            } else {
                $now = new \DateTimeImmutable();
                $token = $user->getVerificationToken();
                $expires = $user->getVerificationTokenExpiresAt();
                if (!$token || !$expires || $expires < $now) {
                    $user->setVerificationToken(bin2hex(random_bytes(32)));
                    $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('+24 hours'));
                    $this->entityManager->flush();
                }

                $baseUrl = rtrim($this->getParameter('app_url'), '/');
                $path = $urlGenerator->generate('app_verify_email', [
                    'token' => $user->getVerificationToken(),
                ]);
                $signedUrl = $baseUrl . $path;

                $emailMessage = (new TemplatedEmail())
                    ->from(new Address(
                        $this->getParameter('mailer_from_address'),
                        $this->getParameter('mailer_from_name')
                    ))
                    ->to((string) $user->getEmail())
                    ->subject('Vérifiez votre email - Chronic Care')
                    ->htmlTemplate('email/verification.html.twig')
                    ->context([
                        'user' => $user,
                        'signedUrl' => $signedUrl,
                    ]);

                try {
                    $mailer->send($emailMessage);
                    $sent = true;
                } catch (TransportExceptionInterface $e) {
                    $error = 'Impossible d\'envoyer l\'email. Vérifiez la configuration du serveur mail.';
                }
            }
        }

        return $this->render('front/resend_verification.html.twig', [
            'email' => $email,
            'sent' => $sent,
            'error' => $error,
        ]);
    }
}
