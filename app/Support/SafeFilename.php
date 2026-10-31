<?php

declare(strict_types=1);

namespace App\Support;

final class SafeFilename
{
    public static function make(string $filename, string $fallback = 'document'): string
    {
        $filename = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '-', $filename));
        $filename = str_replace(['/', '\\'], '-', $filename);
        $filename = trim((string) preg_replace('/\s+/u', ' ', $filename), " .\t\n\r\0\x0B");

        return $filename !== '' ? $filename : $fallback;
    }

    private function __construct() {}
}
