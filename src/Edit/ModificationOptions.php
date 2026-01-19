<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Edit;

use Closure;
use Kestrel\JsoncParser\Format\FormattingOptions;

/**
 * Options for modifying JSON
 */
readonly class ModificationOptions
{
    /**
     * @param  FormattingOptions|null  $formattingOptions  Formatting options for the modification
     * @param  bool  $isArrayInsertion  If true, insert into array instead of replace
     * @param  (Closure(array<string>): int)|null  $getInsertionIndex  Optional function to determine property insertion order
     */
    public function __construct(
        public ?FormattingOptions $formattingOptions = null,
        public bool $isArrayInsertion = false,
        public ?Closure $getInsertionIndex = null,
    ) {
    }
}
