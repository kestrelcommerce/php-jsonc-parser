<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Util;

/**
 * UTF-8 string utility functions
 * Provides JavaScript-like string operations with proper multi-byte character support
 */
final class StringHelper
{
    /**
     * Get the character code at a specific index (JavaScript charCodeAt equivalent)
     *
     * @param string $str The string to read from
     * @param int $index The character index (0-based)
     * @return int The Unicode code point, or 0 if index is out of bounds
     */
    public static function charCodeAt(string $str, int $index): int
    {
        $char = mb_substr($str, $index, 1, 'UTF-8');

        if ($char === '') {
            return 0;
        }

        $code = mb_ord($char, 'UTF-8');

        return is_int($code) !== false ? $code : 0;
    }

    /**
     * Get the character at a specific index (JavaScript charAt equivalent)
     *
     * @param string $str The string to read from
     * @param int $index The character index (0-based)
     * @return string The character, or empty string if index is out of bounds
     */
    public static function charAt(string $str, int $index): string
    {
        return mb_substr($str, $index, 1, 'UTF-8');
    }

    /**
     * Get the length of a string in characters (not bytes)
     *
     * @param string $str The string to measure
     * @return int The number of characters
     */
    public static function length(string $str): int
    {
        return mb_strlen($str, 'UTF-8');
    }

    /**
     * Get a substring (JavaScript substring equivalent)
     *
     * @param string $str The source string
     * @param int $start The start index
     * @param int|null $end The end index (exclusive), or null for end of string
     * @return string The substring
     */
    public static function substring(string $str, int $start, ?int $end = null): string
    {
        if ($end === null) {
            return mb_substr($str, $start, null, 'UTF-8');
        }

        $length = $end - $start;
        if ($length < 0) {
            return '';
        }

        return mb_substr($str, $start, $length, 'UTF-8');
    }

    /**
     * Create a string from a character code (JavaScript String.fromCharCode equivalent)
     *
     * @param int $code The Unicode code point
     * @return string The character
     */
    public static function fromCharCode(int $code): string
    {
        return mb_chr($code, 'UTF-8') ?: '';
    }

    private function __construct()
    {
        // Prevent instantiation
    }
}
