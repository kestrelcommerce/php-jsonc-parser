<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Parser;

/**
 * Represents a node in the JSON AST
 */
class Node
{
    /**
     * @param NodeType $type Node type
     * @param int $offset Start position in the text
     * @param int $length Length of the node
     * @param mixed $value The evaluated value (for literals)
     * @param int|null $colonOffset Position of the colon (for properties)
     * @param Node|null $parent Parent node
     * @param Node[]|null $children Child nodes
     */
    public function __construct(
        public readonly NodeType $type,
        public readonly int $offset,
        public int $length,
        public readonly mixed $value = null,
        public readonly ?int $colonOffset = null,
        public ?Node $parent = null,
        public ?array $children = null,
    ) {
    }
}
