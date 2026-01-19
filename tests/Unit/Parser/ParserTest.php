<?php

declare(strict_types=1);

use Kestrel\JsoncParser\JsoncParser;
use Kestrel\JsoncParser\Parser\ParseOptions;
use Kestrel\JsoncParser\Parser\NodeType;
use Kestrel\JsoncParser\Parser\JsonVisitor;
use Kestrel\JsoncParser\Parser\ParseErrorCode;

/**
 * A reusable visitor that records events to an array.
 * Configure which events to record via constructor parameters.
 */
class EventRecordingVisitor implements JsonVisitor
{
    /** @var array<array<mixed>> */
    public array $events = [];

    public function __construct(
        private bool $recordObject = true,
        private bool $recordArray = true,
        private bool $recordLiteral = true,
        private bool $recordSeparator = true,
        private bool $recordComment = true,
        private bool $recordError = true,
    ) {
    }

    public function onObjectBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        if ($this->recordObject) {
            $this->events[] = ['onObjectBegin', $offset, $pathSupplier()];
        }
        return null;
    }

    public function onObjectProperty(string $property, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): void
    {
        if ($this->recordObject) {
            $this->events[] = ['onObjectProperty', $property, $pathSupplier()];
        }
    }

    public function onObjectEnd(int $offset, int $length, int $startLine, int $startCharacter): void
    {
        if ($this->recordObject) {
            $this->events[] = ['onObjectEnd', $offset];
        }
    }

    public function onArrayBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        if ($this->recordArray) {
            $this->events[] = ['onArrayBegin', $offset, $pathSupplier()];
        }
        return null;
    }

    public function onArrayEnd(int $offset, int $length, int $startLine, int $startCharacter): void
    {
        if ($this->recordArray) {
            $this->events[] = ['onArrayEnd', $offset];
        }
    }

    public function onLiteralValue(mixed $value, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): void
    {
        if ($this->recordLiteral) {
            $this->events[] = ['onLiteralValue', $value, $pathSupplier()];
        }
    }

    public function onSeparator(string $character, int $offset, int $length, int $startLine, int $startCharacter): void
    {
        if ($this->recordSeparator) {
            $this->events[] = ['onSeparator', $character];
        }
    }

    public function onComment(int $offset, int $length, int $startLine, int $startCharacter): void
    {
        if ($this->recordComment) {
            $this->events[] = ['onComment', $offset];
        }
    }

    public function onError(ParseErrorCode $error, int $offset, int $length, int $startLine, int $startCharacter): void
    {
        if ($this->recordError) {
            $this->events[] = ['onError', $error];
        }
    }
}

describe('parse: literals', function () {
    test('parses boolean literals', function () {
        assertValidParse('true', true);
        assertValidParse('false', false);
    });

    test('parses null', function () {
        assertValidParse('null', null);
    });

    test('parses strings', function () {
        assertValidParse('"foo"', 'foo');
        assertValidParse('"\\"-\\\\-\\/-\\b-\\f-\\n-\\r-\\t"', '"-\\-/-' . "\x08-\f-\n-\r-\t");
        assertValidParse('"\\u00DC"', 'Ü');
    });

    test('parses numbers', function () {
        assertValidParse('9', 9);
        assertValidParse('-9', -9);
        assertValidParse('0.129', 0.129);
        assertValidParse('23e3', 23e3);
        assertValidParse('1.2E+3', 1.2E+3);
        assertValidParse('1.2E-3', 1.2E-3);
    });

    test('parses with comments', function () {
        assertValidParse('1.2E-3 // comment', 1.2E-3);
    });
});

describe('parse: objects', function () {
    test('parses empty object', function () {
        assertValidParse('{}', []);
    });

    test('parses simple objects', function () {
        assertValidParse('{ "foo": true }', ['foo' => true]);
        assertValidParse('{ "bar": 8, "xoo": "foo" }', ['bar' => 8, 'xoo' => 'foo']);
    });

    test('parses nested objects', function () {
        assertValidParse('{ "hello": [], "world": {} }', ['hello' => [], 'world' => []]);
        assertValidParse(
            '{ "a": false, "b": true, "c": [ 7.4 ] }',
            ['a' => false, 'b' => true, 'c' => [7.4]]
        );
    });

    test('parses complex nested objects', function () {
        assertValidParse(
            '{ "hello": { "again": { "inside": 5 }, "world": 1 }}',
            ['hello' => ['again' => ['inside' => 5], 'world' => 1]]
        );
    });

    test('parses objects with comments', function () {
        assertValidParse('{ "foo": /*hello*/true }', ['foo' => true]);
    });

    test('parses objects with empty string keys', function () {
        assertValidParse('{ "": true }', ['' => true]);
    });
});

