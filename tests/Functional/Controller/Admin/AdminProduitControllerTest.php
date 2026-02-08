<?php

namespace App\Tests\Functional\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminProduitControllerTest extends WebTestCase
{
    public function testListProduitsRequiresAdmin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/products');

        $this->assertResponseStatusCodeSame(302);
    }

    public function testCreateProduitRequiresAdmin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/products/create');

        $this->assertResponseStatusCodeSame(302);
    }

    public function testLowStockRequiresAdmin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/products/low-stock');

        $this->assertResponseStatusCodeSame(302);
    }
}
