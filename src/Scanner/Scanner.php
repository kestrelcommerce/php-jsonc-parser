<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Scanner;

use Kestrel\JsoncParser\Util\CharacterCodes as CC;
use Kestrel\JsoncParser\Util\StringHelper;

/**
 * JSON/JSONC Scanner implementation
 * Tokenizes JSON text with support for JavaScript-style comments
 */
final class Scanner implements JsonScanner
{
    private string $text;
    private int $len;
    private int $pos = 0;
    private string $value = '';
    private int $tokenOffset = 0;
    private SyntaxKind $token;
    private int $lineNumber = 0;
    private int $lineStartOffset = 0;
    private int $tokenLineStartOffset = 0;
    private int $prevTokenLineStartOffset = 0;
    private ScanError $scanError;

    /**
     * Create a new scanner
     *
     * @param string $text The text to scan
     * @param bool $ignoreTrivia If true, whitespace and comments are skipped
     */
    public static function create(string $text, bool $ignoreTrivia = false): JsonScanner
    {
        return new self($text, $ignoreTrivia);
    }

    private function __construct(
        string $text,
        private readonly bool $ignoreTrivia
    ) {
        $this->text = $text;
        $this->len = StringHelper::length($text);
        $this->token = SyntaxKind::Unknown;
        $this->scanError = ScanError::None;
    }

    public function setPosition(int $pos): void
    {
        $this->pos = $pos;
        $this->value = '';
        $this->tokenOffset = 0;
        $this->token = SyntaxKind::Unknown;
        $this->scanError = ScanError::None;
    }

    public function scan(): SyntaxKind
    {
        return $this->ignoreTrivia ? $this->scanNextNonTrivia() : $this->scanNext();
    }

    public function getPosition(): int
    {
        return $this->pos;
    }

    public function getToken(): SyntaxKind
    {
        return $this->token;
    }

    public function getTokenValue(): string
    {
        return $this->value;
    }

    public function getTokenOffset(): int
    {
        return $this->tokenOffset;
    }

    public function getTokenLength(): int
    {
        return $this->pos - $this->tokenOffset;
    }

    public function getTokenStartLine(): int
    {
        return $this->lineStartOffset;
    }

    public function getTokenStartCharacter(): int
    {
        return $this->tokenOffset - $this->prevTokenLineStartOffset;
    }

    public function getTokenError(): ScanError
    {
        return $this->scanError;
    }

    private function scanHexDigits(int $count, bool $exact = false): int
    {
        $digits = 0;
        $value = 0;

        while ($digits < $count || !$exact) {
            $ch = StringHelper::charCodeAt($this->text, $this->pos);

            if ($ch >= CC::DIGIT_0 && $ch <= CC::DIGIT_9) {
                $value = $value * 16 + $ch - CC::DIGIT_0;
            } elseif ($ch >= CC::UPPER_A && $ch <= CC::UPPER_F) {
                $value = $value * 16 + $ch - CC::UPPER_A + 10;
            } elseif ($ch >= CC::LOWER_A && $ch <= CC::LOWER_F) {
                $value = $value * 16 + $ch - CC::LOWER_A + 10;
            } else {
                break;
            }

            $this->pos++;
            $digits++;
        }

        if ($digits < $count) {
            $value = -1;
        }

        return $value;
    }

    private function scanNumber(): string
    {
        $start = $this->pos;

        if (StringHelper::charCodeAt($this->text, $this->pos) === CC::DIGIT_0) {
            $this->pos++;
        } else {
            $this->pos++;
            while ($this->pos < $this->len && $this->isDigit(StringHelper::charCodeAt($this->text, $this->pos))) {
                $this->pos++;
            }
        }

        if ($this->pos < $this->len && StringHelper::charCodeAt($this->text, $this->pos) === CC::DOT) {
            $this->pos++;
            if ($this->pos < $this->len && $this->isDigit(StringHelper::charCodeAt($this->text, $this->pos))) {
                $this->pos++;
                while ($this->pos < $this->len && $this->isDigit(StringHelper::charCodeAt($this->text, $this->pos))) {
                    $this->pos++;
                }
            } else {
                $this->scanError = ScanError::UnexpectedEndOfNumber;
                return StringHelper::substring($this->text, $start, $this->pos);
            }
        }

        $end = $this->pos;
        if ($this->pos < $this->len) {
            $ch = StringHelper::charCodeAt($this->text, $this->pos);
            if ($ch === CC::UPPER_E || $ch === CC::LOWER_E) {
                $this->pos++;
                if ($this->pos < $this->len) {
                    $ch = StringHelper::charCodeAt($this->text, $this->pos);
                    if ($ch === CC::PLUS || $ch === CC::MINUS) {
                        $this->pos++;
                    }
                }
                if ($this->pos < $this->len && $this->isDigit(StringHelper::charCodeAt($this->text, $this->pos))) {
                    $this->pos++;
                    while ($this->pos < $this->len && $this->isDigit(StringHelper::charCodeAt($this->text, $this->pos))) {
                        $this->pos++;
                    }
                    $end = $this->pos;
                } else {
                    $this->scanError = ScanError::UnexpectedEndOfNumber;
                }
            }
        }

        return StringHelper::substring($this->text, $start, $end);
    }

