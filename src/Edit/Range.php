<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Edit;

/**
 * Represents a text range in the document
 */
readonly class Range
{
    /**
     * @param  int  $offset  The start offset of the range
     * @param  int  $length  The length of the range (must not be negative)
     */
    public function __construct(
        public int $offset,
        public int $length,
    ) {
    }
}
