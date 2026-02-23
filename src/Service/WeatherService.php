<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        #[Autowire('%weather_default_country%')]
        private string $defaultCountry,
        #[Autowire('%weather_geocoding_api_base%')]
        private string $geocodingApiBase,
        #[Autowire('%weather_forecast_api_base%')]
        private string $forecastApiBase,
        #[Autowire('%weather_archive_api_base%')]
        private string $archiveApiBase,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @return array{
     *     location: string,
     *     date: string,
     *     source: string,
     *     weather_code: int,
     *     weather_label: string,
     *     temperature_min: float|null,
     *     temperature_max: float|null,
     *     precipitation_mm: float|null
     * }|null
     */
    public function getEventWeather(\DateTimeInterface $eventDate, string $location): ?array
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }

        $coordinates = $this->resolveCoordinates($location);
        if ($coordinates === null) {
            return null;
        }

        $date = $eventDate->format('Y-m-d');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $isPast = $date < $today;
        $weather = $this->fetchDailyWeather(
            $coordinates['latitude'],
            $coordinates['longitude'],
            $date,
            $isPast,
            $coordinates['timezone'] ?? 'auto'
        );

        if ($weather === null) {
            return null;
        }

        return [
            'location' => $coordinates['display_name'],
            'date' => $date,
            'source' => $isPast ? 'archive' : 'forecast',
            'weather_code' => $weather['weather_code'],
            'weather_label' => $this->mapWeatherCode($weather['weather_code']),
            'temperature_min' => $weather['temperature_min'],
            'temperature_max' => $weather['temperature_max'],
            'precipitation_mm' => $weather['precipitation_mm'],
        ];
    }

    /**
     * @return array{latitude: float, longitude: float, timezone: string, display_name: string}|null
     */
    private function resolveCoordinates(string $location): ?array
    {
        $cacheKey = 'weather_geo_' . md5(strtolower($location));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($location) {
            $item->expiresAfter(7 * 24 * 3600);

            $queries = $this->buildSearchQueries($location);

            foreach ($queries as $query) {
                $result = $this->queryGeocoding($query, true);
                if ($result !== null) {
                    return $result;
                }
            }

            foreach ($queries as $query) {
                $result = $this->queryGeocoding($query, false);
                if ($result !== null) {
                    return $result;
                }
            }

            $item->expiresAfter(3600);
            return null;
        });
    }

    /**
     * @return array{latitude: float, longitude: float, timezone: string, display_name: string}|null
     */
    private function queryGeocoding(string $query, bool $restrictCountry): ?array
    {
        try {
            $params = [
                'name' => $query,
                'count' => 5,
                'language' => 'fr',
                'format' => 'json',
            ];
            if ($restrictCountry) {
                $params['countryCode'] = strtoupper($this->defaultCountry);
            }

            $response = $this->httpClient->request('GET', $this->geocodingApiBase . '/search', [
                'query' => $params,
                'timeout' => 8,
                'max_duration' => 12,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray(false);
            if (!is_array($data) || !isset($data['results']) || !is_array($data['results'])) {
                return null;
            }

            $first = null;
            foreach ($data['results'] as $candidate) {
                if (!is_array($candidate)) {
                    continue;
                }

                if ($restrictCountry && (($candidate['country_code'] ?? '') !== strtoupper($this->defaultCountry))) {
                    continue;
                }

                if (!$restrictCountry && isset($candidate['country_code']) && $candidate['country_code'] === strtoupper($this->defaultCountry)) {
                    $first = $candidate;
                    break;
                }

                if ($first === null) {
                    $first = $candidate;
                }
            }

            if ($first === null || !isset($first['latitude'], $first['longitude'])) {
                return null;
            }

            $name = isset($first['name']) ? (string) $first['name'] : $query;
            $country = isset($first['country']) ? (string) $first['country'] : '';
            $displayName = $country !== '' ? $name . ', ' . $country : $name;

            return [
                'latitude' => (float) $first['latitude'],
                'longitude' => (float) $first['longitude'],
                'timezone' => isset($first['timezone']) ? (string) $first['timezone'] : 'auto',
                'display_name' => $displayName,
            ];
        } catch (\Throwable $exception) {
            if ($this->logger) {
                $this->logger->warning('Weather geocoding failed', [
                    'query' => $query,
                    'error' => $exception->getMessage(),
                ]);
            }

            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function buildSearchQueries(string $location): array
    {
        $queries = [$location];
        if (str_contains($location, ',')) {
            $queries[] = trim((string) explode(',', $location)[0]);
        }

        if (strtoupper($this->defaultCountry) === 'TN') {
            // Handles frequent Tunisian place names such as "Bardo" -> "Le Bardo".
            $queries[] = 'Le ' . $location;
            $queries[] = 'El ' . $location;
        }

        $deduplicated = [];
        foreach ($queries as $query) {
            $query = trim($query);
            if ($query === '') {
                continue;
            }

            $key = strtolower($query);
            if (isset($deduplicated[$key])) {
                continue;
            }

            $deduplicated[$key] = $query;
        }

        return array_values($deduplicated);
    }

    /**
     * @return array{weather_code: int, temperature_min: float|null, temperature_max: float|null, precipitation_mm: float|null}|null
     */
    private function fetchDailyWeather(
        float $latitude,
        float $longitude,
        string $date,
        bool $isPast,
        string $timezone
    ): ?array {
        $endpoint = $isPast ? $this->archiveApiBase : $this->forecastApiBase;
        $cacheKey = sprintf(
            'weather_day_%s_%s_%s_%s',
            $isPast ? 'a' : 'f',
            str_replace('.', '_', (string) $latitude),
            str_replace('.', '_', (string) $longitude),
            $date
        );

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($endpoint, $latitude, $longitude, $date, $timezone) {
            $item->expiresAfter(3 * 3600);

            try {
                $response = $this->httpClient->request('GET', $endpoint, [
                    'query' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'timezone' => $timezone,
                        'start_date' => $date,
                        'end_date' => $date,
                        'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum',
                    ],
                    'timeout' => 8,
                    'max_duration' => 12,
                ]);

                if ($response->getStatusCode() !== 200) {
                    $item->expiresAfter(900);
                    return null;
                }

                $data = $response->toArray(false);
                if (!is_array($data) || !isset($data['daily']) || !is_array($data['daily'])) {
                    $item->expiresAfter(900);
                    return null;
                }

                $daily = $data['daily'];
                $weatherCode = $daily['weather_code'][0] ?? null;
                if ($weatherCode === null) {
                    $item->expiresAfter(900);
                    return null;
                }

                return [
                    'weather_code' => (int) $weatherCode,
                    'temperature_min' => isset($daily['temperature_2m_min'][0]) ? (float) $daily['temperature_2m_min'][0] : null,
                    'temperature_max' => isset($daily['temperature_2m_max'][0]) ? (float) $daily['temperature_2m_max'][0] : null,
                    'precipitation_mm' => isset($daily['precipitation_sum'][0]) ? (float) $daily['precipitation_sum'][0] : null,
                ];
            } catch (\Throwable $exception) {
                $item->expiresAfter(900);
                if ($this->logger) {
                    $this->logger->warning('Weather API request failed', [
                        'date' => $date,
                        'error' => $exception->getMessage(),
                    ]);
                }

                return null;
            }
        });
    }

    private function mapWeatherCode(int $code): string
    {
        return match ($code) {
            0 => 'Ciel degage',
            1, 2 => 'Peu nuageux',
            3 => 'Couvert',
            45, 48 => 'Brouillard',
            51, 53, 55, 56, 57 => 'Bruine',
            61, 63, 65, 66, 67 => 'Pluie',
            71, 73, 75, 77 => 'Neige',
            80, 81, 82 => 'Averses',
            85, 86 => 'Averses de neige',
            95, 96, 99 => 'Orage',
            default => 'Meteo variable',
        };
    }
}
