<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MapGeocodingService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        #[Autowire('%map_geocode_api_base%')]
        private string $geocodeApiBase,
        #[Autowire('%weather_default_country%')]
        private string $defaultCountry,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @return array{
     *     latitude: float,
     *     longitude: float,
     *     display_name: string,
     *     map_url: string
     * }|null
     */
    public function resolveLocation(string $location): ?array
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }

        $cacheKey = 'map_geo_' . md5(strtolower($location));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($location) {
            $item->expiresAfter(7 * 24 * 3600);

            $result = $this->queryNominatim($location, true);
            if ($result === null) {
                $result = $this->queryNominatim($location, false);
            }

            if ($result === null) {
                $item->expiresAfter(3600);
            }

            return $result;
        });
    }

    /**
     * @return array{
     *     latitude: float,
     *     longitude: float,
     *     display_name: string,
     *     map_url: string
     * }|null
     */
    private function queryNominatim(string $location, bool $restrictCountry): ?array
    {
        try {
            $query = [
                'q' => $location,
                'format' => 'jsonv2',
                'limit' => 1,
                'accept-language' => 'fr',
            ];

            if ($restrictCountry) {
                $query['countrycodes'] = strtolower($this->defaultCountry);
            }

            $response = $this->httpClient->request('GET', rtrim($this->geocodeApiBase, '/') . '/search', [
                'query' => $query,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'ChronicCare/1.0 (Symfony app)',
                ],
                'timeout' => 8,
                'max_duration' => 12,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray(false);
            if (!isset($data[0]) || !is_array($data[0])) {
                return null;
            }

            $first = $data[0];
            if (!isset($first['lat'], $first['lon'])) {
                return null;
            }

            $latitude = (float) $first['lat'];
            $longitude = (float) $first['lon'];
            $displayName = isset($first['display_name']) ? (string) $first['display_name'] : $location;

            return [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'display_name' => $displayName,
                'map_url' => sprintf('https://www.openstreetmap.org/?mlat=%F&mlon=%F#map=15/%F/%F', $latitude, $longitude, $latitude, $longitude),
            ];
        } catch (\Throwable $exception) {
            if ($this->logger) {
                $this->logger->warning('Map geocoding failed', [
                    'location' => $location,
                    'error' => $exception->getMessage(),
                ]);
            }

            return null;
        }
    }
}
