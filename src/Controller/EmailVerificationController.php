<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/verify-email')]
class EmailVerificationController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/{token}', name: 'app_verify_email', methods: ['GET', 'POST'])]
    public function verify(Request $request, string $token): Response
    {
        $user = $this->userRepository->findOneBy([
            'verificationToken' => $token,
        ]);

        if (!$user) {
            $this->addFlash('error', 'Lien de vérification invalide ou expiré.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->getVerificationTokenExpiresAt() && $user->getVerificationTokenExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('error', 'Ce lien de vérification a expiré. Veuillez vous réinscrire ou demander un nouveau lien.');
            return $this->redirectToRoute('app_register');
        }

        if ($request->isMethod('POST')) {
            $user->setIsActive(true);
            $user->setVerificationToken(null);
            $user->setVerificationTokenExpiresAt(null);
            $this->entityManager->flush();

            return $this->render('front/verification_success.html.twig', [
                'user' => $user,
            ]);
        }

        return $this->render('front/verification_confirm.html.twig', [
            'token' => $token,
            'user' => $user,
        ]);
    }
}
