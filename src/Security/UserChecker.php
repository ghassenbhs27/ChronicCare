<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        $roles = $user->getRoles();
        $requiresEmailVerification = in_array($user->getRequestedRole(), ['ROLE_COACH', 'ROLE_NUTRITIONNISTE'], true)
            || in_array('ROLE_COACH', $roles, true)
            || in_array('ROLE_NUTRITIONNISTE', $roles, true);
        if ($requiresEmailVerification && !$user->isActive() && $user->getVerificationToken() !== null) {
            throw new CustomUserMessageAccountStatusException(
                'Veuillez verifier votre adresse email avant de vous connecter.'
            );
        }

        if ($user->isRejected()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre demande d inscription a ete refusee. Veuillez contacter le support.'
            );
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte n est pas encore actif. Verifiez votre email puis attendez la validation de l administrateur.'
            );
        }

        if ($user->getRoles() === []) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte n a pas encore de role attribue. Un administrateur doit valider votre inscription.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
