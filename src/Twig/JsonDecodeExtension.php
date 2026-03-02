<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class JsonDecodeExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('json_decode', [$this, 'jsonDecode']),
        ];
    }

    public function jsonDecode(?string $json, bool $assoc = true)
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, $assoc);
        
        return is_array($decoded) ? $decoded : [];
    }
}