describe('parse: arrays', function () {
    test('parses empty array', function () {
        assertValidParse('[]', []);
    });

    test('parses nested arrays', function () {
        assertValidParse('[ [],  [ [] ]]', [[], [[]]]);
    });

    test('parses arrays with values', function () {
        assertValidParse('[ 1, 2, 3 ]', [1, 2, 3]);
        assertValidParse('[ { "a": null } ]', [['a' => null]]);
    });
});

describe('parse: objects with errors', function () {
    test('recovers from comma errors', function () {
        assertInvalidParse('{,}', []);
        assertInvalidParse('{ "foo": true, }', ['foo' => true]);
        assertInvalidParse('{ ,"bar": 8 }', ['bar' => 8]);
    });

    test('recovers from missing comma', function () {
        assertInvalidParse('{ "bar": 8 "xoo": "foo" }', ['bar' => 8, 'xoo' => 'foo']);
    });

    test('recovers from incomplete properties', function () {
        assertInvalidParse('{ ,"bar": 8, "foo" }', ['bar' => 8]);
        assertInvalidParse('{ "bar": 8, "foo": }', ['bar' => 8]);
    });

    test('recovers from invalid keys', function () {
        assertInvalidParse('{ 8, "foo": 9 }', ['foo' => 9]);
    });
});

describe('parse: arrays with errors', function () {
    test('recovers from comma errors', function () {
        assertInvalidParse('[,]', []);
        assertInvalidParse('[ ,1, 2, 3 ]', [1, 2, 3]);
        assertInvalidParse('[ ,1, 2, 3, ]', [1, 2, 3]);
    });

    test('recovers from missing comma', function () {
        assertInvalidParse('[ 1 2, 3 ]', [1, 2, 3]);
    });
});

describe('parse: general errors', function () {
    test('handles empty input', function () {
        assertInvalidParse('', null);
    });

    test('handles multiple values', function () {
        assertInvalidParse('1,1', 1);
    });
});

describe('parse: options', function () {
    test('disallowComments option', function () {
        $options = new ParseOptions(disallowComments: true);

        assertValidParse('[ 1, 2, null, "foo" ]', [1, 2, null, 'foo'], $options);
        assertValidParse('{ "hello": [], "world": {} }', ['hello' => [], 'world' => []], $options);

        assertInvalidParse('{ "foo": /*comment*/ true }', ['foo' => true], $options);
    });

    test('allowTrailingComma option', function () {
        $options = new ParseOptions(allowTrailingComma: true);

        assertValidParse('{ "hello": [], }', ['hello' => []], $options);
        assertValidParse('{ "hello": [] }', ['hello' => []], $options);
        assertValidParse('{ "hello": [], "world": {}, }', ['hello' => [], 'world' => []], $options);
        assertValidParse('[ 1, 2, ]', [1, 2], $options);
        assertValidParse('[ 1, 2 ]', [1, 2], $options);

        // Without the option, trailing commas are errors
        assertInvalidParse('{ "hello": [], }', ['hello' => []]);
        assertInvalidParse('[ 1, 2, ]', [1, 2]);
    });
});

describe('parseTree: literals', function () {
    test('parses boolean literals', function () {
        $tree = JsoncParser::parseTree('true');
        assert($tree !== null);
        expect($tree->type)->toBe(NodeType::Boolean);
        expect($tree->value)->toBe(true);
        expect($tree->offset)->toBe(0);
        expect($tree->length)->toBe(4);

        $tree = JsoncParser::parseTree('false');
        assert($tree !== null);
        expect($tree->type)->toBe(NodeType::Boolean);
        expect($tree->value)->toBe(false);
        expect($tree->length)->toBe(5);
    });

    test('parses null', function () {
        $tree = JsoncParser::parseTree('null');
        assert($tree !== null);
        expect($tree->type)->toBe(NodeType::Null);
        expect($tree->value)->toBe(null);
        expect($tree->length)->toBe(4);
    });

    test('parses numbers', function () {
        $tree = JsoncParser::parseTree('23');
        assert($tree !== null);
        expect($tree->type)->toBe(NodeType::Number);
        expect($tree->value)->toBe(23);
        expect($tree->length)->toBe(2);

        $tree = JsoncParser::parseTree('-1.93e-19');
        assert($tree !== null);
        expect($tree->type)->toBe(NodeType::Number);
        expect($tree->value)->toBe(-1.93e-19);
        expect($tree->length)->toBe(9);
    });

    test('parses strings', function () {
        $tree = JsoncParser::parseTree('"hello"');
        assert($tree !== null);
        expect($tree->type)->toBe(NodeType::String);
        expect($tree->value)->toBe('hello');
        expect($tree->length)->toBe(7);
    });
});

