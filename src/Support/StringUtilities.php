<?php

namespace Stillat\DocumentationSearch\Support;

use Illuminate\Support\Str;

class StringUtilities
{
    public static function cleanStringHash(string $text): string
    {
        $text = trim($text);

        if (Str::startsWith($text, '#')) {
            $text = trim(mb_substr($text, 1));
        }

        return $text;
    }

    public static function extractWords(string $string): array
    {
        preg_match_all('/\b\w+\b/', $string, $matches);

        return $matches[0];
    }

    public static function soundexWords(string $string): string
    {
        $words = array_filter(self::extractWords($string));

        return implode(' ', array_map('soundex', $words));
    }
}
