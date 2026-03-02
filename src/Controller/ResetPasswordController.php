<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\ResetPasswordCode;
use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordCodeFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\ResetPasswordCodeRepository;
use App\Service\SecurityMonitoringService;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    private const OTP_TTL_SECONDS = 900;

    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager,
        private ResetPasswordCodeRepository $resetPasswordCodeRepository,
        private LoggerInterface $logger
    ) {
    }

    #[Route('', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        MailerInterface $mailer,
        SecurityMonitoringService $securityMonitoringService
    ): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();
            return $this->processSendingPasswordResetEmail($request, $email, $mailer, $securityMonitoringService);
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
    public function verify(Request $request): Response
    {
        $form = $this->createForm(ResetPasswordCodeFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = mb_strtolower(trim((string) $form->get('email')->getData()));
            $code = trim((string) $form->get('code')->getData());

            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if (!$user instanceof User) {
                $this->addFlash('reset_password_error', 'Code invalide ou expire.');

                return $this->redirectToRoute('app_reset_password_verify');
            }

            try {
                $otp = $this->resetPasswordCodeRepository->findValidCodeForUserAndHash(
                    $user,
                    $this->hashOtpCode($code),
                    new \DateTimeImmutable()
                );
            } catch (\Throwable $e) {
                if ($this->isMissingResetPasswordCodeTable($e)) {
                    $this->logger->warning('OTP verification fallback to classic reset link (missing table).', [
                        'error' => $e->getMessage(),
                    ]);

                    $this->addFlash('reset_password_error', 'Verification par code indisponible. Utilisez le lien recu par email.');

                    return $this->redirectToRoute('app_forgot_password_request');
                }

                throw $e;
            }

            if (!$otp instanceof ResetPasswordCode) {
                $this->addFlash('reset_password_error', 'Code invalide ou expire.');

                return $this->redirectToRoute('app_reset_password_verify');
            }

            $otp->setUsedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->storeTokenInSession((string) $otp->getResetToken());
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
        Request $request,
        string $emailFormData,
        MailerInterface $mailer,
        SecurityMonitoringService $securityMonitoringService
    ): RedirectResponse {
        $emailFormData = trim($emailFormData);

        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $emailFormData,
        ]);

        $securityMonitoringService->trackResetPasswordRequest(
            request: $request,
            user: $user instanceof User ? $user : null,
            identifier: $emailFormData
        );

        if (!$user) {
            if ($this->getParameter('kernel.environment') === 'dev') {
                $this->addFlash('reset_password_info', sprintf('Aucun compte trouve pour: %s', $emailFormData));
            }

            $this->logger->warning('Password reset requested for unknown email.', [
                'email' => $emailFormData,
                'ip' => $request->getClientIp(),
            ]);

            return $this->redirectToRoute('app_check_email');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (TooManyPasswordRequestsException $e) {
            $retryAfter = max(1, (int) ceil($e->getRetryAfter() / 60));
            $this->addFlash(
                'reset_password_error',
                sprintf('Un email a deja ete envoye. Reessayez dans environ %d minute(s).', $retryAfter)
            );

            return $this->redirectToRoute('app_check_email');
        } catch (ResetPasswordExceptionInterface $e) {
            return $this->redirectToRoute('app_check_email');
        }

        $resetPath = $this->generateUrl('app_reset_password', ['token' => $resetToken->getToken()]);
        $resetUrl = rtrim($request->getSchemeAndHttpHost(), '/') . $resetPath;
        $otpCode = null;

        try {
            $this->resetPasswordCodeRepository->invalidateActiveCodesForUser($user);

            $otpCode = $this->generateSixDigitOtp();
            $otpEntity = (new ResetPasswordCode())
                ->setUser($user)
                ->setCodeHash($this->hashOtpCode($otpCode))
                ->setResetToken($resetToken->getToken())
                ->setCreatedAt(new \DateTimeImmutable())
                ->setExpiresAt(new \DateTimeImmutable(sprintf('+%d seconds', self::OTP_TTL_SECONDS)));

            $this->entityManager->persist($otpEntity);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            if ($this->isMissingResetPasswordCodeTable($e)) {
                $this->logger->warning('reset_password_code table missing. Falling back to classic reset link mode.', [
                    'error' => $e->getMessage(),
                ]);
                $otpCode = null;
            } else {
                throw $e;
            }
        }

        $email = (new TemplatedEmail())
            ->from(new Address((string) $this->getParameter('mailer_from_address'), (string) $this->getParameter('mailer_from_name')))
            ->to((string) $user->getEmail())
            ->subject('Reinitialisation de votre mot de passe - Chronic Care')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
                'user' => $user,
                'resetUrl' => $resetUrl,
                'otpCode' => $otpCode,
            ]);

        try {
            $mailer->send($email);
            $this->logger->info('Password reset email sent.', [
                'email' => (string) $user->getEmail(),
                'ip' => $request->getClientIp(),
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Password reset email transport failed.', [
                'email' => (string) $user->getEmail(),
                'error' => $e->getMessage(),
            ]);
            $this->addFlash('reset_password_error', 'Email sending failed. Please try again later.');
        }

        $this->setTokenObjectInSession($resetToken);
        return $this->redirectToRoute('app_check_email');
    }

    private function generateSixDigitOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function hashOtpCode(string $otpCode): string
    {
        return hash('sha256', $otpCode);
    }

    private function isMissingResetPasswordCodeTable(\Throwable $exception): bool
    {
        if ($exception instanceof TableNotFoundException) {
            return true;
        }

        if ($exception instanceof DbalException && str_contains(mb_strtolower($exception->getMessage()), 'reset_password_code')) {
            return true;
        }

        return $exception->getPrevious() instanceof TableNotFoundException;
    }
}
