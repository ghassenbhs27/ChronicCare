<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidAddressValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof ValidAddress) {
            throw new UnexpectedTypeException($constraint, ValidAddress::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        // Vérifier la longueur minimale
        if (strlen($value) < 10) {
            $this->context->buildViolation($constraint->messageTooShort)
                ->addViolation();
            return;
        }

        // Vérifier que l'adresse contient au moins un mot (lettres) et est cohérente
        if (!preg_match('/[a-zA-ZÀ-ÿ]{2,}/', $value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Interdire les caractères spéciaux dangereux
        if (preg_match('/[<>"\'{};]/', $value)) {
            $this->context->buildViolation($constraint->messageInvalidCharacters)
                ->addViolation();
        }
    }
}
