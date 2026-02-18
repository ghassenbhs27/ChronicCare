<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class OAuthAuthenticator extends OAuth2Authenticator
{
    private const ALLOWED_ROLES = ['ROLE_PATIENT', 'ROLE_COACH', 'ROLE_NUTRITIONNISTE'];

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RequestStack $requestStack
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return (string) $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $client = $this->clientRegistry->getClient('google_main');
        $appUrl = rtrim((string) ($_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? ''), '/');
        $redirectUri = ($appUrl !== '' ? $appUrl : $request->getSchemeAndHttpHost()) . '/connect/google/check';
        $accessToken = $this->fetchAccessToken($client, ['redirect_uri' => $redirectUri]);
        $requestedRole = $this->extractRequestedRole();

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), fn () => $this->loadOrCreateUser($accessToken, $requestedRole))
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('index'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function loadOrCreateUser(AccessToken $accessToken, string $requestedRole): User
    {
        $oauthUser = $this->clientRegistry->getClient('google_main')->fetchUserFromToken($accessToken);
        $email = $oauthUser->getEmail();

        if (!$email) {
            throw new CustomUserMessageAuthenticationException(
                'Impossible de recuperer un email depuis le fournisseur OAuth.'
            );
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user instanceof User) {
            return $this->synchronizeExistingUserRole($user, $requestedRole);
        }

        $fullName = trim((string) ($oauthUser->getName() ?? ''));
        $firstName = trim((string) ($oauthUser->getFirstName() ?? ''));
        $lastName = trim((string) ($oauthUser->getLastName() ?? ''));

        if ($firstName === '' || $lastName === '') {
            $parts = preg_split('/\s+/', $fullName) ?: [];
            $firstName = $firstName !== '' ? $firstName : (string) ($parts[0] ?? '');
            $lastName = $lastName !== '' ? $lastName : (string) ($parts[1] ?? '');
        }

        if ($firstName === '') {
            $firstName = 'Utilisateur';
        }
        if ($lastName === '') {
            $lastName = 'OAuth';
        }

        $newUser = new User();
        $newUser->setEmail($email);
        $newUser->setPrenom($firstName);
        $newUser->setNom($lastName);
        $newUser->setGenre('Homme');
        $newUser->setRoles([$requestedRole]);
        $newUser->setRequestedRole($requestedRole);
        $newUser->setApprovalStatus(
            in_array($requestedRole, ['ROLE_COACH', 'ROLE_NUTRITIONNISTE'], true)
                ? User::APPROVAL_PENDING
                : User::APPROVAL_APPROVED
        );
        $newUser->setIsActive(true);
        $newUser->setCreatedAt(new \DateTimeImmutable());
        $newUser->setUpdatedAt(new \DateTimeImmutable());
        $newUser->setVerificationToken(null);
        $newUser->setVerificationTokenExpiresAt(null);

        $randomPassword = bin2hex(random_bytes(32));
        $newUser->setPassword($this->passwordHasher->hashPassword($newUser, $randomPassword));

        $this->entityManager->persist($newUser);
        $this->entityManager->flush();

        return $newUser;
    }

    private function synchronizeExistingUserRole(User $user, string $requestedRole): User
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $user;
        }

        $currentRole = (string) ($user->getRequestedRole() ?? 'ROLE_PATIENT');
        if ($currentRole === $requestedRole) {
            return $user;
        }

        $user->setRequestedRole($requestedRole);
        $user->setRoles([$requestedRole]);
        $user->setApprovalStatus(
            in_array($requestedRole, ['ROLE_COACH', 'ROLE_NUTRITIONNISTE'], true)
                ? User::APPROVAL_PENDING
                : User::APPROVAL_APPROVED
        );
        $user->setIsActive(true);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $user;
    }

    private function extractRequestedRole(): string
    {
        $session = $this->requestStack->getSession();
        $role = (string) ($session?->get('oauth_requested_role', 'ROLE_PATIENT') ?? 'ROLE_PATIENT');
        $session?->remove('oauth_requested_role');

        return in_array($role, self::ALLOWED_ROLES, true) ? $role : 'ROLE_PATIENT';
    }
}
