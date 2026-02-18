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

    private function appUrl(): string
    {
        $raw = (string) ($_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? '');
        return rtrim($raw, '/');
    }

    private function normalizeRequestedRole(?string $role): string
    {
        $role = (string) ($role ?? '');
        return in_array($role, self::ALLOWED_ROLES, true) ? $role : 'ROLE_PATIENT';
    }

    #[Route('/connect/google', name: 'connect_google_start')]
    public function connectGoogle(Request $request, ClientRegistry $clientRegistry): Response
    {
        $clientId = (string) ($_ENV['OAUTH_GOOGLE_CLIENT_ID'] ?? $_SERVER['OAUTH_GOOGLE_CLIENT_ID'] ?? '');
        $clientSecret = (string) ($_ENV['OAUTH_GOOGLE_CLIENT_SECRET'] ?? $_SERVER['OAUTH_GOOGLE_CLIENT_SECRET'] ?? '');
        if ($clientId === '' || $clientSecret === '') {
            $this->addFlash('danger', 'Google OAuth non configure. Ajoutez OAUTH_GOOGLE_CLIENT_ID et OAUTH_GOOGLE_CLIENT_SECRET.');
            return $this->redirectToRoute('app_login');
        }

        $appUrl = $this->appUrl();
        if ($appUrl !== '') {
            $target = parse_url($appUrl);
            $targetHost = (string) ($target['host'] ?? '');
            if ($targetHost !== '' && $request->getHost() !== $targetHost) {
                $role = (string) $request->query->get('role', '');
                $query = $role !== '' ? ('?role=' . rawurlencode($role)) : '';
                return $this->redirect($appUrl . '/connect/google' . $query);
            }
        }

        $requestedRole = $this->normalizeRequestedRole($request->query->get('role'));
        $request->getSession()->set('oauth_requested_role', $requestedRole);

        $redirectUri = ($appUrl !== '' ? $appUrl : $request->getSchemeAndHttpHost()) . '/connect/google/check';

        return $clientRegistry
            ->getClient('google_main')
            ->redirect(['email', 'profile'], ['redirect_uri' => $redirectUri]);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectGoogleCheck(): never
    {
        throw new \LogicException('Google check route is handled by the firewall.');
    }
}
