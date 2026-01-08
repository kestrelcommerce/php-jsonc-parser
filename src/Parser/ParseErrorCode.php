<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Parser;

/**
 * Parse error codes
 */
enum ParseErrorCode: int
{
    case InvalidSymbol = 1;
    case InvalidNumberFormat = 2;
    case PropertyNameExpected = 3;
    case ValueExpected = 4;
    case ColonExpected = 5;
    case CommaExpected = 6;
    case CloseBraceExpected = 7;
    case CloseBracketExpected = 8;
    case EndOfFileExpected = 9;
    case InvalidCommentToken = 10;
    case UnexpectedEndOfComment = 11;
    case UnexpectedEndOfString = 12;
    case UnexpectedEndOfNumber = 13;
    case InvalidUnicode = 14;
    case InvalidEscapeCharacter = 15;
    case InvalidCharacter = 16;
}
