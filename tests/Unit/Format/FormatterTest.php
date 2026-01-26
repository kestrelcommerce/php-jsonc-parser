<?php

declare(strict_types=1);

use Kestrel\JsoncParser\Format\Formatter;
use Kestrel\JsoncParser\Format\FormattingOptions;
use Kestrel\JsoncParser\Edit\Range;
use Kestrel\JsoncParser\Util\StringHelper;

/**
 * Format helper function
 * Formats content and applies edits, handles range markers (|)
 * Uses StringHelper for UTF-8 safe string operations since edits use character offsets
 */
function formatJson(string $content, string $expected, bool $insertSpaces = true, bool $insertFinalNewline = false, bool $keepLines = false): void
{
    $range = null;
    // Use strpos for range markers since | is ASCII and byte position works for finding it
    $rangeStart = strpos($content, '|');
    $rangeEnd = strrpos($content, '|');

    if ($rangeStart !== false && $rangeEnd !== false && $rangeStart !== $rangeEnd) {
        // Convert byte positions to character positions for proper range handling
        $rangeStartChar = StringHelper::length(substr($content, 0, $rangeStart));
        $rangeEndChar = StringHelper::length(substr($content, 0, $rangeEnd));
        // Remove the | markers and create range (using character positions)
        $content = StringHelper::substring($content, 0, $rangeStartChar) .
                   StringHelper::substring($content, $rangeStartChar + 1, $rangeEndChar) .
                   StringHelper::substring($content, $rangeEndChar + 1);
        // Range length matches original formula: $rangeEnd - $rangeStart
        $range = new Range($rangeStartChar, $rangeEndChar - $rangeStartChar);
    }

    $options = new FormattingOptions(
        tabSize: 2,
        insertSpaces: $insertSpaces,
        eol: "\n",
        insertFinalNewline: $insertFinalNewline,
        keepLines: $keepLines
    );

    $edits = Formatter::format($content, $range, $options);

    // Apply edits from end to beginning using UTF-8 safe operations
    for ($i = count($edits) - 1; $i >= 0; $i--) {
        $edit = $edits[$i];
        $content = StringHelper::substring($content, 0, $edit->offset) . $edit->content . StringHelper::substring($content, $edit->offset + $edit->length);
    }

    expect($content)->toBe($expected);
}

