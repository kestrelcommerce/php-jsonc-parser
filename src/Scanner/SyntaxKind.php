<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Scanner;

/**
 * Token types returned by the scanner
 */
enum SyntaxKind: int
{
    case OpenBraceToken = 1;      // {
    case CloseBraceToken = 2;     // }
    case OpenBracketToken = 3;    // [
    case CloseBracketToken = 4;   // ]
    case CommaToken = 5;          // ,
    case ColonToken = 6;          // :
    case NullKeyword = 7;         // null
    case TrueKeyword = 8;         // true
    case FalseKeyword = 9;        // false
    case StringLiteral = 10;      // "string"
    case NumericLiteral = 11;     // 123, 1.23, 1.23e-4
    case LineCommentTrivia = 12;  // // comment
    case BlockCommentTrivia = 13; // /* comment */
    case LineBreakTrivia = 14;    // \n, \r\n, \r
    case Trivia = 15;             // whitespace
    case Unknown = 16;            // unknown token
    case EOF = 17;                // end of file
}
