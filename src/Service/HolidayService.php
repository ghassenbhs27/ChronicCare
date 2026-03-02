<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HolidayService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        #[Autowire('%holiday_country%')]
        private string $holidayCountry,
        #[Autowire('%holiday_api_base%')]
        private string $holidayApiBase,
        private ?LoggerInterface $logger = null
    ) {
    }

    public function getHolidayName(\DateTimeInterface $date): ?string
    {
        $year = (int) $date->format('Y');
        $country = strtoupper($this->holidayCountry);
        $holidays = $this->getHolidays($year, $country);
        $key = $date->format('Y-m-d');

        if (!isset($holidays[$key])) {
            return null;
        }

        $holiday = $holidays[$key];
        if (isset($holiday['localName']) && $holiday['localName'] !== '') {
            return (string) $holiday['localName'];
        }

        return isset($holiday['name']) ? (string) $holiday['name'] : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getHolidays(int $year, string $country): array
    {
        $cacheKey = sprintf('holidays_%s_%d', strtolower($country), $year);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($year, $country) {
            $url = sprintf('%s/PublicHolidays/%d/%s', $this->holidayApiBase, $year, $country);

            try {
                $response = null;
                $lastException = null;
                for ($attempt = 1; $attempt <= 2; ++$attempt) {
                    try {
                        $response = $this->httpClient->request('GET', $url, [
                            'timeout' => 8,
                            'max_duration' => 15,
                        ]);
                        break;
                    } catch (\Throwable $exception) {
                        $lastException = $exception;
                        if ($attempt === 2) {
                            throw $exception;
                        }
                        usleep(200000);
                    }
                }

                if ($response === null && $lastException !== null) {
                    throw $lastException;
                }

                if ($response->getStatusCode() !== 200) {
                    $item->expiresAfter(300);
                    return [];
                }

                $data = $response->toArray(false);
            } catch (\Throwable $exception) {
                $item->expiresAfter(300);
                if ($this->logger) {
                    $this->logger->warning('Holiday API request failed', [
                        'error' => $exception->getMessage(),
                        'country' => $country,
                        'year' => $year,
                    ]);
                }

                return [];
            }

            $map = [];
            if (is_array($data)) {
                foreach ($data as $holiday) {
                    if (is_array($holiday) && isset($holiday['date'])) {
                        $map[(string) $holiday['date']] = $holiday;
                    }
                }
            }

            $item->expiresAfter(86400);
            return $map;
        });
    }

    /**
     * @return string[] Dates formatted as Y-m-d
     */
    public function getHolidayDatesInRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $years = [];
        $startYear = (int) $start->format('Y');
        $endYear = (int) $end->format('Y');
        for ($y = $startYear; $y <= $endYear; $y++) {
            $years[] = $y;
        }

        $all = [];
        $country = strtoupper($this->holidayCountry);
        foreach ($years as $year) {
            $holidays = $this->getHolidays($year, $country);
            $all = array_merge($all, array_keys($holidays));
        }

        $startDate = $start->format('Y-m-d');
        $endDate = $end->format('Y-m-d');

        return array_values(array_filter($all, static function (string $date) use ($startDate, $endDate): bool {
            return $date >= $startDate && $date <= $endDate;
        }));
    }

    public function isHoliday(\DateTimeInterface $date): bool
    {
        $year = (int) $date->format('Y');
        $country = strtoupper($this->holidayCountry);
        $holidays = $this->getHolidays($year, $country);
        $target = $date->format('Y-m-d');
        return isset($holidays[$target]);
    }
}
