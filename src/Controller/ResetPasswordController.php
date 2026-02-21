<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\ResetPasswordCode;
use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordCodeFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\ResetPasswordCodeRepository;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    private const CODE_TTL_MINUTES = 15;

    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager,
        private ResetPasswordCodeRepository $resetPasswordCodeRepository
    ) {
    }

    #[Route('', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(Request $request, MailerInterface $mailer, TranslatorInterface $translator): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();
            return $this->processSendingPasswordResetEmail($email, $mailer, $translator);
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    #[Route('/check-email', name: 'app_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        if (null === ($resetToken = $this->getTokenObjectFromSession())) {
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }

    #[Route('/verify', name: 'app_reset_password_verify', methods: ['GET', 'POST'])]
    public function verify(Request $request, TranslatorInterface $translator): Response
    {
        $form = $this->createForm(ResetPasswordCodeFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = mb_strtolower(trim((string) $form->get('email')->getData()));
            $code = (string) $form->get('code')->getData();
            try {
                $entry = $this->resetPasswordCodeRepository->findLatestActiveByEmail($email);
            } catch (TableNotFoundException) {
                $this->addFlash('reset_password_error', 'Le mode code 6 chiffres n est pas encore active. Utilisez le lien recu par email.');
                return $this->redirectToRoute('app_forgot_password_request');
            }
            if (!$entry instanceof ResetPasswordCode || !$this->isCodeValid($code, $entry->getCodeHash())) {
                $this->addFlash('reset_password_error', 'Code invalide ou expire.');
                return $this->redirectToRoute('app_reset_password_verify');
            }

            try {
                $this->resetPasswordHelper->validateTokenAndFetchUser($entry->getResetToken());
            } catch (ResetPasswordExceptionInterface $e) {
                $this->addFlash('reset_password_error', sprintf(
                    'The code is invalid or expired. %s',
                    $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
                ));

                return $this->redirectToRoute('app_reset_password_verify');
            }

            $entry->setUsedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->storeTokenInSession($entry->getResetToken());
            return $this->redirectToRoute('app_reset_password');
        }

        return $this->render('reset_password/verify.html.twig', [
            'verifyForm' => $form->createView(),
        ]);
    }

    #[Route('/reset/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        TranslatorInterface $translator,
        ?string $token = null
    ): Response {
        if ($token) {
            $this->storeTokenInSession($token);
            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('No reset code found.');
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('reset_password_error', sprintf(
                'The code is invalid or expired. %s',
                $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
            ));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->resetPasswordHelper->removeResetRequest($token);

            $plainPassword = (string) $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $this->entityManager->flush();

            $this->cleanSessionAfterReset();
            $this->addFlash('success', 'Votre mot de passe a ete modifie avec succes.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    private function processSendingPasswordResetEmail(
        string $emailFormData,
        MailerInterface $mailer,
        TranslatorInterface $translator
    ): RedirectResponse {
        $emailFormData = trim($emailFormData);

        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $emailFormData,
        ]);

        if (!$user) {
            return $this->redirectToRoute('app_check_email');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('reset_password_error', sprintf(
                'Impossible de renvoyer le code maintenant. %s',
                $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
            ));
            return $this->redirectToRoute('app_check_email');
        }
        $plainCode = $this->generateSixDigitCode();
        $codeEntry = (new ResetPasswordCode())
            ->setEmail(mb_strtolower((string) $user->getEmail()))
            ->setCodeHash($this->hashCode($plainCode))
            ->setResetToken($resetToken->getToken())
            ->setExpiresAt(new \DateTimeImmutable('+' . self::CODE_TTL_MINUTES . ' minutes'));
        try {
            $this->resetPasswordCodeRepository->deleteForEmail(mb_strtolower((string) $user->getEmail()));
            $this->entityManager->persist($codeEntry);
            $this->entityManager->flush();
        } catch (TableNotFoundException) {
            $plainCode = '';
        }

        $baseUrl = rtrim((string) ($this->getParameter('app_url') ?? ''), '/');
        $resetPath = $this->generateUrl('app_reset_password', ['token' => $resetToken->getToken()]);
        $resetUrl = $baseUrl !== ''
            ? $baseUrl . $resetPath
            : $this->generateUrl('app_reset_password', ['token' => $resetToken->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address((string) $this->getParameter('mailer_from_address'), (string) $this->getParameter('mailer_from_name')))
            ->to((string) $user->getEmail())
            ->subject('Reinitialisation de votre mot de passe - Chronic Care')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
                'user' => $user,
                'resetUrl' => $resetUrl,
                'sixDigitCode' => $plainCode,
                'codeTtlMinutes' => self::CODE_TTL_MINUTES,
            ]);

        try {
            $mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->addFlash('reset_password_error', 'Email sending failed. Please try again later.');
        }

        $this->setTokenObjectInSession($resetToken);
        return $this->redirectToRoute('app_check_email');
    }

    private function generateSixDigitCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function hashCode(string $plainCode): string
    {
        return hash_hmac('sha256', $plainCode, (string) $this->getParameter('kernel.secret'));
    }

    private function isCodeValid(string $plainCode, string $storedHash): bool
    {
        return hash_equals($storedHash, $this->hashCode($plainCode));
    }
}
