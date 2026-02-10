<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Force le contexte du routeur à utiliser APP_URL pour que les liens
 * générés dans les emails (vérification, reset mdp) pointent vers l'URL publique (ngrok, prod).
 */
class RouterContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly ?string $appUrl = null
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 32],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $this->appUrl === null || $this->appUrl === '') {
            return;
        }

        $parts = parse_url($this->appUrl);
        if ($parts === false) {
            return;
        }

        $context = $this->router->getContext();
        if (isset($parts['scheme'])) {
            $context->setScheme($parts['scheme']);
        }
        if (isset($parts['host'])) {
            $context->setHost($parts['host']);
        }
        if (isset($parts['port'])) {
            if (($parts['scheme'] ?? 'http') === 'https') {
                $context->setHttpsPort((int) $parts['port']);
            } else {
                $context->setHttpPort((int) $parts['port']);
            }
        }
    }
}
