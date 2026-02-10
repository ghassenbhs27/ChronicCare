<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => ['onLoginSuccess', -64],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user) {
            return;
        }

        $route = null;
        if (method_exists($user, 'getRoles')) {
            $roles = $user->getRoles();
            if (in_array('ROLE_ADMIN', $roles, true)) {
                $route = 'admin_dashboard';
            } elseif (in_array('ROLE_COACH', $roles, true)) {
                $route = 'app_statistics';
            } elseif (in_array('ROLE_PATIENT', $roles, true) || in_array('ROLE_USER', $roles, true)) {
                $route = 'app_statistics';
            }
        }

        if ($route !== null) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate($route)));
        }
    }
}
