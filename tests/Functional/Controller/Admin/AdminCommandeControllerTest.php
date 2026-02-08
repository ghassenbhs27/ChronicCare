<?php

namespace App\Tests\Functional\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminCommandeControllerTest extends WebTestCase
{
    public function testListCommandesRequiresAdmin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/commandes');

        $this->assertResponseStatusCodeSame(302);
    }

    public function testPendingCommandesRequiresAdmin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/commandes/pending');

        $this->assertResponseStatusCodeSame(302);
    }

    public function testStatsRequiresAdmin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/commandes/stats');

        $this->assertResponseStatusCodeSame(302);
    }

    public function testShippedCommandesRequiresAdmin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/commandes/shipped');

        $this->assertResponseStatusCodeSame(302);
    }
}
