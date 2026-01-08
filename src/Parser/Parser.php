<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Parser;

use Kestrel\JsoncParser\Scanner\Scanner;
use Kestrel\JsoncParser\Scanner\SyntaxKind;

/**
 * JSONC Parser
 * Parses JSON with comments using visitor pattern, direct evaluation, or tree building
 */
final class Parser
{
    /**
     * Parse JSONC and return the evaluated value
     *
     * @param string $text The JSONC text to parse
     * @param array<ParseError> $errors Array to collect errors
     * @param ParseOptions|null $options Parser options
     * @return mixed The parsed value
     */
    public static function parse(string $text, array &$errors = [], ?ParseOptions $options = null): mixed
    {
        $currentParent = ['__root__' => null];
        $currentProperty = '__root__';
        $parentStack = [];
        $isArray = false; // Track if current parent is an array (true) or object (false)

        $visitor = new class ($errors, $currentParent, $currentProperty, $parentStack, $isArray) implements JsonVisitor {
            public function __construct(
                private array &$errors,
                private array &$currentParent,
                private mixed &$currentProperty,
                private array &$parentStack,
                private bool &$isArray
            ) {
            }

            public function onObjectBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
            {
                $object = [];

                // Add to parent
                if ($this->isArray) {
                    $this->currentParent[] = &$object;
                } else {
                    $this->currentParent[$this->currentProperty] = &$object;
                }

                // Push current context and switch to new object
                $this->parentStack[] = [&$this->currentParent, $this->currentProperty, $this->isArray];
                $this->currentParent = &$object;
                $this->currentProperty = null;
                $this->isArray = false; // Object context

                return null;
            }

            public function onObjectProperty(string $property, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): void
            {
                $this->currentProperty = $property;
            }

            public function onObjectEnd(int $offset, int $length, int $startLine, int $startCharacter): void
            {
                // Pop parent context
                $context = array_pop($this->parentStack);
                $this->currentParent = &$context[0];
                $this->currentProperty = $context[1];
                $this->isArray = $context[2];
            }

            public function onArrayBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
            {
                $array = [];

                // Add to parent
                if ($this->isArray) {
                    $this->currentParent[] = &$array;
                } else {
                    $this->currentParent[$this->currentProperty] = &$array;
                }

                // Push current context and switch to new array
                $this->parentStack[] = [&$this->currentParent, $this->currentProperty, $this->isArray];
                $this->currentParent = &$array;
                $this->currentProperty = null;
                $this->isArray = true; // Array context

                return null;
            }

            public function onArrayEnd(int $offset, int $length, int $startLine, int $startCharacter): void
            {
                // Pop parent context
                $context = array_pop($this->parentStack);
                $this->currentParent = &$context[0];
                $this->currentProperty = $context[1];
                $this->isArray = $context[2];
            }

            public function onLiteralValue(mixed $value, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): void
            {
                if ($this->isArray) {
                    $this->currentParent[] = $value;
                } else {
                    $this->currentParent[$this->currentProperty] = $value;
                }
            }

            public function onSeparator(string $character, int $offset, int $length, int $startLine, int $startCharacter): void
            {
                // No-op for parse
            }

            public function onComment(int $offset, int $length, int $startLine, int $startCharacter): void
            {
                // No-op for parse
            }

            public function onError(ParseErrorCode $error, int $offset, int $length, int $startLine, int $startCharacter): void
            {
                $this->errors[] = new ParseError($error, $offset, $length, $startLine, $startCharacter);
            }
        };

        self::visit($text, $visitor, $options);
        return $currentParent['__root__'];
    }