describe('Formatter', function () {
    test('object - single property', function () {
        $content = '{"x" : 1}';

        $expected = implode("\n", [
            '{',
            '  "x": 1',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('object - multiple properties', function () {
        $content = '{"x" : 1,  "y" : "foo", "z"  : true}';

        $expected = implode("\n", [
            '{',
            '  "x": 1,',
            '  "y": "foo",',
            '  "z": true',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('object - no properties', function () {
        $content = '{"x" : {    },  "y" : {}}';

        $expected = implode("\n", [
            '{',
            '  "x": {},',
            '  "y": {}',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('object - nesting', function () {
        $content = '{"x" : {  "y" : { "z"  : { }}, "a": true}}';

        $expected = implode("\n", [
            '{',
            '  "x": {',
            '    "y": {',
            '      "z": {}',
            '    },',
            '    "a": true',
            '  }',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('array - single items', function () {
        $content = '["[]"]';

        $expected = implode("\n", [
            '[',
            '  "[]"',
            ']'
        ]);

        formatJson($content, $expected);
    });

    test('array - multiple items', function () {
        $content = '[true,null,1.2]';

        $expected = implode("\n", [
            '[',
            '  true,',
            '  null,',
            '  1.2',
            ']'
        ]);

        formatJson($content, $expected);
    });

    test('array - no items', function () {
        $content = '[      ]';

        $expected = '[]';

        formatJson($content, $expected);
    });

    test('array - nesting', function () {
        $content = '[ [], [ [ {} ], "a" ]  ]';

        $expected = implode("\n", [
            '[',
            '  [],',
            '  [',
            '    [',
            '      {}',
            '    ],',
            '    "a"',
            '  ]',
            ']'
        ]);

        formatJson($content, $expected);
    });

    test('syntax errors', function () {
        $content = '[ null  1.2 "Hello" ]';

        $expected = implode("\n", [
            '[',
            '  null  1.2 "Hello"',
            ']'
        ]);

        formatJson($content, $expected);
    });

    test('syntax errors 2', function () {
        $content = '{"a":"b""c":"d" }';

        $expected = implode("\n", [
            '{',
            '  "a": "b""c": "d"',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('empty lines', function () {
        $content = implode("\n", [
            '{',
            '"a": true,',
            '',
            '"b": true',
            '}'
        ]);

        $expected = implode("\n", [
            '{',
            "\t\"a\": true,",
            "\t\"b\": true",
            '}'
        ]);

        formatJson($content, $expected, false);
    });

    test('single line comment', function () {
        $content = implode("\n", [
            '[ ',
            '//comment',
            '"foo", "bar"',
            '] '
        ]);

        $expected = implode("\n", [
            '[',
            '  //comment',
            '  "foo",',
            '  "bar"',
            ']'
        ]);

        formatJson($content, $expected);
    });

    test('block line comment', function () {
        $content = implode("\n", [
            '[{',
            '        /*comment*/     ',
            '"foo" : true',
            '}] '
        ]);

        $expected = implode("\n", [
            '[',
            '  {',
            '    /*comment*/',
            '    "foo": true',
            '  }',
            ']'
        ]);

        formatJson($content, $expected);
    });

    test('single line comment on same line', function () {
        $content = implode("\n", [
            ' {  ',
            '        "a": {}// comment    ',
            ' } '
        ]);

        $expected = implode("\n", [
            '{',
            '  "a": {} // comment    ',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('single line comment on same line 2', function () {
        $content = implode("\n", [
            '{ //comment',
            '}'
        ]);

        $expected = implode("\n", [
            '{ //comment',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('block comment on same line', function () {
        $content = implode("\n", [
            '{      "a": {}, /*comment*/    ',
            '        /*comment*/ "b": {},    ',
            '        "c": {/*comment*/}    } '
        ]);

        $expected = implode("\n", [
            '{',
            '  "a": {}, /*comment*/',
            '  /*comment*/ "b": {},',
            '  "c": { /*comment*/}',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('block comment on same line advanced', function () {
        $content = implode("\n", [
            ' {       "d": [',
            '             null',
            '        ] /*comment*/',
            '        ,"e": /*comment*/ [null] }'
        ]);

        $expected = implode("\n", [
            '{',
            '  "d": [',
            '    null',
            '  ] /*comment*/,',
            '  "e": /*comment*/ [',
            '    null',
            '  ]',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('multiple block comments on same line', function () {
        $content = implode("\n", [
            '{      "a": {} /*comment*/, /*comment*/   ',
            '        /*comment*/ "b": {}  /*comment*/  } '
        ]);

        $expected = implode("\n", [
            '{',
            '  "a": {} /*comment*/, /*comment*/',
            '  /*comment*/ "b": {} /*comment*/',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('multiple mixed comments on same line', function () {
        $content = implode("\n", [
            '[ /*comment*/  /*comment*/   // comment ',
            ']'
        ]);

        $expected = implode("\n", [
            '[ /*comment*/ /*comment*/ // comment ',
            ']'
        ]);

        formatJson($content, $expected);
    });

    test('range', function () {
        $content = implode("\n", [
            '{ "a": {},',
            '|"b": [null, null]|',
            '} '
        ]);

        $expected = implode("\n", [
            '{ "a": {},',
            '"b": [',
            '  null,',
            '  null',
            ']',
            '} '
        ]);

        formatJson($content, $expected);
    });

    test('range with existing indent', function () {
        $content = implode("\n", [
            '{ "a": {},',
            '   |"b": [null],',
            '"c": {}',
            '}|'
        ]);

        $expected = implode("\n", [
            '{ "a": {},',
            '   "b": [',
            '    null',
            '  ],',
            '  "c": {}',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('range with existing indent - tabs', function () {
        $content = implode("\n", [
            '{ "a": {},',
            '|  "b": [null],   ',
            '"c": {}',
            '}|    '
        ]);

        $expected = implode("\n", [
            '{ "a": {},',
            "\t\"b\": [",
            "\t\tnull",
            "\t],",
            "\t\"c\": {}",
            '}'
        ]);

        formatJson($content, $expected, false);
    });

    test('property range - issue 14623', function () {
        $content = implode("\n", [
            '{ |"a" :| 1,',
            '  "b": 1',
            '}'
        ]);

        $expected = implode("\n", [
            '{ "a": 1,',
            '  "b": 1',
            '}'
        ]);

        formatJson($content, $expected, false);
    });

    test('block comment none-line breaking symbols', function () {
        $content = implode("\n", [
            '{ "a": [ 1',
            '/* comment */',
            ', 2',
            '/* comment */',
            ']',
            '/* comment */',
            ',',
            ' "b": true',
            '/* comment */',
            '}'
        ]);

        $expected = implode("\n", [
            '{',
            '  "a": [',
            '    1',
            '    /* comment */',
            '    ,',
            '    2',
            '    /* comment */',
            '  ]',
            '  /* comment */',
            '  ,',
            '  "b": true',
            '  /* comment */',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('line comment after none-line breaking symbols', function () {
        $content = implode("\n", [
            '{ "a":',
            '// comment',
            'null,',
            ' "b"',
            '// comment',
            ': null',
            '// comment',
            '}'
        ]);

        $expected = implode("\n", [
            '{',
            '  "a":',
            '  // comment',
            '  null,',
            '  "b"',
            '  // comment',
            '  : null',
            '  // comment',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('line comment, enforce line comment', function () {
        $content = implode("\n", [
            '{"settings": // This is some text',
            '{',
            '"foo": 1',
            '}',
            '}'
        ]);

        $expected = implode("\n", [
            '{',
            '  "settings": // This is some text',
            '  {',
            '    "foo": 1',
            '  }',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('random content', function () {
        $content = 'a 1 b 1 3 true';

        $expected = 'a 1 b 1 3 true';

        formatJson($content, $expected);
    });

    // UTF-8 multibyte character tests

    test('UTF-8 characters in keys', function () {
        $content = '{"café" : "latte",  "日本語" : "test"}';

        $expected = implode("\n", [
            '{',
            '  "café": "latte",',
            '  "日本語": "test"',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('UTF-8 characters in values', function () {
        $content = '{"greeting" : "こんにちは",  "emoji" : "🎉🎊🎈"}';

        $expected = implode("\n", [
            '{',
            '  "greeting": "こんにちは",',
            '  "emoji": "🎉🎊🎈"',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('mixed UTF-8 byte lengths', function () {
        // Mix of 2-byte (é, ñ), 3-byte (日, €), and 4-byte (🎉) UTF-8 characters
        $content = '{"café" : "€50",  "日本" : "🗾",  "señor" : "🎉"}';

        $expected = implode("\n", [
            '{',
            '  "café": "€50",',
            '  "日本": "🗾",',
            '  "señor": "🎉"',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('nested objects with UTF-8', function () {
        $content = '{"données" : {  "prénom" : "François", "âge"  : 30 }}';

        $expected = implode("\n", [
            '{',
            '  "données": {',
            '    "prénom": "François",',
            '    "âge": 30',
            '  }',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('arrays with UTF-8 content', function () {
        $content = '["日本語",  "한국어",  "中文"]';

        $expected = implode("\n", [
            '[',
            '  "日本語",',
            '  "한국어",',
            '  "中文"',
            ']'
        ]);

        formatJson($content, $expected);
    });

    test('range format with UTF-8 before range', function () {
        $content = implode("\n", [
            '{ "café": "☕",',
            '|"target": [1,2,3]|',
            '}'
        ]);

        $expected = implode("\n", [
            '{ "café": "☕",',
            '"target": [',
            '  1,',
            '  2,',
            '  3',
            ']',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('comments with UTF-8 content', function () {
        $content = implode("\n", [
            '{ ',
            '// コメント (Japanese comment)',
            '"key": "value"',
            '}'
        ]);

        $expected = implode("\n", [
            '{',
            '  // コメント (Japanese comment)',
            '  "key": "value"',
            '}'
        ]);

        formatJson($content, $expected);
    });

    test('block comment with UTF-8', function () {
        $content = implode("\n", [
            '[{',
            '        /* données françaises */     ',
            '"prénom" : "Jean"',
            '}]'
        ]);

        $expected = implode("\n", [
            '[',
            '  {',
            '    /* données françaises */',
            '    "prénom": "Jean"',
            '  }',
            ']'
        ]);

        formatJson($content, $expected);
    });

    test('insertFinalNewline', function () {
        $content = implode("\n", [
            '{',
            '}'
        ]);

        $expected = implode("\n", [
            '{}',
            ''
        ]);

        formatJson($content, $expected, true, true);
    });

    // keepLines feature tests

    test('adjust the indentation of a one-line array', function () {
        $content = implode("\n", [
            '{ "array": [1,2,3]',
            '}'
        ]);

        $expected = implode("\n", [
            '{ "array": [ 1, 2, 3 ]',
            '}'
        ]);

        formatJson($content, $expected, true, false, true);
    });

    test('adjust the indentation of a multi-line array', function () {
        $content = implode("\n", [
            '{"array":',
            ' [1,2,',
            ' 3]',
            '}'
        ]);

        $expected = implode("\n", [
            '{ "array":',
            '  [ 1, 2,',
            '    3 ]',
            '}'
        ]);

        formatJson($content, $expected, true, false, true);
    });

    test('adjust the identation of a one-line object', function () {
        $content = implode("\n", [
            '{"settings": // This is some text',
            '{"foo": 1}',
            '}'
        ]);

        $expected = implode("\n", [
            '{ "settings": // This is some text',
            '  { "foo": 1 }',
            '}'
        ]);

        formatJson($content, $expected, true, false, true);
    });

    test('multiple line breaks are kept', function () {
        $content = implode("\n", [
            '{"settings":',
            '',
            '',
            '',
            '{"foo": 1}',
            '}'
        ]);

        $expected = implode("\n", [
            '{ "settings":',
            '',
            '',
            '',
            '  { "foo": 1 }',
            '}'
        ]);

        formatJson($content, $expected, true, false, true);
    });

    test('adjusting multiple line breaks and a block comment, line breaks are kept', function () {
        $content = implode("\n", [
            '{"settings":',
            '',
            '',
            '{"foo": 1} /* this is a multiline',
            'comment */',
            '}'
        ]);

        $expected = implode("\n", [
            '{ "settings":',
            '',
            '',
            '  { "foo": 1 } /* this is a multiline',
            'comment */',
            '}'
        ]);

        formatJson($content, $expected, true, false, true);
    });

    test('colon is kept on its own line', function () {
        $content = implode("\n", [
            '{"settings"',
            ':',
            '{"foo"',
            ':',
            '1}',
            '}'
        ]);

        $expected = implode("\n", [
            '{ "settings"',
            '  :',
            '  { "foo"',
            '    :',
            '    1 }',
            '}'
        ]);

        formatJson($content, $expected, true, false, true);
    });

    test('adjusting the indentation of a nested multi-line array', function () {
        $content = implode("\n", [
            '{',
            '',
            '{',
            '',
            '"array"   : [1, 2',
            '3, 4]',
            '}',
            '}'
        ]);

        $expected = implode("\n", [
            '{',
            '',
            '  {',
            '',
            '    "array": [ 1, 2',
            '      3, 4 ]',
            '  }',
            '}'
        ]);

        formatJson($content, $expected, true, false, true);
    });

    test('adjusting the indentation for a series of empty arrays or objects', function () {
        $content = implode("\n", [
            '{',
            '',
            '}',
            '',
            '{',
            '[',
            ']',
            '}'
        ]);

        $expected = implode("\n", [
            '{',
            '',
            '}',
            '',
            '{',
            '  [',
            '  ]',
            '}'
        ]);

        formatJson($content, $expected, true, false, true);
    });

    test('adjusting the indentation for a series of multiple empty lines at the end', function () {
        $content = implode("\n", [
            '{',
            '}',
            '',
            '',
            ''
        ]);

        $expected = implode("\n", [
            '{',
            '}',
            '',
            '',
            ''
        ]);

        formatJson($content, $expected, true, false, true);
    });

    test('adjusting the indentation for comments on separate lines', function () {
        $content = implode("\n", [
            '',
            '',
            '',
            '   // comment 1',
            '',
            '',
            '',
            '  /* comment 2 */',
            'const'
        ]);

        $expected = implode("\n", [
            '',
            '',
            '',
            '// comment 1',
            '',
            '',
            '',
            '/* comment 2 */',
            'const'
        ]);

        formatJson($content, $expected, true, false, true);
    });
});
