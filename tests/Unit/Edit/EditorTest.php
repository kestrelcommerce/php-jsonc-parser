<?php

declare(strict_types=1);

use Kestrel\JsoncParser\Edit\Edit;
use Kestrel\JsoncParser\Edit\Editor;
use Kestrel\JsoncParser\Edit\ModificationOptions;
use Kestrel\JsoncParser\Edit\RemoveMarker;
use Kestrel\JsoncParser\Format\FormattingOptions;

/**
 * Assert modification edits helper function
 * Verifies edits are valid and applies them
 *
 * @param array<Edit> $edits
 */
function assertModifyEdits(string $content, array $edits, string $expected): void
{
    expect($edits)->not->toBeNull();

    $lastEditOffset = strlen($content);
    for ($i = count($edits) - 1; $i >= 0; $i--) {
        $edit = $edits[$i];
        expect($edit->offset)->toBeGreaterThanOrEqual(0);
        expect($edit->length)->toBeGreaterThanOrEqual(0);
        expect($edit->offset + $edit->length)->toBeLessThanOrEqual(strlen($content));
        expect(is_string($edit->content))->toBeTrue();
        expect($lastEditOffset)->toBeGreaterThanOrEqual($edit->offset + $edit->length); // Ensure edits are ordered
        $lastEditOffset = $edit->offset;
        $content = substr($content, 0, $edit->offset) . $edit->content . substr($content, $edit->offset + $edit->length);
    }

    expect($content)->toBe($expected);
}

/**
 * Get default formatting options for tests
 */
function getFormattingOptions(): FormattingOptions
{
    return new FormattingOptions(
        insertSpaces: true,
        tabSize: 2,
        eol: "\n",
        keepLines: false
    );
}

/**
 * Get default modification options for tests
 */
function getModOptions(): ModificationOptions
{
    return new ModificationOptions(
        formattingOptions: getFormattingOptions()
    );
}

