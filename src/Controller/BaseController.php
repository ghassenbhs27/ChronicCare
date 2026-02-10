<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BaseController extends AbstractController
{
    protected function isUserAuthenticated(): bool
    {
        return $this->getUser() !== null;
    }
}