describe('parseTree: arrays', function () {
    test('parses empty array', function () {
        $tree = JsoncParser::parseTree('[]');
        assert($tree !== null);
        expect($tree->type)->toBe(NodeType::Array);
        expect($tree->offset)->toBe(0);
        expect($tree->length)->toBe(2);
        expect($tree->children)->toBe([]);
    });

    test('parses simple array', function () {
        $tree = JsoncParser::parseTree('[ 1 ]');
        assert($tree !== null);
        expect($tree->type)->toBe(NodeType::Array);
        expect($tree->length)->toBe(5);
        assert($tree->children !== null);
        expect($tree->children)->toHaveCount(1);
        expect($tree->children[0]->type)->toBe(NodeType::Number);
        expect($tree->children[0]->value)->toBe(1);
    });

    test('parses array with multiple values', function () {
        $tree = JsoncParser::parseTree('[ 1,"x"]');
        assert($tree !== null);
        expect($tree->type)->toBe(NodeType::Array);
        assert($tree->children !== null);
        expect($tree->children)->toHaveCount(2);
        expect($tree->children[0]->type)->toBe(NodeType::Number);
        expect($tree->children[0]->value)->toBe(1);
        expect($tree->children[1]->type)->toBe(NodeType::String);
        expect($tree->children[1]->value)->toBe('x');
    });

    test('parses nested arrays', function () {
        $tree = JsoncParser::parseTree('[[]]');
        assert($tree !== null);
        expect($tree->type)->toBe(NodeType::Array);
        assert($tree->children !== null);
        expect($tree->children)->toHaveCount(1);
        expect($tree->children[0]->type)->toBe(NodeType::Array);
        expect($tree->children[0]->children)->toBe([]);
    });
});

describe('parseTree: objects', function () {
    test('parses empty object', function () {
        $tree = JsoncParser::parseTree('{ }');
        assert($tree !== null);
        expect($tree->type)->toBe(NodeType::Object);
        expect($tree->offset)->toBe(0);
        expect($tree->length)->toBe(3);
        expect($tree->children)->toBe([]);
    });

    test('parses simple object', function () {
        $tree = JsoncParser::parseTree('{ "val": 1 }');
        assert($tree !== null);
        expect($tree->type)->toBe(NodeType::Object);
        assert($tree->children !== null);
        expect($tree->children)->toHaveCount(1);

        $prop = $tree->children[0];
        expect($prop->type)->toBe(NodeType::Property);
        expect($prop->offset)->toBe(2);
        expect($prop->length)->toBe(8);
        expect($prop->colonOffset)->toBe(7);
        assert($prop->children !== null);
        expect($prop->children)->toHaveCount(2);

        expect($prop->children[0]->type)->toBe(NodeType::String);
        expect($prop->children[0]->value)->toBe('val');
        expect($prop->children[1]->type)->toBe(NodeType::Number);
        expect($prop->children[1]->value)->toBe(1);
    });

    test('verifies parent references', function () {
        $tree = JsoncParser::parseTree('{ "val": 1 }');
        assert($tree !== null);
        assert($tree->children !== null);

        $prop = $tree->children[0];
        expect($prop->parent)->toBe($tree);
        assert($prop->children !== null);
        expect($prop->children[0]->parent)->toBe($prop);
        expect($prop->children[1]->parent)->toBe($prop);
    });
});

