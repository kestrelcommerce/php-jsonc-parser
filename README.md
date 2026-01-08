# PHP JSONC Parser

A PHP 8.4+ port of [Microsoft's node-jsonc-parser](https://github.com/microsoft/node-jsonc-parser) - a scanner and fault-tolerant parser for JSON with Comments (JSONC).

[![Tests](https://img.shields.io/badge/tests-118%20passing-brightgreen)](https://github.com/kestrelwp/php-jsonc-parser)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

## Features

- 🔍 **Scanner/Tokenizer** - Character-by-character lexical analysis
- 🌳 **Parser** - Three parsing modes:
  - SAX-style visitor pattern with event callbacks
  - DOM-style tree builder with AST nodes
  - Direct evaluation to PHP arrays/objects
- 💬 **Comment Support** - Handles `//` line comments and `/* */` block comments
- ⚡ **Fault Tolerant** - Continues parsing on errors and collects error information
- 🎨 **Formatter** - Configurable indentation, line breaks, and comment handling
- ✏️ **Editor** - Modify JSON with insert, update, and delete operations
- 🧭 **Navigation** - Find nodes by path, offset, or location matching
- 🚀 **Performance** - String interning for optimized formatting

## Installation

```bash
composer require kestrelwp/php-jsonc-parser
```

## Quick Start

```php
use Kestrel\JsoncParser\JsoncParser;

// Parse JSONC to PHP array
$json = '{"name": "John", /* comment */ "age": 30}';
$errors = [];
$data = JsoncParser::parse($json, $errors);
// ['name' => 'John', 'age' => 30]

// Strip comments
$clean = JsoncParser::stripComments($json);
// '{"name": "John",  "age": 30}'

// Format JSON
use Kestrel\JsoncParser\Format\FormattingOptions;

$formatted = JsoncParser::applyEdits(
    $json,
    JsoncParser::format($json, null, new FormattingOptions(
        insertSpaces: true,
        tabSize: 2
    ))
);
```

## Usage

### Parsing

#### Direct Evaluation

Parse JSON/JSONC and convert to PHP arrays and objects:

```php
use Kestrel\JsoncParser\JsoncParser;

$json = '{
  "name": "Alice",
  "age": 25,
  "hobbies": ["reading", "coding"]
}';

$errors = [];
$data = JsoncParser::parse($json, $errors);

// $data = [
//     'name' => 'Alice',
//     'age' => 25,
//     'hobbies' => ['reading', 'coding']
// ]

// Check for errors
if (!empty($errors)) {
    foreach ($errors as $error) {
        echo "Error: {$error->error->value} at offset {$error->offset}\n";
    }
}
```

#### DOM Tree

Build an Abstract Syntax Tree (AST):

```php
use Kestrel\JsoncParser\JsoncParser;

$json = '{"x": 1, "y": 2}';
$errors = [];
$tree = JsoncParser::parseTree($json, $errors);

// Navigate the tree
foreach ($tree->children as $property) {
    $key = $property->children[0]->value;   // Property name
    $value = $property->children[1]->value; // Property value
    echo "$key = $value\n";
}
```

#### Visitor Pattern

Process JSON with event callbacks (SAX-style):

```php
use Kestrel\JsoncParser\JsoncParser;
use Kestrel\JsoncParser\Parser\JsonVisitor;

$visitor = new class implements JsonVisitor {
    public function onObjectBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null {
        echo "Object started at offset $offset\n";
        return null;
    }

    public function onObjectProperty(string $property, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null {
        echo "Property: $property\n";
        return null;
    }

    public function onLiteralValue(mixed $value, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null {
        echo "Value: $value\n";
        return null;
    }

    // Implement other interface methods...
    public function onObjectEnd(int $offset, int $length, int $startLine, int $startCharacter): bool|null { return null; }
    public function onArrayBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null { return null; }
    public function onArrayEnd(int $offset, int $length, int $startLine, int $startCharacter): bool|null { return null; }
    public function onSeparator(string $character, int $offset, int $length, int $startLine, int $startCharacter): bool|null { return null; }
    public function onComment(int $offset, int $length, int $startLine, int $startCharacter): bool|null { return null; }
    public function onError(int $error, int $offset, int $length, int $startLine, int $startCharacter): bool|null { return null; }
};

$json = '{"name": "Bob", "age": 30}';
JsoncParser::visit($json, $visitor);
```

### Scanning/Tokenization

Low-level token scanning:

```php
use Kestrel\JsoncParser\JsoncParser;
use Kestrel\JsoncParser\Scanner\SyntaxKind;

$scanner = JsoncParser::createScanner('{"key": 123}');

while (($token = $scanner->scan()) !== SyntaxKind::EOF) {
    echo $token->name . ': ' . $scanner->getTokenValue() . "\n";
}
// OpenBraceToken: {
// StringLiteral: "key"
// ColonToken: :
// NumericLiteral: 123
// CloseBraceToken: }
```

### Navigation

Find nodes in the AST:

```php
use Kestrel\JsoncParser\JsoncParser;

$json = '{"user": {"name": "Alice", "age": 30}}';
$tree = JsoncParser::parseTree($json);

// Find by path
$nameNode = JsoncParser::findNodeAtLocation($tree, ['user', 'name']);
echo JsoncParser::getNodeValue($nameNode); // "Alice"

// Find by offset
$node = JsoncParser::findNodeAtOffset($tree, 15);

// Get node path
$path = JsoncParser::getNodePath($nameNode);
// ['user', 'name']

// Get location at position
$location = JsoncParser::getLocation($json, 15);
echo implode('.', $location->path); // "user.name"
```

### Formatting

Format JSON with configurable options:

```php
use Kestrel\JsoncParser\JsoncParser;
use Kestrel\JsoncParser\Format\FormattingOptions;

$json = '{"name":"Alice","age":30}';

$options = new FormattingOptions(
    insertSpaces: true,     // Use spaces instead of tabs
    tabSize: 2,            // Indent size
    insertFinalNewline: true,
    eol: "\n",             // Line ending
    keepLines: false       // Don't preserve original line breaks
);

$edits = JsoncParser::format($json, null, $options);
$formatted = JsoncParser::applyEdits($json, $edits);

echo $formatted;
// {
//   "name": "Alice",
//   "age": 30
// }
```

### Modification

Modify JSON documents:

```php
use Kestrel\JsoncParser\JsoncParser;
use Kestrel\JsoncParser\Edit\ModificationOptions;
use Kestrel\JsoncParser\Edit\RemoveMarker;
use Kestrel\JsoncParser\Format\FormattingOptions;

$json = '{"name": "Alice", "age": 30}';

$options = new ModificationOptions(
    formattingOptions: new FormattingOptions(
        insertSpaces: true,
        tabSize: 2
    )
);

// Set a value
$edits = JsoncParser::modify($json, ['email'], 'alice@example.com', $options);
$json = JsoncParser::applyEdits($json, $edits);

// Update a value
$edits = JsoncParser::modify($json, ['age'], 31, $options);
$json = JsoncParser::applyEdits($json, $edits);

// Delete a property (use RemoveMarker, not null - null is a valid JSON value)
$edits = JsoncParser::modify($json, ['age'], RemoveMarker::instance(), $options);
$json = JsoncParser::applyEdits($json, $edits);

// Array operations
$json = '{"items": [1, 2, 3]}';

// Insert at end (-1)
$edits = JsoncParser::modify($json, ['items', -1], 4, $options);

// Insert at specific index
$edits = JsoncParser::modify($json, ['items', 1], 99, new ModificationOptions(
    formattingOptions: $options->formattingOptions,
    isArrayInsertion: true  // Insert, don't replace
));

// Delete array element
$edits = JsoncParser::modify($json, ['items', 0], RemoveMarker::instance(), $options);
```

### Comment Handling

```php
use Kestrel\JsoncParser\JsoncParser;

$jsonc = '{
  "name": "Alice", // user name
  /* age field */ "age": 30
}';

// Strip comments
$clean = JsoncParser::stripComments($jsonc);
// {"name": "Alice",  "age": 30}

// Replace comments with spaces (preserves formatting)
$replaced = JsoncParser::stripComments($jsonc, ' ');

// Parse with comments (automatically handled)
$data = JsoncParser::parse($jsonc);
```

## API Reference

### JsoncParser (Facade)

Main entry point for all operations.

#### Scanner
- `createScanner(string $text, bool $ignoreTrivia = false): JsonScanner`

#### Parser
- `parse(string $text, array &$errors = [], ?ParseOptions $options = null): mixed`
- `parseTree(string $text, array &$errors = [], ?ParseOptions $options = null): ?Node`
- `visit(string $text, JsonVisitor $visitor, ?ParseOptions $options = null): mixed`
- `stripComments(string $text, ?string $replaceCh = null): string`

#### Navigation
- `getLocation(string $text, int $position): Location`
- `findNodeAtLocation(?Node $root, array $path): ?Node`
- `findNodeAtOffset(Node $node, int $offset, bool $includeRightBound = false): ?Node`
- `getNodePath(Node $node): array`
- `getNodeValue(Node $node): mixed`

#### Formatting
- `format(string $documentText, ?Range $range, FormattingOptions $options): array`

#### Editing
- `modify(string $text, array $path, mixed $value, ModificationOptions $options): array`
- `applyEdits(string $text, array $edits): string`

### Options Classes

#### ParseOptions
```php
new ParseOptions(
    disallowComments: false,    // Reject comments
    allowTrailingComma: false,  // Allow trailing commas
    allowEmptyContent: false    // Allow empty input
)
```

#### FormattingOptions
```php
new FormattingOptions(
    insertSpaces: true,         // Use spaces vs tabs
    tabSize: 2,                 // Indent size
    insertFinalNewline: false,  // Add newline at EOF
    eol: "\n",                  // Line ending
    keepLines: false            // Preserve line breaks
)
```

#### ModificationOptions
```php
new ModificationOptions(
    formattingOptions: $formattingOptions,
    isArrayInsertion: false,    // Insert vs replace in arrays
    getInsertionIndex: null     // Custom property ordering
)
```

## Error Handling

The parser is fault-tolerant and continues on errors:

```php
$json = '{"invalid": true,}'; // Trailing comma
$errors = [];
$data = JsoncParser::parse($json, $errors);

foreach ($errors as $error) {
    echo "Error code: {$error->error->value}\n";
    echo "At offset: {$error->offset}\n";
    echo "Length: {$error->length}\n";
}
```

## Examples

See the [examples](examples/) directory for complete working examples:
- [basic_parsing.php](examples/basic_parsing.php) - Parsing, scanning, and basic operations
- [visitor_pattern.php](examples/visitor_pattern.php) - Using the visitor pattern
- [modification.php](examples/modification.php) - Modifying and formatting JSON

## Differences from JavaScript Version

1. **Deletion marker**: PHP doesn't distinguish `null` from `undefined`, so we use `RemoveMarker::instance()` to indicate deletion in `modify()` operations
2. **Return type**: Visitor methods return `bool|null` instead of `void` for consistency with PHP's type system
3. **Naming**: Uses PSR-12 style (e.g., `getNodeValue` instead of `getNodeValue`)

## Performance

The library includes performance optimizations:
- **String interning**: Caches commonly used strings (spaces, line breaks)
- **Lazy evaluation**: Only parses what's needed
- **Efficient scanning**: Character-by-character with minimal allocations

## Requirements

- PHP 8.4 or higher
- ext-mbstring (for UTF-8 support)

## Testing

```bash
# Run tests
composer test

# Run with coverage
composer test-coverage

# Code style
composer format

# Static analysis
composer stan
```

## Credits

This is a PHP port of Microsoft's [node-jsonc-parser](https://github.com/microsoft/node-jsonc-parser), licensed under the MIT License.

## License

MIT License - see [LICENSE.md](LICENSE.md) for details.

## Contributing

Contributions are welcome! Please ensure:
- All tests pass (`composer test`)
- Code follows PSR-12 (`composer format`)
- PHPStan passes (`composer stan`)
