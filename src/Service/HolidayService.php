<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HolidayService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly string $countryCode,
        private readonly string $apiBase
    ) {
    }

    /**
     * @return string[] Dates au format Y-m-d
     */
    public function getHolidayDatesForYear(int $year): array
    {
        $cacheKey = sprintf('holidays_%s_%d', $this->countryCode, $year);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($year): array {
            $item->expiresAfter(86400);

            $url = rtrim($this->apiBase, '/') . sprintf('/PublicHolidays/%d/%s', $year, $this->countryCode);
            $response = $this->httpClient->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = $response->toArray(false);
            $dates = [];
            foreach ($data as $holiday) {
                if (isset($holiday['date'])) {
                    $dates[] = (string) $holiday['date'];
                }
            }

            return array_values(array_unique($dates));
        });
    }

    /**
     * @return string[] Dates au format Y-m-d
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
        foreach ($years as $year) {
            $all = array_merge($all, $this->getHolidayDatesForYear($year));
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
        $target = $date->format('Y-m-d');
        return in_array($target, $this->getHolidayDatesForYear($year), true);
    }
}
