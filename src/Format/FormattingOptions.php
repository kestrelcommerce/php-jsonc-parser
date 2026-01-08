<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Format;

/**
 * Options for formatting JSON
 */
readonly class FormattingOptions
{
    public function __construct(
        public int $tabSize = 4,
        public bool $insertSpaces = true,
        public string $eol = "\n",
        public bool $insertFinalNewline = false,
        public bool $keepLines = false,
    ) {
    }
}
