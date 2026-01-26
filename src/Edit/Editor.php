<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Edit;

use Kestrel\JsoncParser\Parser\Node;
use Kestrel\JsoncParser\Parser\NodeType;
use Kestrel\JsoncParser\Parser\Parser;
use Kestrel\JsoncParser\Format\Formatter;
use Kestrel\JsoncParser\Util\StringHelper;

/**
 * JSON/JSONC Editor
 * Provides modification operations (insert, update, delete) for JSON documents
 */
final class Editor
{
    /**
     * Marker constant for removing properties/values
     * Use this instead of null when you want to delete, since null is a valid JSON value
     */
    public const REMOVE = null; // Will be set in static constructor

    private static ?RemoveMarker $removeMarker = null;

    /**
     * Get the remove marker instance
     */
    private static function getRemoveMarker(): RemoveMarker
    {
        if (self::$removeMarker === null) {
            self::$removeMarker = RemoveMarker::instance();
        }
        return self::$removeMarker;
    }

    /**
     * Remove a property at the given path
     *
     * @param string $text The JSON text
     * @param array<int|string> $path The path to the property
     * @param ModificationOptions $options Modification options
     * @return array<Edit> Array of edit operations
     */
    public static function removeProperty(string $text, array $path, ModificationOptions $options): array
    {
        return self::modify($text, $path, self::getRemoveMarker(), $options);
    }

    /**
     * Modify JSON text by setting a value at the given path
     *
     * @param string $text The JSON text to modify
     * @param array<int|string> $originalPath The path to the property (array of string keys or numeric indices)
     * @param mixed $value The value to set (use RemoveMarker for deletion, or use removeProperty())
     * @param ModificationOptions $options Modification options
     * @return array<Edit> Array of edit operations to apply
     */
    public static function modify(string $text, array $originalPath, mixed $value, ModificationOptions $options): array
    {
        $path = $originalPath; // Copy for modification
        $errors = [];
        $root = Parser::parseTree($text, $errors);
        $parent = null;

        $isDelete = $value instanceof RemoveMarker;

        $lastSegment = null;
        while (count($path) > 0) {
            $lastSegment = array_pop($path);
            $parent = Parser::findNodeAtLocation($root, $path);
            if ($parent === null && !$isDelete) {
                if (is_string($lastSegment)) {
                    $value = [$lastSegment => $value];
                } else {
                    $value = [$value];
                }
            } else {
                break;
            }
        }

        if ($parent === null) {
            // Empty document
            if ($isDelete) {
                throw new \Exception('Cannot delete in empty document');
            }
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                throw new \Exception('Failed to encode value as JSON');
            }
            return self::withFormatting($text, new Edit(
                $root !== null ? $root->offset : 0,
                $root !== null ? $root->length : 0,
                $encoded
            ), $options);
        } elseif ($parent->type === NodeType::Object && is_string($lastSegment) && is_array($parent->children)) {
            $existing = Parser::findNodeAtLocation($parent, [$lastSegment]);
            if ($existing !== null) {
                if ($isDelete) {
                    // Delete
                    if ($existing->parent === null) {
                        throw new \Exception('Malformed AST');
                    }
                    $searchResult = array_search($existing->parent, $parent->children, true);
                    if ($searchResult === false) {
                        throw new \Exception('Malformed AST: property not found in parent');
                    }
                    $propertyIndex = (int) $searchResult;
                    $removeBegin = 0;
                    $removeEnd = $existing->parent->offset + $existing->parent->length;

                    if ($propertyIndex > 0) {
                        // Remove the comma of the previous node
                        $previous = $parent->children[$propertyIndex - 1];
                        $removeBegin = $previous->offset + $previous->length;
                    } else {
                        $removeBegin = $parent->offset + 1;
                        if (count($parent->children) > 1) {
                            // Remove the comma of the next node
                            $next = $parent->children[1];
                            $removeEnd = $next->offset;
                        }
                    }
                    return self::withFormatting($text, new Edit($removeBegin, $removeEnd - $removeBegin, ''), $options);
                } else {
                    // Set value of existing property
                    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    if ($encoded === false) {
                        throw new \Exception('Failed to encode value as JSON');
                    }
                    return self::withFormatting($text, new Edit(
                        $existing->offset,
                        $existing->length,
                        $encoded
                    ), $options);
                }
            } else {
                if ($isDelete) {
                    // Delete: property does not exist, nothing to do
                    return [];
                }
                $encodedKey = json_encode($lastSegment, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $encodedValue = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($encodedKey === false || $encodedValue === false) {
                    throw new \Exception('Failed to encode value as JSON');
                }
                $newProperty = $encodedKey . ': ' . $encodedValue;
                /** @var array<string> $propertyNames */
                $propertyNames = array_map(fn ($p) => $p->children[0]->value ?? '', $parent->children);
                $index = $options->getInsertionIndex !== null
                    ? ($options->getInsertionIndex)($propertyNames)
                    : count($parent->children);

                $edit = null;
                if ($index > 0) {
                    $previous = $parent->children[$index - 1];
                    $edit = new Edit($previous->offset + $previous->length, 0, ',' . $newProperty);
                } elseif (count($parent->children) === 0) {
                    $edit = new Edit($parent->offset + 1, 0, $newProperty);
                } else {
                    $edit = new Edit($parent->offset + 1, 0, $newProperty . ',');
                }
                return self::withFormatting($text, $edit, $options);
            }
        } elseif ($parent->type === NodeType::Array && is_int($lastSegment) && is_array($parent->children)) {
            $insertIndex = $lastSegment;
            if ($insertIndex === -1) {
                // Insert
                $newProperty = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($newProperty === false) {
                    throw new \Exception('Failed to encode value as JSON');
                }
                $edit = null;
                if (count($parent->children) === 0) {
                    $edit = new Edit($parent->offset + 1, 0, $newProperty);
                } else {
                    $previous = $parent->children[count($parent->children) - 1];
                    $edit = new Edit($previous->offset + $previous->length, 0, ',' . $newProperty);
                }
                return self::withFormatting($text, $edit, $options);
            } elseif ($isDelete && count($parent->children) > 0) {
                // Removal
                $removalIndex = $lastSegment;
                $toRemove = $parent->children[$removalIndex];
                $edit = null;
                if (count($parent->children) === 1) {
                    // Only item
                    $edit = new Edit($parent->offset + 1, $parent->length - 2, '');
                } elseif (count($parent->children) - 1 === $removalIndex) {
                    // Last item
                    $previous = $parent->children[$removalIndex - 1];
                    $offset = $previous->offset + $previous->length;
                    $parentEndOffset = $parent->offset + $parent->length;
                    $edit = new Edit($offset, $parentEndOffset - 2 - $offset, '');
                } else {
                    $edit = new Edit($toRemove->offset, $parent->children[$removalIndex + 1]->offset - $toRemove->offset, '');
                }
                return self::withFormatting($text, $edit, $options);
            } elseif (!$isDelete) {
                $newProperty = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($newProperty === false) {
                    throw new \Exception('Failed to encode value as JSON');
                }

                $edit = null;
                if (!$options->isArrayInsertion && count($parent->children) > $lastSegment) {
                    $toModify = $parent->children[$lastSegment];
                    $edit = new Edit($toModify->offset, $toModify->length, $newProperty);
                } elseif (count($parent->children) === 0 || $lastSegment === 0) {
                    $edit = new Edit(
                        $parent->offset + 1,
                        0,
                        count($parent->children) === 0 ? $newProperty : $newProperty . ','
                    );
                } else {
                    $index = $lastSegment > count($parent->children) ? count($parent->children) : $lastSegment;
                    $previous = $parent->children[$index - 1];
                    $edit = new Edit($previous->offset + $previous->length, 0, ',' . $newProperty);
                }

                return self::withFormatting($text, $edit, $options);
            } else {
                throw new \Exception("Cannot remove array index {$insertIndex} as length is not sufficient");
            }
        } else {
            $segmentType = is_int($lastSegment) ? 'property' : 'index';
            throw new \Exception("Cannot add {$segmentType} to parent of type {$parent->type->value}");
        }
    }

