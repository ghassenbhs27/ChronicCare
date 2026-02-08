<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProduitControllerTest extends WebTestCase
{
    public function testListProduits(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/products');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Catalogue Produits', $crawler->getContent());
    }

    public function testShowProduitNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/products/99999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testSearchProduits(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/products/search?keyword=test');

        $this->assertResponseIsSuccessful();
    }
}
