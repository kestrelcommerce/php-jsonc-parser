<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Parser;

/**
 * Represents a parse error with location information
 */
readonly class ParseError
{
    public function __construct(
        public ParseErrorCode $error,
        public int $offset,
        public int $length,
        public int $startLine,
        public int $startCharacter,
    ) {
    }
}
