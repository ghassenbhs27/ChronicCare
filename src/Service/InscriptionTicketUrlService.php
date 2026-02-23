<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\InscriptionEvenement;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class InscriptionTicketUrlService
{
    private UriSigner $uriSigner;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%kernel.secret%')]
        string $secret
    ) {
        $this->uriSigner = new UriSigner($secret);
    }

    public function generateSignedTicketUrl(InscriptionEvenement $inscription): string
    {
        $id = $inscription->getId();
        if ($id === null) {
            throw new \LogicException('Cannot generate a ticket URL before the registration is persisted.');
        }

        $url = $this->urlGenerator->generate('front_inscription_ticket', [
            'id' => $id,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->uriSigner->sign($url);
    }

    public function isRequestSignatureValid(Request $request): bool
    {
        return $this->uriSigner->check($request->getUri());
    }
}

