<?php

namespace App\Controller;

use App\Service\GeoLocationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/geolocation', name: 'api_geolocation_')]
class GeolocationApiController extends AbstractController
{
    public function __construct(
        private readonly GeoLocationService $geoLocationService
    ) {
    }

    /**
     * Récupère les informations de géolocalisation pour une adresse IP
     * GET /api/geolocation/ip/{ipAddress}
     */
    #[Route('/ip/{ipAddress}', name: 'get_by_ip', methods: ['GET'])]
    public function getByIp(string $ipAddress): JsonResponse
    {
        $location = $this->geoLocationService->getLocationByIp($ipAddress);
        
        if (!$location) {
            return $this->json([
                'status' => 'error',
                'message' => 'Impossible de localiser cette adresse IP'
            ], 404);
        }

        return $this->json([
            'status' => 'success',
            'data' => $location
        ]);
    }

    /**
     * Récupère les informations de géolocalisation du client actuel
     * GET /api/geolocation/client
     */
    #[Route('/client', name: 'get_client', methods: ['GET'])]
    public function getClientLocation(Request $request): JsonResponse
    {
        try {
            // Récupérer l'IP du client
            $clientIp = $request->getClientIp();
            
            $location = $this->geoLocationService->getClientLocation($clientIp);
            
            // Si on n'a pas de données de IP-API, retourner quand même une réponse partielle
            // avec l'IP et un message d'avertissement
            if (!$location) {
                return $this->json([
                    'status' => 'partial',
                    'message' => 'Géolocalisation indisponible, la commande sera traitée sans données de localisation',
                    'ip' => $clientIp,
                    'data' => null
                ], 200);
            }

            return $this->json([
                'status' => 'success',
                'ip' => $clientIp,
                'data' => $location
            ]);
        } catch (\Exception $e) {
            // En cas d'erreur, retourner une réponse au lieu de 500
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur serveur: ' . $e->getMessage(),
                'ip' => $request->getClientIp() ?? null,
                'data' => null
            ], 200); // 200 pour que le frontend sache que la requête a fonctionné
        }
    }

    /**
     * Calcule la distance entre deux points GPS
     * POST /api/geolocation/distance
     * JSON: {"lat1": 48.8566, "lon1": 2.3522, "lat2": 43.2965, "lon2": 5.3698}
     */
    #[Route('/distance', name: 'calculate_distance', methods: ['POST'])]
    public function calculateDistance(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Valider les paramètres
        if (!isset($data['lat1'], $data['lon1'], $data['lat2'], $data['lon2'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'Paramètres manquants: lat1, lon1, lat2, lon2'
            ], 400);
        }

        try {
            $distance = $this->geoLocationService->calculateDistance(
                (float) $data['lat1'],
                (float) $data['lon1'],
                (float) $data['lat2'],
                (float) $data['lon2']
            );

            return $this->json([
                'status' => 'success',
                'distance_km' => $distance,
                'distance_miles' => round($distance * 0.621371, 2)
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors du calcul: ' . $e->getMessage()
            ], 400);
        }
    }
}
