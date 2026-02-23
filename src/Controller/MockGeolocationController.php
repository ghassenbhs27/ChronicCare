<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API mock pour développement/test local
 * Simule les réponses IP-API sans dépendre d'un service externe
 */
#[Route('/api/mock', name: 'api_mock_')]
class MockGeolocationController extends AbstractController
{
    /**
     * GET /api/mock/geolocation/{ip}
     * Simule une réponse IP-API
     */
    #[Route('/geolocation/{ip}', name: 'geolocation', methods: ['GET'])]
    public function mockGeolocation(string $ip = null): JsonResponse
    {
        // Données de test par IP
        $mockData = [
            '127.0.0.1' => [
                'query' => '127.0.0.1',
                'status' => 'success',
                'country' => 'France',
                'countryCode' => 'FR',
                'region' => 'Île-de-France',
                'regionName' => 'Île-de-France',
                'city' => 'Paris',
                'zip' => '75000',
                'lat' => 48.8566,
                'lon' => 2.3522,
                'timezone' => 'Europe/Paris',
                'isp' => 'Local Development',
                'org' => 'Chronic Care',
                'as' => 'DEV-LOCAL',
            ],
            // Par défaut, retourner des données pour la France
            'default' => [
                'query' => $ip ?? '127.0.0.1',
                'status' => 'success',
                'country' => 'France',
                'countryCode' => 'FR',
                'region' => 'Provence-Alpes-Côte d\'Azur',
                'regionName' => 'Provence-Alpes-Côte d\'Azur',
                'city' => 'Marseille',
                'zip' => '13000',
                'lat' => 43.2965,
                'lon' => 5.3698,
                'timezone' => 'Europe/Paris',
                'isp' => 'Mock Geolocation Service',
                'org' => 'Chronic Care Mock',
                'as' => 'DEV-MOCK',
            ]
        ];

        $data = $mockData[$ip] ?? $mockData['default'];
        
        return $this->json($data);
    }
}
