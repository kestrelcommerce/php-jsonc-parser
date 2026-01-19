<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Parser;

use Closure;

/**
 * Visitor interface for SAX-style JSON parsing
 * All methods are optional - implement only the events you need to handle
 */
interface JsonVisitor
{
    /**
     * Invoked when an open brace is encountered and an object is started
     * Return false to skip visiting the object's properties
     *
     * @param int $offset Global offset within the JSON document
     * @param int $length Length of the opening brace token
     * @param int $startLine Line number (0-based)
     * @param int $startCharacter Column number (0-based)
     * @param Closure $pathSupplier Supplier function that returns the current JSONPath
     * @return bool|null Return false to skip this object's properties
     */
    public function onObjectBegin(
        int $offset,
        int $length,
        int $startLine,
        int $startCharacter,
        Closure $pathSupplier
    ): ?bool;

    /**
     * Invoked when a property is encountered
     *
     * @param  string  $property  The property name
     * @param  int  $offset  Offset of the property name
     * @param  int  $length  Length of the property name
     * @param  int  $startLine  Line number (0-based)
     * @param  int  $startCharacter  Column number (0-based)
     * @param  Closure  $pathSupplier  Supplier that returns the path to the enclosing object
     */
    public function onObjectProperty(
        string $property,
        int $offset,
        int $length,
        int $startLine,
        int $startCharacter,
        Closure $pathSupplier
    ): void;

    /**
     * Invoked when a closing brace is encountered and an object is completed
     *
     * @param  int  $offset  Offset of the closing brace
     * @param  int  $length  Length of the closing brace token
     * @param  int  $startLine  Line number (0-based)
     * @param  int  $startCharacter  Column number (0-based)
     */
    public function onObjectEnd(
        int $offset,
        int $length,
        int $startLine,
        int $startCharacter
    ): void;

    /**
     * Invoked when an open bracket is encountered
     * Return false to skip visiting the array's items
     *
     * @param  int  $offset  Offset of the opening bracket
     * @param  int  $length  Length of the opening bracket token
     * @param  int  $startLine  Line number (0-based)
     * @param  int  $startCharacter  Column number (0-based)
     * @param  Closure  $pathSupplier  Supplier function that returns the current JSONPath
     * @return bool|null Return false to skip this array's items
     */
    public function onArrayBegin(
        int $offset,
        int $length,
        int $startLine,
        int $startCharacter,
        Closure $pathSupplier
    ): ?bool;

    /**
     * Invoked when a closing bracket is encountered
     *
     * @param  int  $offset  Offset of the closing bracket
     * @param  int  $length  Length of the closing bracket token
     * @param  int  $startLine  Line number (0-based)
     * @param  int  $startCharacter  Column number (0-based)
     */
    public function onArrayEnd(
        int $offset,
        int $length,
        int $startLine,
        int $startCharacter
    ): void;

    /**
     * Invoked when a literal value is encountered
     *
     * @param  mixed  $value  The literal value (string, number, boolean, or null)
     * @param  int  $offset  Offset of the literal
     * @param  int  $length  Length of the literal
     * @param  int  $startLine  Line number (0-based)
     * @param  int  $startCharacter  Column number (0-based)
     * @param  Closure  $pathSupplier  Supplier function that returns the current JSONPath
     */
    public function onLiteralValue(
        mixed $value,
        int $offset,
        int $length,
        int $startLine,
        int $startCharacter,
        Closure $pathSupplier
    ): void;

    /**
     * Invoked when a comma or colon separator is encountered
     *
     * @param  string  $character  The separator character
     * @param  int  $offset  Offset of the separator
     * @param  int  $length  Length of the separator token
     * @param  int  $startLine  Line number (0-based)
     * @param  int  $startCharacter  Column number (0-based)
     */
    public function onSeparator(
        string $character,
        int $offset,
        int $length,
        int $startLine,
        int $startCharacter
    ): void;

    /**
     * Invoked when a comment is encountered (if comments are allowed)
     *
     * @param  int  $offset  Offset of the comment
     * @param  int  $length  Length of the comment
     * @param  int  $startLine  Line number (0-based)
     * @param  int  $startCharacter  Column number (0-based)
     */
    public function onComment(
        int $offset,
        int $length,
        int $startLine,
        int $startCharacter
    ): void;

    /**
     * Invoked when an error is encountered
     *
     * @param  ParseErrorCode  $error  The error code
     * @param  int  $offset  Offset of the error
     * @param  int  $length  Length of the erroneous token
     * @param  int  $startLine  Line number (0-based)
     * @param  int  $startCharacter  Column number (0-based)
     */
    public function onError(
        ParseErrorCode $error,
        int $offset,
        int $length,
        int $startLine,
        int $startCharacter
    ): void;
}
