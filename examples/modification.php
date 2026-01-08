<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kestrel\JsoncParser\JsoncParser;
use Kestrel\JsoncParser\Edit\ModificationOptions;
use Kestrel\JsoncParser\Edit\RemoveMarker;
use Kestrel\JsoncParser\Format\FormattingOptions;
use Kestrel\JsoncParser\Edit\Range;

echo "=== PHP JSONC Parser - Modification & Formatting Examples ===\n\n";

// Example 1: Basic Formatting
echo "1. Formatting JSON\n";
echo str_repeat('-', 50) . "\n";

$unformatted = '{"name":"Alice","age":30,"city":"NYC"}';
echo "Unformatted:\n$unformatted\n\n";

$options = new FormattingOptions(
    insertSpaces: true,
    tabSize: 2,
    insertFinalNewline: false,
    eol: "\n"
);

$edits = JsoncParser::format($unformatted, null, $options);
$formatted = JsoncParser::applyEdits($unformatted, $edits);

echo "Formatted:\n$formatted\n\n";

// Example 2: Formatting with Tabs
echo "2. Formatting with Tabs\n";
echo str_repeat('-', 50) . "\n";

$options = new FormattingOptions(
    insertSpaces: false,  // Use tabs
    tabSize: 1,
    insertFinalNewline: true
);

$edits = JsoncParser::format($unformatted, null, $options);
$formatted = JsoncParser::applyEdits($unformatted, $edits);

echo "Formatted with tabs:\n$formatted";

// Example 3: Range Formatting
echo "3. Range Formatting (Partial Formatting)\n";
echo str_repeat('-', 50) . "\n";

$json = '{
  "section1": {"a": 1,"b": 2},
  "section2": {"c":3,"d":4}
}';

echo "Original:\n$json\n\n";

// Format only section2 (find it first)
$section2Start = strpos($json, '"section2"');
$section2End = strrpos($json, '}');
$range = new Range($section2Start, $section2End - $section2Start);

$options = new FormattingOptions(insertSpaces: true, tabSize: 2);
$edits = JsoncParser::format($json, $range, $options);
$formatted = JsoncParser::applyEdits($json, $edits);

echo "After range formatting (only section2):\n$formatted\n\n";

// Example 4: Adding Properties
echo "4. Adding Properties to JSON\n";
echo str_repeat('-', 50) . "\n";

$json = '{"name": "Alice"}';
echo "Original: $json\n\n";

$modOptions = new ModificationOptions(
    formattingOptions: new FormattingOptions(insertSpaces: true, tabSize: 2)
);

// Add email
$edits = JsoncParser::modify($json, ['email'], 'alice@example.com', $modOptions);
$json = JsoncParser::applyEdits($json, $edits);
echo "After adding email:\n$json\n\n";

// Add age
$edits = JsoncParser::modify($json, ['age'], 30, $modOptions);
$json = JsoncParser::applyEdits($json, $edits);
echo "After adding age:\n$json\n\n";

// Add nested object
$edits = JsoncParser::modify($json, ['address', 'city'], 'New York', $modOptions);
$json = JsoncParser::applyEdits($json, $edits);
echo "After adding nested address:\n$json\n\n";

// Example 5: Updating Properties
echo "5. Updating Properties\n";
echo str_repeat('-', 50) . "\n";

$json = '{
  "name": "Alice",
  "age": 30,
  "city": "NYC"
}';

echo "Original:\n$json\n\n";

// Update age
$edits = JsoncParser::modify($json, ['age'], 31, $modOptions);
$json = JsoncParser::applyEdits($json, $edits);
echo "After updating age to 31:\n$json\n\n";

// Update with null (JSON null, not deletion)
$edits = JsoncParser::modify($json, ['city'], null, $modOptions);
$json = JsoncParser::applyEdits($json, $edits);
echo "After setting city to null:\n$json\n\n";

// Example 6: Deleting Properties
echo "6. Deleting Properties\n";
echo str_repeat('-', 50) . "\n";

$json = '{
  "name": "Alice",
  "age": 31,
  "city": null,
  "temp": "to-delete"
}';

echo "Original:\n$json\n\n";

// Delete a property (use RemoveMarker, not null!)
$edits = JsoncParser::modify($json, ['temp'], RemoveMarker::instance(), $modOptions);
$json = JsoncParser::applyEdits($json, $edits);
echo "After deleting 'temp':\n$json\n\n";

$edits = JsoncParser::modify($json, ['city'], RemoveMarker::instance(), $modOptions);
$json = JsoncParser::applyEdits($json, $edits);
echo "After deleting 'city':\n$json\n\n";

// Example 7: Array Modifications
echo "7. Array Modifications\n";
echo str_repeat('-', 50) . "\n";

$json = '{"items": [1, 2, 3]}';
echo "Original: $json\n\n";

// Replace element at index 1
$edits = JsoncParser::modify($json, ['items', 1], 99, $modOptions);
$json = JsoncParser::applyEdits($json, $edits);
echo "After replacing items[1]:\n$json\n\n";

// Insert at index 0 (using isArrayInsertion)
$insertOptions = new ModificationOptions(
    formattingOptions: $modOptions->formattingOptions,
    isArrayInsertion: true
);
$edits = JsoncParser::modify($json, ['items', 0], 0, $insertOptions);
$json = JsoncParser::applyEdits($json, $edits);
echo "After inserting at items[0]:\n$json\n\n";

// Append to end (index -1)
$edits = JsoncParser::modify($json, ['items', -1], 100, $modOptions);
$json = JsoncParser::applyEdits($json, $edits);
echo "After appending to end:\n$json\n\n";

