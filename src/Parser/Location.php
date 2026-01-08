<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Parser;

/**
 * Represents a location in the JSON document
 */
class Location
{
    /**
     * @param array<string|int> $path The path to this location (property names and array indices)
     * @param Node|null $previousNode The previous property key or literal value
     * @param bool $isAtPropertyKey Whether the location is at a property key
     */
    public function __construct(
        public readonly array $path,
        public readonly ?Node $previousNode = null,
        public readonly bool $isAtPropertyKey = false,
    ) {
    }

    /**
     * Match the location's path against a pattern
     * Supports wildcards:
     * - '*' matches a single segment
     * - '**' matches any number of segments
     *
     * @param array<string|int> $pattern The pattern to match against
     * @return bool True if the path matches the pattern
     */
    public function matches(array $pattern): bool
    {
        $pathIndex = 0;
        $patternIndex = 0;
        $pathLength = count($this->path);
        $patternLength = count($pattern);

        while ($pathIndex < $pathLength && $patternIndex < $patternLength) {
            $patternSegment = $pattern[$patternIndex];

            if ($patternSegment === '**') {
                // '**' matches any number of segments
                // Try to match the rest of the pattern
                if ($patternIndex === $patternLength - 1) {
                    // '**' at the end matches everything remaining
                    return true;
                }

                // Try matching at each position
                for ($i = $pathIndex; $i < $pathLength; $i++) {
                    $subPattern = array_slice($pattern, $patternIndex + 1);
                    $subPath = array_slice($this->path, $i);
                    $tempLocation = new Location($subPath, $this->previousNode, $this->isAtPropertyKey);
                    if ($tempLocation->matches($subPattern)) {
                        return true;
                    }
                }
                return false;
            }

            if ($patternSegment === '*') {
                // '*' matches any single segment
                $pathIndex++;
                $patternIndex++;
                continue;
            }

            // Exact match required
            if ($this->path[$pathIndex] !== $patternSegment) {
                return false;
            }

            $pathIndex++;
            $patternIndex++;
        }

        // Both path and pattern must be fully consumed
        return $pathIndex === $pathLength && $patternIndex === $patternLength;
    }
}
