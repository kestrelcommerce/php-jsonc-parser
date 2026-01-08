<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Parser;

/**
 * Options for parsing JSONC
 */
readonly class ParseOptions
{
    public function __construct(
        public bool $disallowComments = false,
        public bool $allowTrailingComma = false,
        public bool $allowEmptyContent = false,
    ) {
    }
}
