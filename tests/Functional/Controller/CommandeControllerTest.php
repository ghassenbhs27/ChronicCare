<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CommandeControllerTest extends WebTestCase
{
    public function testHistoryRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/commandes');

        $this->assertResponseRedirect();
    }

    public function testTrackCommande(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/commandes/track/CMD-1234567890123-1234');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateCommandeRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/commandes/create');

        $this->assertResponseRedirect();
    }
}
