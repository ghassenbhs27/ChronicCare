<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class GeoLocationService
{
    // En développement (localhost), utiliser l'API mock locale
    // En production, utiliser IP-API réel
    private const API_URL_PROD = 'https://ip-api.com/json/';
    private const API_URL_DEV = 'http://localhost:8000/api/mock/geolocation/';
    private const API_TIMEOUT = 5;

    private bool $isDevMode = false;
    private string $apiUrl;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        string $environment = 'dev'
    ) {
        $this->isDevMode = ($environment === 'dev');
        $this->apiUrl = $this->isDevMode ? self::API_URL_DEV : self::API_URL_PROD;

        if ($this->isDevMode) {
            $this->logger->notice('GeoLocationService: Mode DÉVELOPPEMENT - Utilisation de l\'API mock locale');
        }
    }

    /**
     * Récupère les informations de géolocalisation pour une adresse IP
     * 
     * @param string $ipAddress L'adresse IP à géolocaliser
     * @return array|null Les données de géolocalisation ou null en cas d'erreur
     */
    public function getLocationByIp(string $ipAddress): ?array
    {
        try {
            // En mode dev, retourner des données simulées pour localhost/IP privées
            if ($this->isDevMode && $this->isLocalOrPrivateIp($ipAddress)) {
                $this->logger->info('GeoLocationService: IP locale détectée, utilisation données simulées', ['ip' => $ipAddress]);
                return $this->getMockLocationData($ipAddress);
            }

            // Valider que c'est une adresse IP valide
            if (!$this->isValidIp($ipAddress)) {
                $this->logger->warning('GeoLocationService: IP invalide', ['ip' => $ipAddress]);
                return null;
            }

            $url = $this->apiUrl;
            if (!empty($ipAddress)) {
                $url .= $ipAddress;
            }

            $this->logger->info('GeoLocationService: Requête géolocalisation', ['url' => $url, 'isDev' => $this->isDevMode]);

            $response = $this->httpClient->request('GET', $url, [
                'timeout' => self::API_TIMEOUT,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 (ChronicCare/1.0; +http://localhost:8000)',
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $this->logger->info('GeoLocationService: Status code', ['code' => $statusCode]);

            if ($statusCode !== 200) {
                $this->logger->error('GeoLocationService: Erreur HTTP', ['code' => $statusCode]);
                return null;
            }

            $data = $response->toArray();

            // Vérifier si l'API a retourné un succès
            if ($data['status'] !== 'success') {
                $this->logger->warning('GeoLocationService: status non success', ['status' => $data['status'] ?? 'N/A']);
                return null;
            }

            $result = [
                'ip' => $data['query'] ?? $ipAddress,
                'country' => $data['country'] ?? null,
                'countryCode' => $data['countryCode'] ?? null,
                'city' => $data['city'] ?? null,
                'latitude' => $data['lat'] ?? null,
                'longitude' => $data['lon'] ?? null,
                'timezone' => $data['timezone'] ?? null,
                'isp' => $data['isp'] ?? null,
                'region' => $data['regionName'] ?? null,
            ];

            $this->logger->info('GeoLocationService: Succès', ['city' => $result['city'], 'country' => $result['country']]);
            return $result;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('GeoLocationService: Erreur transport', ['error' => $e->getMessage()]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('GeoLocationService: Erreur générale', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Récupère les informations de géolocalisation pour l'adresse IP cliente
     * Utilise l'IP du client actuel si aucune IP n'est fournie
     * 
     * @param string|null $ipAddress L'adresse IP à géolocaliser (optionnel)
     * @return array|null Les données de géolocalisation ou null en cas d'erreur
     */
    public function getClientLocation(?string $ipAddress = null): ?array
    {
        $this->logger->info('GeoLocationService: getClientLocation appelé', ['ip' => $ipAddress]);

        if (!$ipAddress) {
            // Sans IP fournie, ip-api retournera l'IP du client qui fait la requête
            return $this->getLocationByIp('');
        }

        return $this->getLocationByIp($ipAddress);
    }

    /**
     * Valide si une chaîne est une adresse IP valide (IPv4 ou IPv6)
     * 
     * @param string $ip L'adresse à valider
     * @return bool True si c'est une IP valide
     */
    private function isValidIp(string $ip): bool
    {
        // Permettre une chaîne vide pour utiliser l'IP du client
        if (empty($ip)) {
            return true;
        }

        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Calcule la distance entre deux points GPS en kilomètres
     * Utilise la formule de Haversine
     * 
     * @param float $lat1 Latitude du point 1
     * @param float $lon1 Longitude du point 1
     * @param float $lat2 Latitude du point 2
     * @param float $lon2 Longitude du point 2
     * @return float Distance en kilomètres
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Rayon de la Terre en km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return round($distance, 2);
    }

    /**
     * Vérifie si l'IP est une IP locale ou privée (non routable sur Internet)
     * 
     * @param string $ip L'adresse IP à vérifier
     * @return bool True si c'est une IP locale/privée
     */
    private function isLocalOrPrivateIp(string $ip): bool
    {
        // IPs locales et réservées
        $localPatterns = [
            '127.',      // Localhost IPv4
            '::1',       // Localhost IPv6
            '10.',       // Classe A privée
            '172.16.',   // Classe B privée (172.16.x.x - 172.31.x.x)
            '172.17.',
            '172.18.',
            '172.19.',
            '172.20.',
            '172.21.',
            '172.22.',
            '172.23.',
            '172.24.',
            '172.25.',
            '172.26.',
            '172.27.',
            '172.28.',
            '172.29.',
            '172.30.',
            '172.31.',
            '192.168.',  // Classe C privée
            'fe80:',     // Link-local IPv6
            'fc00:',     // Unique local address IPv6
            'fd00:',     // Unique local address IPv6
        ];

        foreach ($localPatterns as $pattern) {
            if (str_starts_with($ip, $pattern)) {
                return true;
            }
        }

        // Vérification spéciale pour localhost
        if ($ip === 'localhost' || empty($ip)) {
            return true;
        }

        return false;
    }

    /**
     * Retourne des données de géolocalisation simulées pour le développement local
     * Simule une localisation à Tunis, Tunisie
     * 
     * @param string $ip L'adresse IP (pour logging)
     * @return array Données de géolocalisation simulées
     */
    private function getMockLocationData(string $ip): array
    {
        return [
            'ip' => $ip ?: '127.0.0.1',
            'country' => 'Tunisia',
            'countryCode' => 'TN',
            'city' => 'Tunis',
            'region' => 'Tunis',
            'regionName' => 'Tunis Governorate',
            'latitude' => 36.8065,
            'longitude' => 10.1815,
            'timezone' => 'Africa/Tunis',
            'isp' => 'Localhost (Dev Mode)',
            'org' => 'Development Environment',
            'as' => 'AS0 Mock Provider',
            'zip' => '1000',
            'status' => 'success',
            'message' => 'Mock data for local development'
        ];
    }
}