    private function scanString(): string
    {
        $result = '';
        $start = $this->pos;

        while (true) {
            if ($this->pos >= $this->len) {
                $result .= StringHelper::substring($this->text, $start, $this->pos);
                $this->scanError = ScanError::UnexpectedEndOfString;
                break;
            }

            $ch = StringHelper::charCodeAt($this->text, $this->pos);

            if ($ch === CC::DOUBLE_QUOTE) {
                $result .= StringHelper::substring($this->text, $start, $this->pos);
                $this->pos++;
                break;
            }

            if ($ch === CC::BACKSLASH) {
                $result .= StringHelper::substring($this->text, $start, $this->pos);
                $this->pos++;

                if ($this->pos >= $this->len) {
                    $this->scanError = ScanError::UnexpectedEndOfString;
                    break;
                }

                $ch2 = StringHelper::charCodeAt($this->text, $this->pos++);

                switch ($ch2) {
                    case CC::DOUBLE_QUOTE:
                        $result .= '"';
                        break;
                    case CC::BACKSLASH:
                        $result .= '\\';
                        break;
                    case CC::SLASH:
                        $result .= '/';
                        break;
                    case CC::LOWER_B:
                        $result .= "\x08"; // backspace
                        break;
                    case CC::LOWER_F:
                        $result .= "\f";
                        break;
                    case CC::LOWER_N:
                        $result .= "\n";
                        break;
                    case CC::LOWER_R:
                        $result .= "\r";
                        break;
                    case CC::LOWER_T:
                        $result .= "\t";
                        break;
                    case CC::LOWER_U:
                        $ch3 = $this->scanHexDigits(4, true);
                        if ($ch3 >= 0) {
                            $result .= StringHelper::fromCharCode($ch3);
                        } else {
                            $this->scanError = ScanError::InvalidUnicode;
                        }
                        break;
                    default:
                        $this->scanError = ScanError::InvalidEscapeCharacter;
                }

                $start = $this->pos;
                continue;
            }

            if ($ch >= 0 && $ch <= 0x1f) {
                if ($this->isLineBreak($ch)) {
                    $result .= StringHelper::substring($this->text, $start, $this->pos);
                    $this->scanError = ScanError::UnexpectedEndOfString;
                    break;
                } else {
                    $this->scanError = ScanError::InvalidCharacter;
                    // mark as error but continue with string
                }
            }

            $this->pos++;
        }

        return $result;
    }

