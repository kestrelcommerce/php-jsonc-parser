<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser;

use Kestrel\JsoncParser\Edit\Edit;
use Kestrel\JsoncParser\Edit\Editor;
use Kestrel\JsoncParser\Scanner\JsonScanner;
use Kestrel\JsoncParser\Scanner\Scanner;
use Kestrel\JsoncParser\Parser\Parser;
use Kestrel\JsoncParser\Parser\ParseOptions;
use Kestrel\JsoncParser\Parser\JsonVisitor;
use Kestrel\JsoncParser\Parser\Node;
use Kestrel\JsoncParser\Parser\Location;
use Kestrel\JsoncParser\Edit\Range;
use Kestrel\JsoncParser\Edit\ModificationOptions;
use Kestrel\JsoncParser\Format\FormattingOptions;
use Kestrel\JsoncParser\Format\Formatter;

/**
 * Main facade class for the JSONC Parser
 * Provides convenient static methods for all parsing operations
 */
final class JsoncParser
{
    /**
     * Create a scanner for tokenizing JSON/JSONC text
     *
     * @param string $text The text to scan
     * @param bool $ignoreTrivia Whether to skip whitespace and comments
     * @return JsonScanner The scanner instance
     */
    public static function createScanner(string $text, bool $ignoreTrivia = false): JsonScanner
    {
        return Scanner::create($text, $ignoreTrivia);
    }

    /**
     * Parse JSON/JSONC text and return the evaluated value
     *
     * @param string $text The JSON/JSONC text to parse
     * @param array<\Kestrel\JsoncParser\Parser\ParseError> $errors Array to collect parse errors
     * @param ParseOptions|null $options Parser options
     * @return mixed The parsed value (array, object, scalar, or null)
     */
    public static function parse(string $text, array &$errors = [], ?ParseOptions $options = null): mixed
    {
        return Parser::parse($text, $errors, $options);
    }

    /**
     * Parse JSON/JSONC text and return a DOM tree
     *
     * @param string $text The JSON/JSONC text to parse
     * @param array<\Kestrel\JsoncParser\Parser\ParseError> $errors Array to collect parse errors
     * @param ParseOptions|null $options Parser options
     * @return Node|null The root node of the parse tree
     */
    public static function parseTree(string $text, array &$errors = [], ?ParseOptions $options = null): ?Node
    {
        return Parser::parseTree($text, $errors, $options);
    }

    /**
     * Visit JSON/JSONC text using SAX-style visitor pattern
     *
     * @param string $text The JSON/JSONC text to parse
     * @param JsonVisitor $visitor The visitor to call for events
     * @param ParseOptions|null $options Parser options
     * @return mixed The result from visitor callbacks
     */
    public static function visit(string $text, JsonVisitor $visitor, ?ParseOptions $options = null): mixed
    {
        return Parser::visit($text, $visitor, $options);
    }

    /**
     * Strip comments from JSONC text
     *
     * @param string $text The JSONC text
     * @param string|null $replaceCh Optional character to replace comments with (preserves structure)
     * @return string JSON text without comments
     */
    public static function stripComments(string $text, ?string $replaceCh = null): string
    {
        return Parser::stripComments($text, $replaceCh);
    }

    /**
     * Get the location in the JSON document at the given offset
     *
     * @param string $text The JSON document
     * @param int $position Offset in the document (0-based)
     * @return Location The location object with path information
     */
    public static function getLocation(string $text, int $position): Location
    {
        return Parser::getLocation($text, $position);
    }

    /**
     * Find a node at the given path in the JSON DOM tree
     *
     * @param Node|null $root The root node to search from
     * @param array<string|int> $path The path segments (property names and array indices)
     * @return Node|null The found node or null
     */
    public static function findNodeAtLocation(?Node $root, array $path): ?Node
    {
        return Parser::findNodeAtLocation($root, $path);
    }

    /**
     * Find the innermost node at the given offset
     *
     * @param Node $node The root node to search from
     * @param int $offset The offset in the document (0-based)
     * @param bool $includeRightBound Whether to include nodes at the right boundary
     * @return Node|null The found node or null
     */
    public static function findNodeAtOffset(Node $node, int $offset, bool $includeRightBound = false): ?Node
    {
        return Parser::findNodeAtOffset($node, $offset, $includeRightBound);
    }

    /**
     * Get the JSON path (array of segments) of the given node
     *
     * @param Node $node The node to get the path for
     * @return array<string|int> The path segments
     */
    public static function getNodePath(Node $node): array
    {
        return Parser::getNodePath($node);
    }

    /**
     * Evaluate the value represented by a DOM node
     *
     * @param Node $node The node to evaluate
     * @return mixed The evaluated value (array, object, scalar, or null)
     */
    public static function getNodeValue(Node $node): mixed
    {
        return Parser::getNodeValue($node);
    }

    /**
     * Format JSON/JSONC text
     *
     * @param string $documentText The document text to format
     * @param Range|null $range Optional range to format (null formats entire document)
     * @param FormattingOptions $options Formatting options
     * @return array<Edit> Array of edit operations to apply
     */
    public static function format(string $documentText, ?Range $range, FormattingOptions $options): array
    {
        return Formatter::format($documentText, $range, $options);
    }

    /**
     * Modify JSON/JSONC text at the given path
     *
     * @param string $text The JSON/JSONC text to modify
     * @param array<string|int> $path The path to modify
     * @param mixed $value The new value (or null to remove)
     * @param ModificationOptions $options Modification options
     * @return array<Edit> Array of edit operations to apply
     */
    public static function modify(string $text, array $path, mixed $value, ModificationOptions $options): array
    {
        return Editor::modify($text, $path, $value, $options);
    }

    /**
     * Apply edit operations to text
     *
     * @param string $text The text to modify
     * @param array<Edit> $edits The edit operations (must be sorted by offset)
     * @return string The modified text
     */
    public static function applyEdits(string $text, array $edits): string
    {
        return Editor::applyEdits($text, $edits);
    }
}
