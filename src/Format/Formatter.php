<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Format;

use Kestrel\JsoncParser\Edit\Edit;
use Kestrel\JsoncParser\Edit\Range;
use Kestrel\JsoncParser\Scanner\Scanner;
use Kestrel\JsoncParser\Scanner\ScanError;
use Kestrel\JsoncParser\Scanner\SyntaxKind;
use Kestrel\JsoncParser\Util\StringHelper;

/**
 * JSON/JSONC Formatter
 * Formats JSON with configurable indentation, line breaks, and comment handling
 */
final class Formatter
{
    /**
     * Format JSONC text
     *
     * @param string $documentText The text to format
     * @param Range|null $range Optional range to format (null = entire document)
     * @param FormattingOptions $options Formatting options
     * @return array<Edit> Array of edit operations to apply
     */
    public static function format(string $documentText, ?Range $range, FormattingOptions $options): array
    {
        $initialIndentLevel = 0;
        $formatTextStart = 0;
        $rangeStart = 0;
        $rangeEnd = StringHelper::length($documentText);

        if ($range !== null) {
            $rangeStart = $range->offset;
            $rangeEnd = $rangeStart + $range->length;

            // Extend to full lines
            $formatTextStart = $rangeStart;
            while ($formatTextStart > 0 && !self::isEOL($documentText, $formatTextStart - 1)) {
                $formatTextStart--;
            }

            $endOffset = $rangeEnd;
            while ($endOffset < StringHelper::length($documentText) && !self::isEOL($documentText, $endOffset)) {
                $endOffset++;
            }

            $formatText = StringHelper::substring($documentText, $formatTextStart, $endOffset);
            $initialIndentLevel = self::computeIndentLevel($formatText, $options);
        } else {
            $formatText = $documentText;
        }

        $eol = self::getEOL($options, $documentText);
        $eolFastPathSupported = in_array($eol, ["\n", "\r", "\r\n"], true);

        $numberLineBreaks = 0;
        $indentLevel = 0;

        if ($options->insertSpaces) {
            $indentValue = StringIntern::getSpaces($options->tabSize);
        } else {
            $indentValue = "\t";
        }

        $indentType = $indentValue === "\t" ? "\t" : ' ';

        $scanner = Scanner::create($formatText, false);
        $hasError = false;

        $newLinesAndIndent = function () use (&$numberLineBreaks, $eol, $indentValue, $initialIndentLevel, &$indentLevel, $eolFastPathSupported, $indentType): string {
            /** @phpstan-ignore greater.alwaysFalse (captured by reference, modified in scanNext) */
            if ($numberLineBreaks > 1) {
                return str_repeat($eol, $numberLineBreaks) . str_repeat($indentValue, $initialIndentLevel + $indentLevel);
            }

            $amountOfSpaces = strlen($indentValue) * ($initialIndentLevel + $indentLevel);

            if (!$eolFastPathSupported || $amountOfSpaces >= 200) {
                return $eol . str_repeat($indentValue, $initialIndentLevel + $indentLevel);
            }

            if ($amountOfSpaces <= 0) {
                return $eol;
            }

            return StringIntern::getEol($eol, $indentType, $amountOfSpaces);
        };

        $scanNext = function () use ($scanner, &$numberLineBreaks, &$hasError, $options): SyntaxKind {
            $token = $scanner->scan();
            $numberLineBreaks = 0;

            while ($token === SyntaxKind::Trivia || $token === SyntaxKind::LineBreakTrivia) {
                if ($token === SyntaxKind::LineBreakTrivia && $options->keepLines) {
                    $numberLineBreaks += 1;
                } elseif ($token === SyntaxKind::LineBreakTrivia) {
                    $numberLineBreaks = 1;
                }
                $token = $scanner->scan();
            }

            $hasError = $token === SyntaxKind::Unknown || $scanner->getTokenError() !== ScanError::None;
            return $token;
        };

        $editOperations = [];

        $addEdit = function (string $text, int $startOffset, int $endOffset) use (&$hasError, $range, $rangeEnd, $rangeStart, $documentText, &$editOperations): void {
            if (!$hasError &&
                ($range === null || ($startOffset < $rangeEnd && $endOffset > $rangeStart)) &&
                StringHelper::substring($documentText, $startOffset, $endOffset) !== $text) {
                $editOperations[] = new Edit($startOffset, $endOffset - $startOffset, $text);
            }
        };

        $firstToken = $scanNext();

        if ($options->keepLines && $numberLineBreaks > 0) {
            $addEdit(str_repeat($eol, $numberLineBreaks), 0, 0);
        }

        if ($firstToken !== SyntaxKind::EOF) {
            $firstTokenStart = $scanner->getTokenOffset() + $formatTextStart;
            $initialIndent = (strlen($indentValue) * $initialIndentLevel < 20) && $options->insertSpaces
                ? StringIntern::getSpaces(strlen($indentValue) * $initialIndentLevel)
                : str_repeat($indentValue, $initialIndentLevel);
            $addEdit($initialIndent, $formatTextStart, $firstTokenStart);
        }

        while ($firstToken !== SyntaxKind::EOF) {
            $firstTokenEnd = $scanner->getTokenOffset() + $scanner->getTokenLength() + $formatTextStart;
            $secondToken = $scanNext();
            $replaceContent = '';
            $needsLineBreak = false;

            // Handle comments between tokens
            while ($numberLineBreaks === 0 && ($secondToken === SyntaxKind::LineCommentTrivia || $secondToken === SyntaxKind::BlockCommentTrivia)) {
                $commentTokenStart = $scanner->getTokenOffset() + $formatTextStart;
                $addEdit(' ', $firstTokenEnd, $commentTokenStart);
                $firstTokenEnd = $scanner->getTokenOffset() + $scanner->getTokenLength() + $formatTextStart;
                $needsLineBreak = $secondToken === SyntaxKind::LineCommentTrivia;
                $replaceContent = $needsLineBreak ? $newLinesAndIndent() : '';
                $secondToken = $scanNext();
            }

            if ($secondToken === SyntaxKind::CloseBraceToken) {
                if ($firstToken !== SyntaxKind::OpenBraceToken) {
                    $indentLevel--;
                }

                if (($options->keepLines && $numberLineBreaks > 0) || (!$options->keepLines && $firstToken !== SyntaxKind::OpenBraceToken)) {
                    $replaceContent = $newLinesAndIndent();
                } elseif ($options->keepLines) {
                    $replaceContent = ' ';
                }
            } elseif ($secondToken === SyntaxKind::CloseBracketToken) {
                if ($firstToken !== SyntaxKind::OpenBracketToken) {
                    $indentLevel--;
                }

                if (($options->keepLines && $numberLineBreaks > 0) || (!$options->keepLines && $firstToken !== SyntaxKind::OpenBracketToken)) {
                    $replaceContent = $newLinesAndIndent();
                } elseif ($options->keepLines) {
                    $replaceContent = ' ';
                }
            } else {
                switch ($firstToken) {
                    case SyntaxKind::OpenBracketToken:
                    case SyntaxKind::OpenBraceToken:
                        $indentLevel++;
                        if (($options->keepLines && $numberLineBreaks > 0) || !$options->keepLines) {
                            $replaceContent = $newLinesAndIndent();
                        } else {
                            $replaceContent = ' ';
                        }
                        break;

                    case SyntaxKind::CommaToken:
                        if (($options->keepLines && $numberLineBreaks > 0) || !$options->keepLines) {
                            $replaceContent = $newLinesAndIndent();
                        } else {
                            $replaceContent = ' ';
                        }
                        break;

                    case SyntaxKind::LineCommentTrivia:
                        $replaceContent = $newLinesAndIndent();
                        break;

                    case SyntaxKind::BlockCommentTrivia:
                        if ($numberLineBreaks > 0) {
                            $replaceContent = $newLinesAndIndent();
                        } elseif (!$needsLineBreak) {
                            $replaceContent = ' ';
                        }
                        break;

                    case SyntaxKind::ColonToken:
                        if ($options->keepLines && $numberLineBreaks > 0) {
                            $replaceContent = $newLinesAndIndent();
                        } elseif (!$needsLineBreak) {
                            $replaceContent = ' ';
                        }
                        break;

                    case SyntaxKind::StringLiteral:
                        if ($options->keepLines && $numberLineBreaks > 0) {
                            $replaceContent = $newLinesAndIndent();
                        } elseif ($secondToken === SyntaxKind::ColonToken && !$needsLineBreak) {
                            $replaceContent = '';
                        }
                        break;

                    case SyntaxKind::NullKeyword:
                    case SyntaxKind::TrueKeyword:
                    case SyntaxKind::FalseKeyword:
                    case SyntaxKind::NumericLiteral:
                    case SyntaxKind::CloseBraceToken:
                    case SyntaxKind::CloseBracketToken:
                        if ($options->keepLines && $numberLineBreaks > 0) {
                            $replaceContent = $newLinesAndIndent();
                        } else {
                            if (($secondToken === SyntaxKind::LineCommentTrivia || $secondToken === SyntaxKind::BlockCommentTrivia) && !$needsLineBreak) {
                                $replaceContent = ' ';
                            } elseif ($secondToken !== SyntaxKind::CommaToken && $secondToken !== SyntaxKind::EOF) {
                                $hasError = true;
                            }
                        }
                        break;

                    case SyntaxKind::Unknown:
                        $hasError = true;
                        break;
                }

                if ($numberLineBreaks > 0 && ($secondToken === SyntaxKind::LineCommentTrivia || $secondToken === SyntaxKind::BlockCommentTrivia)) {
                    $replaceContent = $newLinesAndIndent();
                }
            }

            if ($secondToken === SyntaxKind::EOF) {
                if ($options->keepLines && $numberLineBreaks > 0) {
                    $replaceContent = $newLinesAndIndent();
                } else {
                    $replaceContent = $options->insertFinalNewline ? $eol : '';
                }
            }

            $secondTokenStart = $scanner->getTokenOffset() + $formatTextStart;
            $addEdit($replaceContent, $firstTokenEnd, $secondTokenStart);
            $firstToken = $secondToken;
        }

        return $editOperations;
    }

