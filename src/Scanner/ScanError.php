<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Scanner;

/**
 * Scan error codes
 */
enum ScanError: int
{
    case None = 0;
    case UnexpectedEndOfComment = 1;
    case UnexpectedEndOfString = 2;
    case UnexpectedEndOfNumber = 3;
    case InvalidUnicode = 4;
    case InvalidEscapeCharacter = 5;
    case InvalidCharacter = 6;
}