    /**
     * Parse JSONC and return a tree representation
     *
     * @param string $text The JSONC text to parse
     * @param array<ParseError> $errors Array to collect errors
     * @param ParseOptions|null $options Parser options
     * @return Node|null The root node
     */
    public static function parseTree(string $text, array &$errors = [], ?ParseOptions $options = null): ?Node
    {
        // Use stdClass for mutable node during construction (like TypeScript)
        $currentParent = (object)['type' => 'array', 'offset' => -1, 'length' => -1, 'children' => []];

        $ensurePropertyComplete = function (int $endOffset) use (&$currentParent): void {
            if ($currentParent->type === 'property') {
                $currentParent->length = $endOffset - $currentParent->offset;
                $currentParent = $currentParent->parent;
            }
        };

        $onValue = function (object $valueNode) use (&$currentParent): object {
            $currentParent->children[] = $valueNode;
            return $valueNode;
        };

        $visitor = new class ($onValue, $ensurePropertyComplete, $errors, $currentParent) implements JsonVisitor {
            public function __construct(
                private \Closure $onValue,
                private \Closure $ensurePropertyComplete,
                private array &$errors,
                private object &$currentParent
            ) {
            }

            public function onObjectBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
            {
                $this->currentParent = ($this->onValue)((object)['type' => 'object', 'offset' => $offset, 'length' => -1, 'parent' => $this->currentParent, 'children' => []]);
                return null;
            }

            public function onObjectProperty(string $property, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): void
            {
                $this->currentParent = ($this->onValue)((object)['type' => 'property', 'offset' => $offset, 'length' => -1, 'parent' => $this->currentParent, 'children' => []]);
                $this->currentParent->children[] = (object)['type' => 'string', 'value' => $property, 'offset' => $offset, 'length' => $length, 'parent' => $this->currentParent];
            }

            public function onObjectEnd(int $offset, int $length, int $startLine, int $startCharacter): void
            {
                ($this->ensurePropertyComplete)($offset + $length);
                $this->currentParent->length = $offset + $length - $this->currentParent->offset;
                $this->currentParent = $this->currentParent->parent;
                ($this->ensurePropertyComplete)($offset + $length);
            }

            public function onArrayBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
            {
                $this->currentParent = ($this->onValue)((object)['type' => 'array', 'offset' => $offset, 'length' => -1, 'parent' => $this->currentParent, 'children' => []]);
                return null;
            }

            public function onArrayEnd(int $offset, int $length, int $startLine, int $startCharacter): void
            {
                $this->currentParent->length = $offset + $length - $this->currentParent->offset;
                $this->currentParent = $this->currentParent->parent;
                ($this->ensurePropertyComplete)($offset + $length);
            }

            public function onLiteralValue(mixed $value, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): void
            {
                ($this->onValue)((object)['type' => self::getNodeType($value), 'value' => $value, 'offset' => $offset, 'length' => $length, 'parent' => $this->currentParent]);
                ($this->ensurePropertyComplete)($offset + $length);
            }

            public function onSeparator(string $character, int $offset, int $length, int $startLine, int $startCharacter): void
            {
                if ($this->currentParent->type === 'property') {
                    if ($character === ':') {
                        $this->currentParent->colonOffset = $offset;
                    } elseif ($character === ',') {
                        ($this->ensurePropertyComplete)($offset);
                    }
                }
            }

            public function onComment(int $offset, int $length, int $startLine, int $startCharacter): void
            {
                // No-op for parseTree
            }

            public function onError(ParseErrorCode $error, int $offset, int $length, int $startLine, int $startCharacter): void
            {
                $this->errors[] = new ParseError($error, $offset, $length, $startLine, $startCharacter);
            }

            private static function getNodeType(mixed $value): string
            {
                return match (true) {
                    is_string($value) => 'string',
                    is_int($value) || is_float($value) => 'number',
                    is_bool($value) => 'boolean',
                    is_null($value) => 'null',
                    default => 'null',
                };
            }
        };

        self::visit($text, $visitor, $options);

        // Convert mutable stdClass tree to readonly Node objects
        $result = $currentParent->children[0] ?? null;
        if ($result === null) {
            return null;
        }

        // Two-pass conversion: first without parents, then add parents
        return self::objectToNodeWithParents($result, null);
    }