    private function scanNext(): SyntaxKind
    {
        $this->value = '';
        $this->scanError = ScanError::None;

        $this->tokenOffset = $this->pos;
        $this->lineStartOffset = $this->lineNumber;
        $this->prevTokenLineStartOffset = $this->tokenLineStartOffset;

        if ($this->pos >= $this->len) {
            // at the end
            $this->tokenOffset = $this->len;
            return $this->token = SyntaxKind::EOF;
        }

        $code = StringHelper::charCodeAt($this->text, $this->pos);

        // trivia: whitespace
        if ($this->isWhiteSpace($code)) {
            do {
                $this->pos++;
                $this->value .= StringHelper::fromCharCode($code);
                $code = StringHelper::charCodeAt($this->text, $this->pos);
            } while ($this->isWhiteSpace($code));

            return $this->token = SyntaxKind::Trivia;
        }

        // trivia: newlines
        if ($this->isLineBreak($code)) {
            $this->pos++;
            $this->value .= StringHelper::fromCharCode($code);
            if ($code === CC::CARRIAGE_RETURN && StringHelper::charCodeAt($this->text, $this->pos) === CC::LINE_FEED) {
                $this->pos++;
                $this->value .= "\n";
            }
            $this->lineNumber++;
            $this->tokenLineStartOffset = $this->pos;
            return $this->token = SyntaxKind::LineBreakTrivia;
        }

        switch ($code) {
            // tokens: []{}:,
            case CC::OPEN_BRACE:
                $this->pos++;
                return $this->token = SyntaxKind::OpenBraceToken;
            case CC::CLOSE_BRACE:
                $this->pos++;
                return $this->token = SyntaxKind::CloseBraceToken;
            case CC::OPEN_BRACKET:
                $this->pos++;
                return $this->token = SyntaxKind::OpenBracketToken;
            case CC::CLOSE_BRACKET:
                $this->pos++;
                return $this->token = SyntaxKind::CloseBracketToken;
            case CC::COLON:
                $this->pos++;
                return $this->token = SyntaxKind::ColonToken;
            case CC::COMMA:
                $this->pos++;
                return $this->token = SyntaxKind::CommaToken;

                // strings
            case CC::DOUBLE_QUOTE:
                $this->pos++;
                $this->value = $this->scanString();
                return $this->token = SyntaxKind::StringLiteral;

                // comments
            case CC::SLASH:
                $start = $this->pos;
                // Single-line comment
                if (StringHelper::charCodeAt($this->text, $this->pos + 1) === CC::SLASH) {
                    $this->pos += 2;

                    while ($this->pos < $this->len) {
                        if ($this->isLineBreak(StringHelper::charCodeAt($this->text, $this->pos))) {
                            break;
                        }
                        $this->pos++;
                    }

                    $this->value = StringHelper::substring($this->text, $start, $this->pos);
                    return $this->token = SyntaxKind::LineCommentTrivia;
                }

                // Multi-line comment
                if (StringHelper::charCodeAt($this->text, $this->pos + 1) === CC::ASTERISK) {
                    $this->pos += 2;

                    $safeLength = $this->len - 1; // For lookahead
                    $commentClosed = false;

                    while ($this->pos < $safeLength) {
                        $ch = StringHelper::charCodeAt($this->text, $this->pos);

                        if ($ch === CC::ASTERISK && StringHelper::charCodeAt($this->text, $this->pos + 1) === CC::SLASH) {
                            $this->pos += 2;
                            $commentClosed = true;
                            break;
                        }

                        $this->pos++;

                        if ($this->isLineBreak($ch)) {
                            if ($ch === CC::CARRIAGE_RETURN && StringHelper::charCodeAt($this->text, $this->pos) === CC::LINE_FEED) {
                                $this->pos++;
                            }
                            $this->lineNumber++;
                            $this->tokenLineStartOffset = $this->pos;
                        }
                    }

                    if (!$commentClosed) {
                        $this->pos++;
                        $this->scanError = ScanError::UnexpectedEndOfComment;
                    }

                    $this->value = StringHelper::substring($this->text, $start, $this->pos);
                    return $this->token = SyntaxKind::BlockCommentTrivia;
                }

                // just a single slash
                $this->value .= StringHelper::fromCharCode($code);
                $this->pos++;
                return $this->token = SyntaxKind::Unknown;

                // numbers
            case CC::MINUS:
                $this->value .= StringHelper::fromCharCode($code);
                $this->pos++;
                if ($this->pos === $this->len || !$this->isDigit(StringHelper::charCodeAt($this->text, $this->pos))) {
                    return $this->token = SyntaxKind::Unknown;
                }
                // found a minus, followed by a number so
                // we fall through to proceed with scanning numbers
                // no break - intentional fall-through
            case CC::DIGIT_0:
            case CC::DIGIT_1:
            case CC::DIGIT_2:
            case CC::DIGIT_3:
            case CC::DIGIT_4:
            case CC::DIGIT_5:
            case CC::DIGIT_6:
            case CC::DIGIT_7:
            case CC::DIGIT_8:
            case CC::DIGIT_9:
                $this->value .= $this->scanNumber();
                return $this->token = SyntaxKind::NumericLiteral;

                // literals and unknown symbols
            default:
                // is a literal? Read the full word.
                while ($this->pos < $this->len && $this->isUnknownContentCharacter($code)) {
                    $this->pos++;
                    $code = StringHelper::charCodeAt($this->text, $this->pos);
                }

                if ($this->tokenOffset !== $this->pos) {
                    $this->value = StringHelper::substring($this->text, $this->tokenOffset, $this->pos);
                    // keywords: true, false, null
                    switch ($this->value) {
                        case 'true':
                            return $this->token = SyntaxKind::TrueKeyword;
                        case 'false':
                            return $this->token = SyntaxKind::FalseKeyword;
                        case 'null':
                            return $this->token = SyntaxKind::NullKeyword;
                    }
                    return $this->token = SyntaxKind::Unknown;
                }

                // some
                $this->value .= StringHelper::fromCharCode($code);
                $this->pos++;
                return $this->token = SyntaxKind::Unknown;
        }
    }

    private function isUnknownContentCharacter(int $code): bool
    {
        if ($this->isWhiteSpace($code) || $this->isLineBreak($code)) {
            return false;
        }

        switch ($code) {
            case CC::CLOSE_BRACE:
            case CC::CLOSE_BRACKET:
            case CC::OPEN_BRACE:
            case CC::OPEN_BRACKET:
            case CC::DOUBLE_QUOTE:
            case CC::COLON:
            case CC::COMMA:
            case CC::SLASH:
                return false;
        }

        return true;
    }

    private function scanNextNonTrivia(): SyntaxKind
    {
        do {
            $result = $this->scanNext();
        } while ($result->value >= SyntaxKind::LineCommentTrivia->value && $result->value <= SyntaxKind::Trivia->value);

        return $result;
    }

    private function isWhiteSpace(int $ch): bool
    {
        return $ch === CC::SPACE || $ch === CC::TAB;
    }

    private function isLineBreak(int $ch): bool
    {
        return $ch === CC::LINE_FEED || $ch === CC::CARRIAGE_RETURN;
    }

    private function isDigit(int $ch): bool
    {
        return $ch >= CC::DIGIT_0 && $ch <= CC::DIGIT_9;
    }
}