// Delete array element
$edits = JsoncParser::modify($json, ['items', 0], RemoveMarker::instance(), $modOptions);
$json = JsoncParser::applyEdits($json, $edits);
echo "After deleting items[0]:\n$json\n\n";

// Example 8: Custom Property Ordering
echo "8. Custom Property Insertion Order\n";
echo str_repeat('-', 50) . "\n";

$json = '{
  "zebra": 1,
  "apple": 2,
  "mango": 3
}';

echo "Original (unsorted):\n$json\n\n";

// Add new property at specific position (alphabetically before "mango")
$customOptions = new ModificationOptions(
    formattingOptions: new FormattingOptions(insertSpaces: true, tabSize: 2),
    getInsertionIndex: function (array $properties) {
        // Insert "banana" in alphabetical order
        $properties[] = 'banana';
        sort($properties);
        return array_search('banana', $properties, true);
    }
);

$edits = JsoncParser::modify($json, ['banana'], 4, $customOptions);
$json = JsoncParser::applyEdits($json, $edits);
echo "After adding 'banana' (alphabetically):\n$json\n\n";

// Example 9: Batch Modifications
echo "9. Batch Modifications\n";
echo str_repeat('-', 50) . "\n";

$json = '{}';
echo "Starting with empty object: $json\n\n";

// Apply multiple modifications
$modifications = [
    ['path' => ['name'], 'value' => 'Configuration'],
    ['path' => ['version'], 'value' => '1.0.0'],
    ['path' => ['debug'], 'value' => false],
    ['path' => ['database', 'host'], 'value' => 'localhost'],
    ['path' => ['database', 'port'], 'value' => 5432],
];

foreach ($modifications as $mod) {
    $edits = JsoncParser::modify($json, $mod['path'], $mod['value'], $modOptions);
    $json = JsoncParser::applyEdits($json, $edits);
}

echo "After batch modifications:\n$json\n\n";

// Example 10: Preserving Comments
echo "10. Formatting with Comments Preserved\n";
echo str_repeat('-', 50) . "\n";

$jsonc = '{
  // User configuration
  "name": "Alice",
  /* Email settings */
  "email": "alice@example.com"
}';

echo "Original JSONC:\n$jsonc\n\n";

$options = new FormattingOptions(
    insertSpaces: true,
    tabSize: 4
);

$edits = JsoncParser::format($jsonc, null, $options);
$formatted = JsoncParser::applyEdits($jsonc, $edits);

echo "Formatted (comments preserved):\n$formatted\n\n";

// Example 11: keepLines Option
echo "11. Formatting with keepLines (Minimal Changes)\n";
echo str_repeat('-', 50) . "\n";

$json = '{  "name": "Alice",
"age":    30,  "city":
"NYC"  }';

echo "Original (irregular spacing):\n$json\n\n";

// Without keepLines (default)
$options = new FormattingOptions(insertSpaces: true, tabSize: 2, keepLines: false);
$edits = JsoncParser::format($json, null, $options);
$formatted1 = JsoncParser::applyEdits($json, $edits);
echo "Formatted without keepLines:\n$formatted1\n\n";

// With keepLines (preserve line structure)
$options = new FormattingOptions(insertSpaces: true, tabSize: 2, keepLines: true);
$edits = JsoncParser::format($json, null, $options);
$formatted2 = JsoncParser::applyEdits($json, $edits);
echo "Formatted with keepLines:\n$formatted2\n\n";

// Example 12: Practical Example - Config File Editor
echo "12. Practical Example - Configuration File Editor\n";
echo str_repeat('-', 50) . "\n";

class ConfigEditor
{
    private string $json;
    private ModificationOptions $options;

    public function __construct(string $json)
    {
        $this->json = $json;
        $this->options = new ModificationOptions(
            formattingOptions: new FormattingOptions(
                insertSpaces: true,
                tabSize: 2,
                insertFinalNewline: true
            )
        );
    }

    public function set(array $path, mixed $value): self
    {
        $edits = JsoncParser::modify($this->json, $path, $value, $this->options);
        $this->json = JsoncParser::applyEdits($this->json, $edits);
        return $this;
    }

    public function delete(array $path): self
    {
        $edits = JsoncParser::modify($this->json, $path, RemoveMarker::instance(), $this->options);
        $this->json = JsoncParser::applyEdits($this->json, $edits);
        return $this;
    }

    public function get(array $path): mixed
    {
        $tree = JsoncParser::parseTree($this->json);
        $node = JsoncParser::findNodeAtLocation($tree, $path);
        return $node ? JsoncParser::getNodeValue($node) : null;
    }

    public function format(): self
    {
        $edits = JsoncParser::format($this->json, null, $this->options->formattingOptions);
        $this->json = JsoncParser::applyEdits($this->json, $edits);
        return $this;
    }

    public function toString(): string
    {
        return $this->json;
    }
}

$config = new ConfigEditor('{}');

$config
    ->set(['app', 'name'], 'MyApplication')
    ->set(['app', 'version'], '2.0.0')
    ->set(['database', 'driver'], 'postgresql')
    ->set(['database', 'host'], 'localhost')
    ->set(['database', 'port'], 5432)
    ->set(['features', 'logging'], true)
    ->set(['features', 'caching'], true)
    ->format();

echo "Final configuration:\n";
echo $config->toString();

echo "\nReading value:\n";
echo "Database host: " . $config->get(['database', 'host']) . "\n";

echo "\n=== Examples Complete ===\n";
