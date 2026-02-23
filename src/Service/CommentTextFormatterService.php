<?php

namespace App\Service;

class CommentTextFormatterService
{
    /**
     * Format text to HTML with markdown-like syntax support
     */
    public function formatTextToHtml(string $text): string
    {
        // Escape HTML first
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        // Bold: **text**
        $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);
        
        // Italic: *text*
        $text = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $text);
        
        // Underline: __text__
        $text = preg_replace('/__(.+?)__/s', '<u>$1</u>', $text);
        
        // Strikethrough: ~~text~~
        $text = preg_replace('/~~(.+?)~~/s', '<s>$1</s>', $text);
        
        // Links: [text](url)
        $text = preg_replace('/\[(.*?)\]\((.*?)\)/s', '<a href="$2" target="_blank">$1</a>', $text);
        
        // Line breaks
        $text = str_replace("\n", '<br>', $text);
        
        return $text;
    }

    /**
     * Generate CSS style from font properties
     */
    public function getCommentStyle(?string $fontStyle, ?string $fontSize, ?string $textColor, ?string $backgroundColor): string
    {
        $styles = [];
        
        // Font style
        if ($fontStyle) {
            switch ($fontStyle) {
                case 'bold':
                    $styles[] = 'font-weight: bold';
                    break;
                case 'italic':
                    $styles[] = 'font-style: italic';
                    break;
                case 'underline':
                    $styles[] = 'text-decoration: underline';
                    break;
                case 'strikethrough':
                    $styles[] = 'text-decoration: line-through';
                    break;
            }
        }
        
        // Font size
        if ($fontSize) {
            switch ($fontSize) {
                case 'small':
                    $styles[] = 'font-size: 0.85rem';
                    break;
                case 'medium':
                    $styles[] = 'font-size: 0.95rem';
                    break;
                case 'large':
                    $styles[] = 'font-size: 1.15rem';
                    break;
            }
        }
        
        // Text color
        if ($textColor && $this->isValidHexColor($textColor)) {
            $styles[] = 'color: ' . $textColor;
        }
        
        // Background color
        if ($backgroundColor && $this->isValidHexColor($backgroundColor)) {
            $styles[] = 'background-color: ' . $backgroundColor;
            $styles[] = 'padding: 0.25rem 0.5rem';
            $styles[] = 'border-radius: 4px';
        }
        
        return implode('; ', $styles);
    }

    /**
     * Validate hex color format
     */
    public function isValidHexColor(?string $color): bool
    {
        if (!$color) {
            return false;
        }
        return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color) === 1;
    }

    /**
     * Parse emojis from JSON string
     */
    public function parseEmojis(?string $emojis): array
    {
        if (!$emojis) {
            return [];
        }
        
        $parsed = json_decode($emojis, true);
        return is_array($parsed) ? $parsed : [];
    }

    /**
     * Get plain text from content (strip HTML)
     */
    public function getPlainText(string $text): string
    {
        return strip_tags($text);
    }
}
