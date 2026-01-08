<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Util;

/**
 * Character code constants for efficient character comparison
 * Ported from TypeScript scanner implementation
 */
final class CharacterCodes
{
    // Line breaks
    public const LINE_FEED = 0x0A;          // \n
    public const CARRIAGE_RETURN = 0x0D;    // \r

    // Whitespace
    public const SPACE = 0x0020;            // " "
    public const TAB = 0x09;                // \t
    public const FORM_FEED = 0x0C;          // \f

    // Digits
    public const DIGIT_0 = 0x30;
    public const DIGIT_1 = 0x31;
    public const DIGIT_2 = 0x32;
    public const DIGIT_3 = 0x33;
    public const DIGIT_4 = 0x34;
    public const DIGIT_5 = 0x35;
    public const DIGIT_6 = 0x36;
    public const DIGIT_7 = 0x37;
    public const DIGIT_8 = 0x38;
    public const DIGIT_9 = 0x39;

    // Lowercase letters
    public const LOWER_A = 0x61;
    public const LOWER_B = 0x62;
    public const LOWER_C = 0x63;
    public const LOWER_D = 0x64;
    public const LOWER_E = 0x65;
    public const LOWER_F = 0x66;
    public const LOWER_G = 0x67;
    public const LOWER_H = 0x68;
    public const LOWER_I = 0x69;
    public const LOWER_J = 0x6A;
    public const LOWER_K = 0x6B;
    public const LOWER_L = 0x6C;
    public const LOWER_M = 0x6D;
    public const LOWER_N = 0x6E;
    public const LOWER_O = 0x6F;
    public const LOWER_P = 0x70;
    public const LOWER_Q = 0x71;
    public const LOWER_R = 0x72;
    public const LOWER_S = 0x73;
    public const LOWER_T = 0x74;
    public const LOWER_U = 0x75;
    public const LOWER_V = 0x76;
    public const LOWER_W = 0x77;
    public const LOWER_X = 0x78;
    public const LOWER_Y = 0x79;
    public const LOWER_Z = 0x7A;

    // Uppercase letters
    public const UPPER_A = 0x41;
    public const UPPER_B = 0x42;
    public const UPPER_C = 0x43;
    public const UPPER_D = 0x44;
    public const UPPER_E = 0x45;
    public const UPPER_F = 0x46;
    public const UPPER_G = 0x47;
    public const UPPER_H = 0x48;
    public const UPPER_I = 0x49;
    public const UPPER_J = 0x4A;
    public const UPPER_K = 0x4B;
    public const UPPER_L = 0x4C;
    public const UPPER_M = 0x4D;
    public const UPPER_N = 0x4E;
    public const UPPER_O = 0x4F;
    public const UPPER_P = 0x50;
    public const UPPER_Q = 0x51;
    public const UPPER_R = 0x52;
    public const UPPER_S = 0x53;
    public const UPPER_T = 0x54;
    public const UPPER_U = 0x55;
    public const UPPER_V = 0x56;
    public const UPPER_W = 0x57;
    public const UPPER_X = 0x58;
    public const UPPER_Y = 0x59;
    public const UPPER_Z = 0x5A;

    // Special characters
    public const ASTERISK = 0x2A;           // *
    public const BACKSLASH = 0x5C;          // \
    public const CLOSE_BRACE = 0x7D;        // }
    public const CLOSE_BRACKET = 0x5D;      // ]
    public const COLON = 0x3A;              // :
    public const COMMA = 0x2C;              // ,
    public const DOT = 0x2E;                // .
    public const DOUBLE_QUOTE = 0x22;       // "
    public const MINUS = 0x2D;              // -
    public const OPEN_BRACE = 0x7B;         // {
    public const OPEN_BRACKET = 0x5B;       // [
    public const PLUS = 0x2B;               // +
    public const SLASH = 0x2F;              // /

    private function __construct()
    {
        // Prevent instantiation
    }
}