describe('Editor', function () {
    test('set property', function () {
        $modOptions = getModOptions();

        $content = "{\n  \"x\": \"y\"\n}";
        $edits = Editor::modify($content, ['x'], 'bar', $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"x\": \"bar\"\n}");

        $content = 'true';
        $edits = Editor::modify($content, [], 'bar', $modOptions);
        assertModifyEdits($content, $edits, '"bar"');

        $content = "{\n  \"x\": \"y\"\n}";
        $edits = Editor::modify($content, ['x'], ['key' => true], $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"x\": {\n    \"key\": true\n  }\n}");

        $content = "{\n  \"a\": \"b\",  \"x\": \"y\"\n}";
        $edits = Editor::modify($content, ['a'], null, $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"a\": null,  \"x\": \"y\"\n}");
    });

    test('insert property', function () {
        $modOptions = getModOptions();
        $formattingOptions = getFormattingOptions();

        $content = '{}';
        $edits = Editor::modify($content, ['foo'], 'bar', $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"foo\": \"bar\"\n}");

        $edits = Editor::modify($content, ['foo', 'foo2'], 'bar', $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"foo\": {\n    \"foo2\": \"bar\"\n  }\n}");

        $content = "{\n}";
        $edits = Editor::modify($content, ['foo'], 'bar', $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"foo\": \"bar\"\n}");

        $content = "  {\n  }";
        $edits = Editor::modify($content, ['foo'], 'bar', $modOptions);
        assertModifyEdits($content, $edits, "  {\n    \"foo\": \"bar\"\n  }");

        $content = "{\n  \"x\": \"y\"\n}";
        $edits = Editor::modify($content, ['foo'], 'bar', $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"x\": \"y\",\n  \"foo\": \"bar\"\n}");

        $content = "{\n  \"x\": \"y\"\n}";
        $edits = Editor::modify($content, ['e'], 'null', $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"x\": \"y\",\n  \"e\": \"null\"\n}");

        $edits = Editor::modify($content, ['x'], 'bar', $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"x\": \"bar\"\n}");

        $content = "{\n  \"x\": {\n    \"a\": 1,\n    \"b\": true\n  }\n}\n";
        $edits = Editor::modify($content, ['x'], 'bar', $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"x\": \"bar\"\n}\n");

        $edits = Editor::modify($content, ['x', 'b'], 'bar', $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"x\": {\n    \"a\": 1,\n    \"b\": \"bar\"\n  }\n}\n");

        $edits = Editor::modify($content, ['x', 'c'], 'bar', new ModificationOptions(
            formattingOptions: $formattingOptions,
            getInsertionIndex: fn () => 0
        ));
        assertModifyEdits($content, $edits, "{\n  \"x\": {\n    \"c\": \"bar\",\n    \"a\": 1,\n    \"b\": true\n  }\n}\n");

        $edits = Editor::modify($content, ['x', 'c'], 'bar', new ModificationOptions(
            formattingOptions: $formattingOptions,
            getInsertionIndex: fn () => 1
        ));
        assertModifyEdits($content, $edits, "{\n  \"x\": {\n    \"a\": 1,\n    \"c\": \"bar\",\n    \"b\": true\n  }\n}\n");

        $edits = Editor::modify($content, ['x', 'c'], 'bar', new ModificationOptions(
            formattingOptions: $formattingOptions,
            getInsertionIndex: fn () => 2
        ));
        assertModifyEdits($content, $edits, "{\n  \"x\": {\n    \"a\": 1,\n    \"b\": true,\n    \"c\": \"bar\"\n  }\n}\n");

        $edits = Editor::modify($content, ['c'], 'bar', $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"x\": {\n    \"a\": 1,\n    \"b\": true\n  },\n  \"c\": \"bar\"\n}\n");

        $content = "{\n  \"a\": [\n    {\n    } \n  ]  \n}";
        $edits = Editor::modify($content, ['foo'], 'bar', $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"a\": [\n    {\n    } \n  ],\n  \"foo\": \"bar\"\n}");

        $content = '';
        $edits = Editor::modify($content, ['foo', 0], 'bar', $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"foo\": [\n    \"bar\"\n  ]\n}");

        $content = '//comment';
        $edits = Editor::modify($content, ['foo', 0], 'bar', $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"foo\": [\n    \"bar\"\n  ]\n} //comment");
    });

    test('remove property', function () {
        $modOptions = getModOptions();

        $content = "{\n  \"x\": \"y\"\n}";
        $edits = Editor::modify($content, ['x'], RemoveMarker::instance(), $modOptions);
        assertModifyEdits($content, $edits, "{\n}");

        $content = "{\n  \"x\": \"y\", \"a\": []\n}";
        $edits = Editor::modify($content, ['x'], RemoveMarker::instance(), $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"a\": []\n}");

        $content = "{\n  \"x\": \"y\", \"a\": []\n}";
        $edits = Editor::modify($content, ['a'], RemoveMarker::instance(), $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"x\": \"y\"\n}");
    });

    test('set item', function () {
        $modOptions = getModOptions();
        $content = "{\n  \"x\": [1, 2, 3],\n  \"y\": 0\n}";

        $edits = Editor::modify($content, ['x', 0], 6, $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"x\": [6, 2, 3],\n  \"y\": 0\n}");

        $edits = Editor::modify($content, ['x', 1], 5, $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"x\": [1, 5, 3],\n  \"y\": 0\n}");

        $edits = Editor::modify($content, ['x', 2], 4, $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"x\": [1, 2, 4],\n  \"y\": 0\n}");

        $edits = Editor::modify($content, ['x', 3], 3, $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"x\": [\n    1,\n    2,\n    3,\n    3\n  ],\n  \"y\": 0\n}");
    });

    test('insert item at 0; isArrayInsertion = true', function () {
        $formattingOptions = getFormattingOptions();
        $content = "[\n  2,\n  3\n]";
        $edits = Editor::modify($content, [0], 1, new ModificationOptions(
            formattingOptions: $formattingOptions,
            isArrayInsertion: true
        ));
        assertModifyEdits($content, $edits, "[\n  1,\n  2,\n  3\n]");
    });

    test('insert item at 0 in empty array', function () {
        $modOptions = getModOptions();
        $content = "[\n]";
        $edits = Editor::modify($content, [0], 1, $modOptions);
        assertModifyEdits($content, $edits, "[\n  1\n]");
    });

    test('insert item at an index; isArrayInsertion = true', function () {
        $formattingOptions = getFormattingOptions();
        $content = "[\n  1,\n  3\n]";
        $edits = Editor::modify($content, [1], 2, new ModificationOptions(
            formattingOptions: $formattingOptions,
            isArrayInsertion: true
        ));
        assertModifyEdits($content, $edits, "[\n  1,\n  2,\n  3\n]");
    });

    test('insert item at an index in empty array', function () {
        $modOptions = getModOptions();
        $content = "[\n]";
        $edits = Editor::modify($content, [1], 1, $modOptions);
        assertModifyEdits($content, $edits, "[\n  1\n]");
    });

    test('insert item at end index', function () {
        $modOptions = getModOptions();
        $content = "[\n  1,\n  2\n]";
        $edits = Editor::modify($content, [2], 3, $modOptions);
        assertModifyEdits($content, $edits, "[\n  1,\n  2,\n  3\n]");
    });

    test('insert item at end to empty array', function () {
        $modOptions = getModOptions();
        $content = "[\n]";
        $edits = Editor::modify($content, [-1], 'bar', $modOptions);
        assertModifyEdits($content, $edits, "[\n  \"bar\"\n]");
    });

    test('insert item at end', function () {
        $modOptions = getModOptions();
        $content = "[\n  1,\n  2\n]";
        $edits = Editor::modify($content, [-1], 'bar', $modOptions);
        assertModifyEdits($content, $edits, "[\n  1,\n  2,\n  \"bar\"\n]");
    });

    test('remove item in array with one item', function () {
        $modOptions = getModOptions();
        $content = "[\n  1\n]";
        $edits = Editor::modify($content, [0], RemoveMarker::instance(), $modOptions);
        assertModifyEdits($content, $edits, '[]');
    });

    test('remove item in the middle of the array', function () {
        $modOptions = getModOptions();
        $content = "[\n  1,\n  2,\n  3\n]";
        $edits = Editor::modify($content, [1], RemoveMarker::instance(), $modOptions);
        assertModifyEdits($content, $edits, "[\n  1,\n  3\n]");
    });

    test('remove last item in the array', function () {
        $modOptions = getModOptions();
        $content = "[\n  1,\n  2,\n  \"bar\"\n]";
        $edits = Editor::modify($content, [2], RemoveMarker::instance(), $modOptions);
        assertModifyEdits($content, $edits, "[\n  1,\n  2\n]");
    });

    test('remove last item in the array if ends with comma', function () {
        $modOptions = getModOptions();
        $content = "[\n  1,\n  \"foo\",\n  \"bar\",\n]";
        $edits = Editor::modify($content, [2], RemoveMarker::instance(), $modOptions);
        assertModifyEdits($content, $edits, "[\n  1,\n  \"foo\"\n]");
    });

    test('remove last item in the array if there is a comment in the beginning', function () {
        $modOptions = getModOptions();
        $content = "// This is a comment\n[\n  1,\n  \"foo\",\n  \"bar\"\n]";
        $edits = Editor::modify($content, [2], RemoveMarker::instance(), $modOptions);
        assertModifyEdits($content, $edits, "// This is a comment\n[\n  1,\n  \"foo\"\n]");
    });

    test('set property without formatting', function () {
        $formattingOptions = getFormattingOptions();
        $content = "{\n  \"x\": [1, 2, 3],\n  \"y\": 0\n}";

        $edits = Editor::modify($content, ['x', 0], ['a' => 1, 'b' => 2], new ModificationOptions(
            formattingOptions: $formattingOptions
        ));
        assertModifyEdits($content, $edits, "{\n  \"x\": [{\n      \"a\": 1,\n      \"b\": 2\n    }, 2, 3],\n  \"y\": 0\n}");

        $edits = Editor::modify($content, ['x', 0], ['a' => 1, 'b' => 2], new ModificationOptions(
            formattingOptions: null
        ));
        assertModifyEdits($content, $edits, "{\n  \"x\": [{\"a\":1,\"b\":2}, 2, 3],\n  \"y\": 0\n}");
    });

    test('insert property when keepLines is true', function () {
        $modOptions = getModOptions();
        $content = '{}';
        $edits = Editor::modify($content, ['foo', 'foo2'], 'bar', $modOptions);
        assertModifyEdits($content, $edits, "{\n  \"foo\": {\n    \"foo2\": \"bar\"\n  }\n}");
    });
});
