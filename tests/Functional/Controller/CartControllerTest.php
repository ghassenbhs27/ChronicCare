<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CartControllerTest extends WebTestCase
{
    public function testShowEmptyCart(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/cart');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('panier est vide', $crawler->getContent());
    }

    public function testAddToCartNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cart/add/99999?quantity=1');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testClearCart(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/cart/clear');

        $this->assertResponseRedirect();
    }
}