    /**
     * Convert stdClass to Node with correct parent references
     */
    private static function objectToNodeWithParents(object $nodeObj, ?Node $parent): Node
    {
        $type = match ($nodeObj->type) {
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
        if (isset($nodeObj->children) && is_array($nodeObj->children)) {
            // Arrays and objects have children array (even if empty)
            $initialChildren = [];
        }

        // Create node
        $node = new Node(
            $type,
            $nodeObj->offset,
            $nodeObj->length,
            $nodeObj->value ?? null,
            $nodeObj->colonOffset ?? null,
            $parent,
            $initialChildren
        );

        // Convert children if present
        if ($initialChildren !== null && !empty($nodeObj->children)) {
            $children = [];
            foreach ($nodeObj->children as $child) {
                $children[] = self::objectToNodeWithParents($child, $node);
            }
            $node->children = $children;
        }

        return $node;
    }

    /**
     * Visit JSONC with a visitor pattern
     *
     * @param string $text The JSONC text to parse
     * @param JsonVisitor $visitor The visitor to call for events
     * @param ParseOptions|null $options Parser options
     * @return mixed The result from visitor callbacks
     */
    public static function visit(string $text, JsonVisitor $visitor, ?ParseOptions $options = null): mixed
    {
        $options ??= new ParseOptions();
        $scanner = Scanner::create($text, false);

        $jsonPath = [];
        $suppressedCallbacks = 0;

        $onValue = function (mixed $value) use (&$jsonPath, $visitor, $scanner, &$suppressedCallbacks): void {
            if ($suppressedCallbacks > 0) {
                $suppressedCallbacks--;
            } else {
                $pathSupplier = fn () => $jsonPath;
                $visitor->onLiteralValue(
                    $value,
                    $scanner->getTokenOffset(),
                    $scanner->getTokenLength(),
                    $scanner->getTokenStartLine(),
                    $scanner->getTokenStartCharacter(),
                    $pathSupplier
                );
            }
        };

        $result = null;
        self::scanNext($scanner, $options, $visitor);
        $result = self::parseValue($scanner, $visitor, $onValue, $jsonPath, $suppressedCallbacks, $options);

        if ($scanner->getToken() !== SyntaxKind::EOF) {
            self::handleError($visitor, $scanner, ParseErrorCode::EndOfFileExpected);
        }

        return $result;
    }

    /**
     * Strip comments from JSONC
     *
     * @param string $text The JSONC text
     * @param string|null $replaceCh Optional character to replace comments with
     * @return string JSON without comments
     */
    public static function stripComments(string $text, ?string $replaceCh = null): string
    {
        $scanner = Scanner::create($text, false);
        $parts = [];
        $offset = 0;

        do {
            $pos = $scanner->getPosition();
            $kind = $scanner->scan();

            if ($kind === SyntaxKind::LineCommentTrivia || $kind === SyntaxKind::BlockCommentTrivia || $kind === SyntaxKind::EOF) {
                // Add text before comment/EOF
                if ($offset !== $pos) {
                    $parts[] = substr($text, $offset, $pos - $offset);
                }

                // Replace comment content - preserve newlines
                if ($replaceCh !== null && $kind !== SyntaxKind::EOF) {
                    $parts[] = preg_replace('/[^\r\n]/', $replaceCh, $scanner->getTokenValue());
                }

                $offset = $scanner->getPosition();
            }
        } while ($kind !== SyntaxKind::EOF);

        return implode('', $parts);
    }

    /**
     * For a given offset, evaluate the location in JSON document
     *
     * @param string $text The JSON document
     * @param int $position Offset in the document
     * @return Location The location object
     */
    public static function getLocation(string $text, int $position): Location
    {
        $path = [];
        $previousNode = null;
        $isAtPropertyKey = false;

        $visitor = new class ($position, $path, $previousNode, $isAtPropertyKey) implements JsonVisitor {
            public function __construct(
                private int $position,
                private array &$path,
                private ?Node &$previousNode,
                private bool &$isAtPropertyKey
            ) {
            }

            public function onObjectBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
            {
                if ($offset < $this->position && $offset + $length >= $this->position) {
                    $this->path = $pathSupplier();
                    return null;
                }
                return false;
            }

            public function onObjectProperty(string $property, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): void
            {
                if ($offset <= $this->position && $offset + $length > $this->position) {
                    $this->path = $pathSupplier();
                    $this->isAtPropertyKey = true;
                }
            }

            public function onObjectEnd(int $offset, int $length, int $startLine, int $startCharacter): void
            {
                // No-op
            }

            public function onArrayBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
            {
                if ($offset < $this->position && $offset + $length >= $this->position) {
                    $this->path = $pathSupplier();
                    return null;
                }
                return false;
            }

            public function onArrayEnd(int $offset, int $length, int $startLine, int $startCharacter): void
            {
                // No-op
            }

            public function onLiteralValue(mixed $value, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): void
            {
                if ($offset <= $this->position && $offset + $length > $this->position) {
                    $this->path = $pathSupplier();
                    // Store as a simple value node for previousNode
                    $this->previousNode = new Node(
                        is_string($value) ? NodeType::String :
                        (is_int($value) || is_float($value) ? NodeType::Number :
                        (is_bool($value) ? NodeType::Boolean : NodeType::Null)),
                        $offset,
                        $length,
                        $value
                    );
                }
            }

            public function onSeparator(string $character, int $offset, int $length, int $startLine, int $startCharacter): void
            {
                // No-op
            }

            public function onComment(int $offset, int $length, int $startLine, int $startCharacter): void
            {
                // No-op
            }

            public function onError(ParseErrorCode $error, int $offset, int $length, int $startLine, int $startCharacter): void
            {
                // No-op
            }
        };

        self::visit($text, $visitor);
        return new Location($path, $previousNode, $isAtPropertyKey);
    }

    /**
     * Find node at given path in JSON DOM
     *
     * @param Node|null $root The root node to search from
     * @param array<string|int> $path The path to search for
     * @return Node|null The found node or null
     */
    public static function findNodeAtLocation(?Node $root, array $path): ?Node
    {
        if ($root === null) {
            return null;
        }

        $node = $root;
        foreach ($path as $segment) {
            if ($node->type === NodeType::Object) {
                $found = false;
                foreach ($node->children ?? [] as $propertyNode) {
                    if ($propertyNode->type === NodeType::Property &&
                        $propertyNode->children !== null &&
                        count($propertyNode->children) > 0 &&
                        $propertyNode->children[0]->value === $segment &&
                        count($propertyNode->children) > 1) {
                        $node = $propertyNode->children[1];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    return null;
                }
            } elseif ($node->type === NodeType::Array) {
                $index = (int) $segment;
                if ($index < 0 || $node->children === null || $index >= count($node->children)) {
                    return null;
                }
                $node = $node->children[$index];
            } else {
                return null;
            }
        }

        return $node;
    }

    /**
     * Find the innermost node at the given offset
     *
     * @param Node $node The node to search from
     * @param int $offset The offset to search for
     * @param bool $includeRightBound Whether to include nodes at the right boundary
     * @return Node|null The found node or null
     */
    public static function findNodeAtOffset(Node $node, int $offset, bool $includeRightBound = false): ?Node
    {
        if ($includeRightBound) {
            if ($offset < $node->offset || $offset > $node->offset + $node->length) {
                return null;
            }
        } else {
            if ($offset < $node->offset || $offset >= $node->offset + $node->length) {
                return null;
            }
        }

        // Recursively search children
        if ($node->children !== null) {
            foreach ($node->children as $child) {
                $found = self::findNodeAtOffset($child, $offset, $includeRightBound);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return $node;
    }

    /**
     * Get the JSON path of a given node
     *
     * @param Node $node The node to get the path for
     * @return array<string|int> The JSON path
     */
    public static function getNodePath(Node $node): array
    {
        if ($node->parent === null) {
            return [];
        }

        $path = self::getNodePath($node->parent);

        if ($node->parent->type === NodeType::Property) {
            $keyNode = $node->parent->children[0] ?? null;
            if ($keyNode !== null) {
                $path[] = $keyNode->value;
            }
        } elseif ($node->parent->type === NodeType::Array) {
            $index = 0;
            foreach ($node->parent->children ?? [] as $child) {
                if ($child === $node) {
                    break;
                }
                $index++;
            }
            $path[] = $index;
        }

        return $path;
    }

    /**
     * Evaluate the JavaScript value represented by a DOM node
     *
     * @param Node $node The node to evaluate
     * @return mixed The evaluated value
     */
    public static function getNodeValue(Node $node): mixed
    {
        return match ($node->type) {
            NodeType::Array => array_map(
                fn (Node $child) => self::getNodeValue($child),
                $node->children ?? []
            ),
            NodeType::Object => array_reduce(
                $node->children ?? [],
                function ($acc, Node $propertyNode) {
                    if ($propertyNode->type === NodeType::Property &&
                        $propertyNode->children !== null &&
                        count($propertyNode->children) === 2) {
                        $key = $propertyNode->children[0]->value;
                        $value = self::getNodeValue($propertyNode->children[1]);
                        $acc[$key] = $value;
                    }
                    return $acc;
                },
                []
            ),
            NodeType::Null => null,
            default => $node->value,
        };
    }

    private static function parseValue(
        $scanner,
        JsonVisitor $visitor,
        \Closure $onValue,
        array &$jsonPath,
        int &$suppressedCallbacks,
        ParseOptions $options
    ): mixed {
        $token = $scanner->getToken();

        return match ($token) {
            SyntaxKind::OpenBracketToken => self::parseArray($scanner, $visitor, $onValue, $jsonPath, $suppressedCallbacks, $options),
            SyntaxKind::OpenBraceToken => self::parseObject($scanner, $visitor, $onValue, $jsonPath, $suppressedCallbacks, $options),
            SyntaxKind::StringLiteral => self::parseLiteral($scanner, $visitor, $onValue, $options),
            SyntaxKind::NumericLiteral => self::parseNumericLiteral($scanner, $visitor, $onValue, $options),
            SyntaxKind::TrueKeyword => self::parseLiteral($scanner, $visitor, $onValue, $options, true),
            SyntaxKind::FalseKeyword => self::parseLiteral($scanner, $visitor, $onValue, $options, false),
            SyntaxKind::NullKeyword => self::parseLiteral($scanner, $visitor, $onValue, $options, null),
            default => self::handleUnexpectedToken($scanner, $visitor, $options)
        };
    }

    private static function parseArray($scanner, JsonVisitor $visitor, \Closure $onValue, array &$jsonPath, int &$suppressedCallbacks, ParseOptions $options): mixed
    {
        $pathSupplier = fn () => $jsonPath;

        if ($suppressedCallbacks > 0) {
            $suppressedCallbacks++;
        } else {
            $result = $visitor->onArrayBegin(
                $scanner->getTokenOffset(),
                $scanner->getTokenLength(),
                $scanner->getTokenStartLine(),
                $scanner->getTokenStartCharacter(),
                $pathSupplier
            );
            if ($result === false) {
                $suppressedCallbacks = 1;
            }
        }

        $jsonPath[] = 0;
        self::scanNext($scanner, $options, $visitor);

        $needsComma = false;
        while ($scanner->getToken() !== SyntaxKind::CloseBracketToken && $scanner->getToken() !== SyntaxKind::EOF) {
            if ($scanner->getToken() === SyntaxKind::CommaToken) {
                if (!$needsComma) {
                    self::handleError($visitor, $scanner, ParseErrorCode::ValueExpected);
                }
                if ($suppressedCallbacks === 0) {
                    $visitor->onSeparator(',', $scanner->getTokenOffset(), $scanner->getTokenLength(), $scanner->getTokenStartLine(), $scanner->getTokenStartCharacter());
                }
                self::scanNext($scanner, $options, $visitor);
                if ($scanner->getToken() === SyntaxKind::CloseBracketToken) {
                    if (!$options->allowTrailingComma) {
                        self::handleError($visitor, $scanner, ParseErrorCode::ValueExpected);
                    }
                    break;
                }
                $needsComma = false;
                $jsonPath[count($jsonPath) - 1]++;
                continue;
            }

            if ($needsComma) {
                self::handleError($visitor, $scanner, ParseErrorCode::CommaExpected);
            }

            self::parseValue($scanner, $visitor, $onValue, $jsonPath, $suppressedCallbacks, $options);
            $needsComma = true;
        }

        array_pop($jsonPath);

        if ($scanner->getToken() !== SyntaxKind::CloseBracketToken) {
            self::handleError($visitor, $scanner, ParseErrorCode::CloseBracketExpected);
        } else {
            if ($suppressedCallbacks > 0) {
                $suppressedCallbacks--;
            } else {
                $visitor->onArrayEnd(
                    $scanner->getTokenOffset(),
                    $scanner->getTokenLength(),
                    $scanner->getTokenStartLine(),
                    $scanner->getTokenStartCharacter()
                );
            }
            self::scanNext($scanner, $options, $visitor);
        }

        return null;
    }

    private static function parseObject($scanner, JsonVisitor $visitor, \Closure $onValue, array &$jsonPath, int &$suppressedCallbacks, ParseOptions $options): mixed
    {
        $pathSupplier = fn () => $jsonPath;

        if ($suppressedCallbacks > 0) {
            $suppressedCallbacks++;
        } else {
            $result = $visitor->onObjectBegin(
                $scanner->getTokenOffset(),
                $scanner->getTokenLength(),
                $scanner->getTokenStartLine(),
                $scanner->getTokenStartCharacter(),
                $pathSupplier
            );
            if ($result === false) {
                $suppressedCallbacks = 1;
            }
        }

        $jsonPath[] = '';
        self::scanNext($scanner, $options, $visitor);

        $needsComma = false;
        while ($scanner->getToken() !== SyntaxKind::CloseBraceToken && $scanner->getToken() !== SyntaxKind::EOF) {
            if ($scanner->getToken() === SyntaxKind::CommaToken) {
                if (!$needsComma) {
                    self::handleError($visitor, $scanner, ParseErrorCode::PropertyNameExpected);
                }
                if ($suppressedCallbacks === 0) {
                    $visitor->onSeparator(',', $scanner->getTokenOffset(), $scanner->getTokenLength(), $scanner->getTokenStartLine(), $scanner->getTokenStartCharacter());
                }
                self::scanNext($scanner, $options, $visitor);
                if ($scanner->getToken() === SyntaxKind::CloseBraceToken) {
                    if (!$options->allowTrailingComma) {
                        self::handleError($visitor, $scanner, ParseErrorCode::PropertyNameExpected);
                    }
                    break;
                }
                $needsComma = false;
                $jsonPath[count($jsonPath) - 1] = '';
                continue;
            }

            if ($needsComma) {
                self::handleError($visitor, $scanner, ParseErrorCode::CommaExpected);
            }

            if ($scanner->getToken() !== SyntaxKind::StringLiteral) {
                self::handleError($visitor, $scanner, ParseErrorCode::PropertyNameExpected);
                self::scanNext($scanner, $options, $visitor);
                continue;
            }

            $propertyName = $scanner->getTokenValue();
            $jsonPath[count($jsonPath) - 1] = $propertyName;

            if ($suppressedCallbacks === 0) {
                $visitor->onObjectProperty(
                    $propertyName,
                    $scanner->getTokenOffset(),
                    $scanner->getTokenLength(),
                    $scanner->getTokenStartLine(),
                    $scanner->getTokenStartCharacter(),
                    $pathSupplier
                );
            }

            self::scanNext($scanner, $options, $visitor);

            if ($scanner->getToken() !== SyntaxKind::ColonToken) {
                self::handleError($visitor, $scanner, ParseErrorCode::ColonExpected);
            } else {
                if ($suppressedCallbacks === 0) {
                    $visitor->onSeparator(':', $scanner->getTokenOffset(), $scanner->getTokenLength(), $scanner->getTokenStartLine(), $scanner->getTokenStartCharacter());
                }
                self::scanNext($scanner, $options, $visitor);
            }

            self::parseValue($scanner, $visitor, $onValue, $jsonPath, $suppressedCallbacks, $options);
            $needsComma = true;
        }

        array_pop($jsonPath);

        if ($scanner->getToken() !== SyntaxKind::CloseBraceToken) {
            self::handleError($visitor, $scanner, ParseErrorCode::CloseBraceExpected);
        } else {
            if ($suppressedCallbacks > 0) {
                $suppressedCallbacks--;
            } else {
                $visitor->onObjectEnd(
                    $scanner->getTokenOffset(),
                    $scanner->getTokenLength(),
                    $scanner->getTokenStartLine(),
                    $scanner->getTokenStartCharacter()
                );
            }
            self::scanNext($scanner, $options, $visitor);
        }

        return null;
    }

    private static function parseLiteral($scanner, JsonVisitor $visitor, \Closure $onValue, ParseOptions $options, mixed $value = '__USE_SCANNER_VALUE__'): mixed
    {
        if ($value === '__USE_SCANNER_VALUE__') {
            $value = $scanner->getTokenValue();
        }
        $onValue($value);
        self::scanNext($scanner, $options, $visitor);
        return null;
    }

    private static function parseNumericLiteral($scanner, JsonVisitor $visitor, \Closure $onValue, ParseOptions $options): mixed
    {
        $value = (float) $scanner->getTokenValue();
        if (floor($value) === $value) {
            $value = (int) $value;
        }
        $onValue($value);
        self::scanNext($scanner, $options, $visitor);
        return null;
    }

    private static function scanNext($scanner, ParseOptions $options, ?JsonVisitor $visitor = null): void
    {
        while (true) {
            $token = $scanner->scan();

            // Handle scan errors
            $scanError = $scanner->getTokenError();
            if ($scanError !== \Kestrel\JsoncParser\Scanner\ScanError::None && $visitor !== null) {
                $errorCode = match ($scanError) {
                    \Kestrel\JsoncParser\Scanner\ScanError::InvalidUnicode => ParseErrorCode::InvalidUnicode,
                    \Kestrel\JsoncParser\Scanner\ScanError::InvalidEscapeCharacter => ParseErrorCode::InvalidEscapeCharacter,
                    \Kestrel\JsoncParser\Scanner\ScanError::UnexpectedEndOfNumber => ParseErrorCode::UnexpectedEndOfNumber,
                    \Kestrel\JsoncParser\Scanner\ScanError::UnexpectedEndOfString => ParseErrorCode::UnexpectedEndOfString,
                    \Kestrel\JsoncParser\Scanner\ScanError::UnexpectedEndOfComment => $options->disallowComments ? null : ParseErrorCode::UnexpectedEndOfComment,
                    \Kestrel\JsoncParser\Scanner\ScanError::InvalidCharacter => ParseErrorCode::InvalidCharacter,
                    default => null,
                };

                if ($errorCode !== null) {
                    self::handleError($visitor, $scanner, $errorCode);
                }
            }

            // Handle token types
            if ($token === SyntaxKind::LineCommentTrivia || $token === SyntaxKind::BlockCommentTrivia) {
                if ($options->disallowComments) {
                    if ($visitor !== null) {
                        self::handleError($visitor, $scanner, ParseErrorCode::InvalidCommentToken);
                    }
                } elseif ($visitor !== null) {
                    $visitor->onComment(
                        $scanner->getTokenOffset(),
                        $scanner->getTokenLength(),
                        $scanner->getTokenStartLine(),
                        $scanner->getTokenStartCharacter()
                    );
                }
                continue; // Skip comments and scan next token
            }

            if ($token === SyntaxKind::Unknown && $visitor !== null) {
                self::handleError($visitor, $scanner, ParseErrorCode::InvalidSymbol);
                continue;
            }

            // Skip whitespace trivia
            if ($token === SyntaxKind::Trivia || $token === SyntaxKind::LineBreakTrivia) {
                continue;
            }

            // Return any other token
            break;
        }
    }

    private static function handleError(JsonVisitor $visitor, $scanner, ParseErrorCode $error): void
    {
        $visitor->onError(
            $error,
            $scanner->getTokenOffset(),
            $scanner->getTokenLength(),
            $scanner->getTokenStartLine(),
            $scanner->getTokenStartCharacter()
        );
    }

    private static function handleUnexpectedToken($scanner, JsonVisitor $visitor, ParseOptions $options): mixed
    {
        self::handleError($visitor, $scanner, match ($scanner->getToken()) {
            SyntaxKind::CloseBracketToken => ParseErrorCode::ValueExpected,
            SyntaxKind::CloseBraceToken => ParseErrorCode::ValueExpected,
            default => ParseErrorCode::ValueExpected,
        });

        self::scanNext($scanner, $options, $visitor);
        return null;
    }
}
