<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Edit;

/**
 * Represents a text modification operation
 */
readonly class Edit
{
    /**
     * @param  int  $offset  The start offset of the modification
     * @param  int  $length  The length of text to replace (0 = insert)
     * @param  string  $content  The new content (empty = delete)
     */
    public function __construct(
        public int $offset,
        public int $length,
        public string $content,
    ) {
    }
}
