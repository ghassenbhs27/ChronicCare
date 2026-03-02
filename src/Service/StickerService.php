<?php

namespace App\Service;

class StickerService
{
    /**
     * Tableau associatif : nom du pack => liste de stickers (emojis)
     *
     * @var array<string, list<string>>
     */
    private array $stickerPacks = [
        'emoji' => ['😀', '😊', '😍', '🤣', '😂', '😘', '😌', '😎', '😢', '😭', '😴', '🤔'],
        'nature' => ['🌸', '🌺', '🌻', '🌷', '🌹', '🍀', '🌼', '🌿', '☀️', '🌤️', '⛅', '🌈'],
        'animals' => ['🐶', '🐱', '🐭', '🐹', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷'],
        'food' => ['🍕', '🍔', '🍟', '🌭', '🍿', '🍩', '🍪', '🍰', '🎂', '🍎', '🍊', '🍋'],
    ];

    /**
     * Get all sticker packs
     *
     * @return array<string, list<string>>
     */
    public function getAllStickerPacks(): array
    {
        return $this->stickerPacks;
    }

    /**
     * Get stickers by pack name
     *
     * @return list<string>
     */
    public function getStickersByPack(string $pack): array
    {
        return $this->stickerPacks[$pack] ?? [];
    }

    /**
     * Validate sticker URL
     */
    public function isValidStickerUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            || preg_match('/^data:image\//', $url) === 1;
    }

    /**
     * Check if sticker exists in packs
     */
    public function isStickerValid(string $sticker): bool
    {
        foreach ($this->stickerPacks as $pack) {
            if (in_array($sticker, $pack, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get sticker picker HTML
     */
    public function generateStickerPickerHtml(): string
    {
        $html = '<div class="sticker-picker-modal">';

        foreach ($this->stickerPacks as $packName => $stickers) {
            $html .= '<div class="sticker-pack" data-pack="' . htmlspecialchars($packName) . '">';
            $html .= '<h6 class="pack-name">' . ucfirst($packName) . '</h6>';
            $html .= '<div class="sticker-grid">';

            foreach ($stickers as $sticker) {
                $html .= '<button type="button" class="sticker-item" data-sticker="' .
                    htmlspecialchars($sticker) . '">' . $sticker . '</button>';
            }

            $html .= '</div></div>';
        }

        $html .= '</div>';

        return $html;
    }
}