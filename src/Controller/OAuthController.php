<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OAuthController extends AbstractController
{
    private const ALLOWED_ROLES = ['ROLE_PATIENT', 'ROLE_COACH', 'ROLE_NUTRITIONNISTE'];

    private function normalizeRequestedRole(?string $role): string
    {
        $role = (string) ($role ?? '');
        return in_array($role, self::ALLOWED_ROLES, true) ? $role : 'ROLE_PATIENT';
    }

    #[Route('/connect/google', name: 'connect_google_start')]
    public function connectGoogle(Request $request, ClientRegistry $clientRegistry): Response
    {
        $clientId = $this->readEnv('OAUTH_GOOGLE_CLIENT_ID');
        $clientSecret = $this->readEnv('OAUTH_GOOGLE_CLIENT_SECRET');
        if ($clientId === '' || $clientSecret === '') {
            $this->addFlash('danger', 'Google OAuth non configure. Ajoutez OAUTH_GOOGLE_CLIENT_ID et OAUTH_GOOGLE_CLIENT_SECRET.');
            return $this->redirectToRoute('app_login');
        }

        $requestedRole = $this->normalizeRequestedRole($request->query->get('role'));
        $request->getSession()->set('oauth_requested_role', $requestedRole);

        $redirectUri = $request->getSchemeAndHttpHost() . '/connect/google/check';

        return $clientRegistry
            ->getClient('google_main')
            ->redirect(['email', 'profile'], ['redirect_uri' => $redirectUri]);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectGoogleCheck(): never
    {
        throw new \LogicException('Google check route is handled by the firewall.');
    }

    private function readEnv(string $key): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_string($value) ? trim($value) : '';
    }
}