describe('visit: object', function () {
    test('visits empty object', function () {
        $visitor = new EventRecordingVisitor(
            recordArray: false,
            recordLiteral: false,
            recordComment: false,
            recordError: false,
        );

        JsoncParser::visit('{ }', $visitor);

        expect($visitor->events)->toBe([
            ['onObjectBegin', 0, []],
            ['onObjectEnd', 2],
        ]);
    });

    test('visits simple object', function () {
        $visitor = new EventRecordingVisitor(
            recordArray: false,
            recordComment: false,
            recordError: false,
        );

        JsoncParser::visit('{ "foo": "bar" }', $visitor);

        expect($visitor->events)->toBe([
            ['onObjectBegin', 0, []],
            ['onObjectProperty', 'foo', []],
            ['onSeparator', ':'],
            ['onLiteralValue', 'bar', ['foo']],
            ['onObjectEnd', 15],
        ]);
    });
});

describe('visit: array', function () {
    test('visits empty array', function () {
        $visitor = new EventRecordingVisitor(
            recordObject: false,
            recordSeparator: false,
            recordComment: false,
            recordError: false,
        );

        JsoncParser::visit('[]', $visitor);

        expect($visitor->events)->toBe([
            ['onArrayBegin', 0, []],
            ['onArrayEnd', 1],
        ]);
    });

    test('visits array with values', function () {
        $visitor = new EventRecordingVisitor(
            recordObject: false,
            recordSeparator: false,
            recordComment: false,
            recordError: false,
        );

        JsoncParser::visit('[ true, null ]', $visitor);

        expect($visitor->events)->toBe([
            ['onArrayBegin', 0, []],
            ['onLiteralValue', true, [0]],
            ['onLiteralValue', null, [1]],
            ['onArrayEnd', 13],
        ]);
    });
});

describe('navigation: findNodeAtLocation', function () {
    test('finds nodes in object', function () {
        $tree = JsoncParser::parseTree('{ "foo": "bar", "baz": 42 }');

        $node = JsoncParser::findNodeAtLocation($tree, ['foo']);
        assert($node !== null);
        expect(JsoncParser::getNodeValue($node))->toBe('bar');

        $node = JsoncParser::findNodeAtLocation($tree, ['baz']);
        assert($node !== null);
        expect(JsoncParser::getNodeValue($node))->toBe(42);

        $node = JsoncParser::findNodeAtLocation($tree, ['missing']);
        expect($node)->toBeNull();
    });

    test('finds nodes in nested structures', function () {
        $tree = JsoncParser::parseTree('{ "a": { "b": { "c": true } } }');

        $node = JsoncParser::findNodeAtLocation($tree, ['a', 'b', 'c']);
        assert($node !== null);
        expect(JsoncParser::getNodeValue($node))->toBe(true);
    });

    test('finds nodes in arrays', function () {
        $tree = JsoncParser::parseTree('[1, 2, 3]');

        $node = JsoncParser::findNodeAtLocation($tree, [0]);
        assert($node !== null);
        expect(JsoncParser::getNodeValue($node))->toBe(1);

        $node = JsoncParser::findNodeAtLocation($tree, [2]);
        assert($node !== null);
        expect(JsoncParser::getNodeValue($node))->toBe(3);

        $node = JsoncParser::findNodeAtLocation($tree, [5]);
        expect($node)->toBeNull();
    });
});

describe('navigation: getNodePath', function () {
    test('returns path for nested object properties', function () {
        $tree = JsoncParser::parseTree('{ "a": { "b": 1 } }');
        $node = JsoncParser::findNodeAtLocation($tree, ['a', 'b']);

        assert($node !== null);
        expect(JsoncParser::getNodePath($node))->toBe(['a', 'b']);
    });

    test('returns path for array elements', function () {
        $tree = JsoncParser::parseTree('[[1, 2], [3, 4]]');
        $node = JsoncParser::findNodeAtLocation($tree, [1, 0]);

        assert($node !== null);
        expect(JsoncParser::getNodePath($node))->toBe([1, 0]);
    });
});

describe('stripComments', function () {
    test('removes line comments', function () {
        $result = JsoncParser::stripComments("{ \"foo\": 1 // comment\n}");
        expect($result)->toBe("{ \"foo\": 1 \n}");
    });

    test('removes block comments', function () {
        $result = JsoncParser::stripComments('{ /* comment */ "foo": 1 }');
        expect($result)->toBe('{  "foo": 1 }');
    });

    test('replaces comments with character', function () {
        $result = JsoncParser::stripComments("{ \"foo\": 1 // comment\n}", ' ');
        expect($result)->toBe("{ \"foo\": 1           \n}");
    });
});