    /**
     * Apply formatting to an edit
     *
     * @param string $text The original text
     * @param Edit $edit The edit to apply
     * @param ModificationOptions $options Modification options
     * @return array<Edit> Array of edit operations with formatting applied
     */
    private static function withFormatting(string $text, Edit $edit, ModificationOptions $options): array
    {
        if ($options->formattingOptions === null) {
            return [$edit];
        }

        // Apply the edit
        $newText = self::applyEdit($text, $edit);

        // Format the new text - use character-based lengths for UTF-8 safety
        $begin = $edit->offset;
        $end = $edit->offset + StringHelper::length($edit->content);
        if ($edit->length === 0 || StringHelper::length($edit->content) === 0) {
            // Insert or remove - extend to full lines
            while ($begin > 0 && !self::isEOL($newText, $begin - 1)) {
                $begin--;
            }
            while ($end < StringHelper::length($newText) && !self::isEOL($newText, $end)) {
                $end++;
            }
        }

        $edits = Formatter::format($newText, new Range($begin, $end - $begin), $options->formattingOptions);

        // Apply the formatting edits and track the begin and end offsets of the changes
        for ($i = count($edits) - 1; $i >= 0; $i--) {
            $formatEdit = $edits[$i];
            $newText = self::applyEdit($newText, $formatEdit);
            $begin = min($begin, $formatEdit->offset);
            $end = max($end, $formatEdit->offset + $formatEdit->length);
            $end += StringHelper::length($formatEdit->content) - $formatEdit->length;
        }

        // Create a single edit with all changes - use character-based operations
        $editLength = StringHelper::length($text) - (StringHelper::length($newText) - $end) - $begin;
        return [new Edit($begin, $editLength, StringHelper::substring($newText, $begin, $end))];
    }

    /**
     * Apply a single edit to text
     *
     * @param string $text The text to modify
     * @param Edit $edit The edit to apply
     * @return string The modified text
     */
    public static function applyEdit(string $text, Edit $edit): string
    {
        // Use StringHelper for UTF-8 safe substring operations since offsets are character-based
        return StringHelper::substring($text, 0, $edit->offset) . $edit->content . StringHelper::substring($text, $edit->offset + $edit->length);
    }

    /**
     * Apply multiple edits to text
     *
     * @param string $text The text to modify
     * @param array<Edit> $edits Array of edits to apply (must be non-overlapping and sorted by offset)
     * @return string The modified text
     */
    public static function applyEdits(string $text, array $edits): string
    {
        // Sort edits by offset in descending order to apply from end to beginning
        usort($edits, fn ($a, $b) => $b->offset <=> $a->offset);

        foreach ($edits as $edit) {
            $text = self::applyEdit($text, $edit);
        }

        return $text;
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
}
