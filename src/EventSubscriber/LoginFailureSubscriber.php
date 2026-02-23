<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\SecurityMonitoringService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class LoginFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SecurityMonitoringService $securityMonitoringService,
        private readonly UserRepository $userRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $identifier = mb_strtolower(trim((string) $request->request->get('_username', '')));
        $user = null;

        if ($identifier !== '') {
            $candidate = $this->userRepository->findOneBy(['email' => $identifier]);
            if ($candidate instanceof User) {
                $user = $candidate;
            }
        }

        $this->securityMonitoringService->trackFailedLogin(
            request: $request,
            user: $user,
            identifier: $identifier !== '' ? $identifier : null
        );
    }
}
