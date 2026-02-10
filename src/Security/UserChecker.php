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

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte n\'est pas encore actif. Veuillez vérifier votre email et attendre la validation par l\'administrateur.'
            );
        }

        if ($user->getRoles() === []) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte n\'a pas encore de rôle attribué. Un administrateur doit valider votre inscription.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
