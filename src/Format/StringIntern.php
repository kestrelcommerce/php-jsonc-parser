<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Format;

/**
 * String interning for performance optimization
 * Caches commonly used strings like spaces and line breaks with indentation
 */
final class StringIntern
{
    /** @var array<int, string> Cached space strings */
    private static array $cachedSpaces = [];

    /** @var array<string, array<string, array<int, string>>> Cached line breaks with spaces/tabs */
    private static array $cachedBreakLinesWithSpaces = [];

    private const MAX_CACHED_VALUES = 200;
    private const SUPPORTED_EOLS = ["\n", "\r", "\r\n"];

    /**
     * Get a string of spaces
     */
    public static function getSpaces(int $count): string
    {
        if ($count < 0) {
            return '';
        }

        if (!isset(self::$cachedSpaces[$count])) {
            if ($count < 20) {
                // Pre-populate cache for small values
                self::initializeSpacesCache();
            } else {
                self::$cachedSpaces[$count] = str_repeat(' ', $count);
            }
        }

        return self::$cachedSpaces[$count];
    }

    /**
     * Get a line break with indentation
     */
    public static function getEol(string $eol, string $indentChar, int $indentCount): string
    {
        if (!in_array($eol, self::SUPPORTED_EOLS, true)) {
            $eol = "\n";
        }

        if ($indentChar !== ' ' && $indentChar !== "\t") {
            $indentChar = ' ';
        }

        if ($indentCount < 0) {
            $indentCount = 0;
        }

        if (!isset(self::$cachedBreakLinesWithSpaces[$indentChar][$eol][$indentCount])) {
            if ($indentCount < self::MAX_CACHED_VALUES) {
                // Pre-populate cache
                self::initializeEolCache($indentChar, $eol);
            } else {
                self::$cachedBreakLinesWithSpaces[$indentChar][$eol][$indentCount] =
                    $eol . str_repeat($indentChar, $indentCount);
            }
        }

        return self::$cachedBreakLinesWithSpaces[$indentChar][$eol][$indentCount];
    }

    /**
     * Initialize the spaces cache
     */
    private static function initializeSpacesCache(): void
    {
        if (empty(self::$cachedSpaces)) {
            for ($i = 0; $i < 20; $i++) {
                self::$cachedSpaces[$i] = str_repeat(' ', $i);
            }
        }
    }

    /**
     * Initialize the EOL cache for a specific indent character and EOL
     */
    private static function initializeEolCache(string $indentChar, string $eol): void
    {
        if (!isset(self::$cachedBreakLinesWithSpaces[$indentChar][$eol])) {
            self::$cachedBreakLinesWithSpaces[$indentChar][$eol] = [];
            for ($i = 0; $i < self::MAX_CACHED_VALUES; $i++) {
                self::$cachedBreakLinesWithSpaces[$indentChar][$eol][$i] =
                    $eol . str_repeat($indentChar, $i);
            }
        }
    }

    /**
     * Clear all caches (useful for testing)
     */
    public static function clearCache(): void
    {
        self::$cachedSpaces = [];
        self::$cachedBreakLinesWithSpaces = [];
    }
}
