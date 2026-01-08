<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Scanner;

/**
 * JSON Scanner interface - represents a scanner at a position in the input string
 */
interface JsonScanner
{
    /**
     * Sets the scan position to a new offset. A call to 'scan' is needed to get the first token.
     */
    public function setPosition(int $pos): void;

    /**
     * Read the next token. Returns the token code.
     */
    public function scan(): SyntaxKind;

    /**
     * Returns the zero-based current scan position, which is after the last read token.
     */
    public function getPosition(): int;

    /**
     * Returns the last read token.
     */
    public function getToken(): SyntaxKind;

    /**
     * Returns the last read token value.
     * The value for strings is the decoded string content.
     * For numbers, it's of type string, for boolean it's true or false.
     */
    public function getTokenValue(): string;

    /**
     * The zero-based start offset of the last read token.
     */
    public function getTokenOffset(): int;

    /**
     * The length of the last read token.
     */
    public function getTokenLength(): int;

    /**
     * The zero-based start line number of the last read token.
     */
    public function getTokenStartLine(): int;

    /**
     * The zero-based start character (column) of the last read token.
     */
    public function getTokenStartCharacter(): int;

    /**
     * An error code of the last scan.
     */
    public function getTokenError(): ScanError;
}
