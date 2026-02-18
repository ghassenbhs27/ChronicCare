<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidAddress extends Constraint
{
    public string $message = "L'adresse fournie n'est pas valide. Veuillez utiliser le format: numéro rue, code postal, ville.";
    public string $messageTooShort = "L'adresse doit contenir au moins 10 caractères.";
    public string $messageInvalidCharacters = "L'adresse contient des caractères non autorisés.";
}