    /**
     * Check if character at offset is an EOL character
     */
    private static function isEOL(string $text, int $offset): bool
    {
        if ($offset < 0 || $offset >= StringHelper::length($text)) {
            return false;
        }
        $ch = StringHelper::charAt($text, $offset);
        return $ch === "\r" || $ch === "\n";
    }

    /**
     * Compute the indentation level of content
     */
    private static function computeIndentLevel(string $content, FormattingOptions $options): int
    {
        $i = 0;
        $nChars = 0;
        $tabSize = $options->tabSize;
        $len = StringHelper::length($content);

        while ($i < $len) {
            $ch = StringHelper::charAt($content, $i);
            if ($ch === ' ') {
                $nChars++;
            } elseif ($ch === "\t") {
                $nChars += $tabSize;
            } else {
                break;
            }
            $i++;
        }

        return (int)floor($nChars / $tabSize);
    }

    /**
     * Get the EOL character(s) used in the document
     */
    private static function getEOL(FormattingOptions $options, string $text): string
    {
        $len = StringHelper::length($text);
        for ($i = 0; $i < $len; $i++) {
            $ch = StringHelper::charAt($text, $i);
            if ($ch === "\r") {
                if ($i + 1 < $len && StringHelper::charAt($text, $i + 1) === "\n") {
                    return "\r\n";
                }
                return "\r";
            } elseif ($ch === "\n") {
                return "\n";
            }
        }

        return $options->eol ?? "\n";
    }
}
