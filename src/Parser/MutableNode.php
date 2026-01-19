<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Parser;

/**
 * Mutable node used during tree construction.
 * This is converted to an immutable Node after parsing is complete.
 *
 * @internal
 */
class MutableNode
{
    public string $type;
    public int $offset;
    public int $length;
    public mixed $value = null;
    public ?int $colonOffset = null;
    public ?MutableNode $parent = null;
    /** @var array<MutableNode> */
    public array $children = [];

    public function __construct(
        string $type,
        int $offset,
        int $length = -1,
        ?MutableNode $parent = null
    ) {
        $this->type = $type;
        $this->offset = $offset;
        $this->length = $length;
        $this->parent = $parent;
    }

    /**
     * Convert this mutable node to an immutable Node with correct parent references.
     */
    public function toNode(?Node $parent = null): Node
    {
        $nodeType = match ($this->type) {
            'object' => NodeType::Object,
            'array' => NodeType::Array,
            'property' => NodeType::Property,
            'string' => NodeType::String,
            'number' => NodeType::Number,
            'boolean' => NodeType::Boolean,
            'null' => NodeType::Null,
            default => NodeType::Null,
        };

        // Determine initial children value
        $initialChildren = null;
        if ($this->children !== []) {
            $initialChildren = [];
        } elseif (in_array($this->type, ['object', 'array', 'property'], true)) {
            // These types always have a children array (even if empty)
            $initialChildren = [];
        }

        $node = new Node(
            $nodeType,
            $this->offset,
            $this->length,
            $this->value,
            $this->colonOffset,
            $parent,
            $initialChildren
        );

        // Convert children
        if ($initialChildren !== null) {
            $convertedChildren = [];
            foreach ($this->children as $child) {
                $convertedChildren[] = $child->toNode($node);
            }
            $node->children = $convertedChildren;
        }

        return $node;
    }
}
