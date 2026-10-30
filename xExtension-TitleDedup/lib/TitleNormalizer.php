<?php
declare(strict_types=1);

final class TitleNormalizer
{
    /**
     * @param array{normalize_whitespace?:bool,normalize_case?:bool,strip_punctuation?:bool} $options
     */
    public static function normalize(string $title, array $options): string
    {
        // FreshRSS stores titles HTML-escaped; compare the visible text.
        $value = trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (!empty($options['strip_punctuation'])) {
            $value = preg_replace('/[\p{P}\p{S}]+/u', '', $value) ?? $value;
            // Removing a dash between two words leaves a double space behind.
            $value = preg_replace('/ {2,}/u', ' ', $value) ?? $value;
        }

        if (!empty($options['normalize_whitespace'])) {
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        }

        if (!empty($options['normalize_case'])) {
            $value = mb_strtolower($value, 'UTF-8');
        }

        return trim($value);
    }
}
