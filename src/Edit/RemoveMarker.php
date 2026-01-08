<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Edit;

/**
 * Marker class to indicate value removal in modify() operations
 *
 * Use this instead of null when you want to delete a property/element,
 * since null is a valid JSON value.
 *
 * Example:
 * ```php
 * // Set to JSON null
 * Editor::modify($json, ['prop'], null, $options);
 *
 * // Delete the property
 * Editor::modify($json, ['prop'], new RemoveMarker(), $options);
 * ```
 */
final class RemoveMarker
{
    private function __construct()
    {
        // Private constructor - use via Editor::REMOVE constant
    }

    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
