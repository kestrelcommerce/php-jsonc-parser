<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kestrel\JsoncParser\JsoncParser;
use Kestrel\JsoncParser\Scanner\SyntaxKind;

echo "=== PHP JSONC Parser - Basic Parsing Examples ===\n\n";

// Example 1: Parse JSON with Comments
echo "1. Parsing JSONC (JSON with Comments)\n";
echo str_repeat('-', 50) . "\n";

$jsonc = '{
  "name": "Alice",
  "age": 30,
  // This is a line comment
  "city": "New York",
  /* This is a
     block comment */
  "hobbies": ["reading", "coding"]
}';

echo "Input JSONC:\n$jsonc\n\n";

$errors = [];
$data = JsoncParser::parse($jsonc, $errors);

echo "Parsed data:\n";
print_r($data);

if (!empty($errors)) {
    echo "\nParse errors:\n";
    foreach ($errors as $error) {
        echo "  - {$error->error->value} at offset {$error->offset}\n";
    }
} else {
    echo "\nNo errors found!\n";
}

echo "\n";

// Example 2: Strip Comments
echo "2. Stripping Comments\n";
echo str_repeat('-', 50) . "\n";

$stripped = JsoncParser::stripComments($jsonc);
echo "Comments removed:\n$stripped\n\n";

$strippedWithSpaces = JsoncParser::stripComments($jsonc, ' ');
echo "Comments replaced with spaces (preserves formatting):\n$strippedWithSpaces\n\n";

// Example 3: Scanner/Tokenizer
echo "3. Scanner - Tokenizing JSON\n";
echo str_repeat('-', 50) . "\n";

$simpleJson = '{"key": "value", "number": 42}';
echo "Input: $simpleJson\n\n";

$scanner = JsoncParser::createScanner($simpleJson);

echo "Tokens:\n";
$tokenCount = 0;
while (($token = $scanner->scan()) !== SyntaxKind::EOF) {
    $tokenCount++;
    $value = $scanner->getTokenValue();
    $offset = $scanner->getTokenOffset();
    echo sprintf(
        "  %2d. %-20s at offset %2d: %s\n",
        $tokenCount,
        $token->name,
        $offset,
        $value !== '' ? "'$value'" : '(empty)'
    );
}

echo "\n";

// Example 4: Parse Tree (AST)
echo "4. Building Abstract Syntax Tree\n";
echo str_repeat('-', 50) . "\n";

$json = '{"user": {"name": "Bob", "age": 25}, "active": true}';
echo "Input: $json\n\n";

$tree = JsoncParser::parseTree($json);

if ($tree) {
    echo "Tree structure:\n";
    echo "Root type: {$tree->type->value}\n";
    echo "Root has " . count($tree->children) . " properties\n\n";

    foreach ($tree->children as $i => $property) {
        $keyNode = $property->children[0];
        $valueNode = $property->children[1];

        echo "Property " . ($i + 1) . ":\n";
        echo "  Key: {$keyNode->value}\n";
        echo "  Value type: {$valueNode->type->value}\n";

        if ($valueNode->type->value === 'object') {
            echo "  Value has " . count($valueNode->children) . " nested properties\n";
        } else {
            echo "  Value: " . json_encode($valueNode->value) . "\n";
        }
        echo "\n";
    }
}

// Example 5: Navigation
echo "5. Navigating the AST\n";
echo str_repeat('-', 50) . "\n";

$json = '{"config": {"database": {"host": "localhost", "port": 5432}}}';
echo "Input: $json\n\n";

$tree = JsoncParser::parseTree($json);

// Find node by path
$hostNode = JsoncParser::findNodeAtLocation($tree, ['config', 'database', 'host']);
if ($hostNode) {
    echo "Found 'config.database.host': " . JsoncParser::getNodeValue($hostNode) . "\n";
}

$portNode = JsoncParser::findNodeAtLocation($tree, ['config', 'database', 'port']);
if ($portNode) {
    echo "Found 'config.database.port': " . JsoncParser::getNodeValue($portNode) . "\n";
    echo "Node path: " . implode('.', JsoncParser::getNodePath($portNode)) . "\n";
}

echo "\n";

// Find node by offset (position in string)
$offset = strpos($json, 'localhost');
$nodeAtOffset = JsoncParser::findNodeAtOffset($tree, $offset);
if ($nodeAtOffset) {
    echo "Node at offset $offset:\n";
    echo "  Type: {$nodeAtOffset->type->value}\n";
    echo "  Value: " . json_encode(JsoncParser::getNodeValue($nodeAtOffset)) . "\n";
    echo "  Path: " . implode('.', JsoncParser::getNodePath($nodeAtOffset)) . "\n";
}

echo "\n";

// Get location at position
$location = JsoncParser::getLocation($json, $offset);
echo "Location at offset $offset:\n";
echo "  Path: " . implode('.', $location->path) . "\n";
echo "  Matches 'config.*.host': " . ($location->matches(['config', '*', 'host']) ? 'Yes' : 'No') . "\n";
echo "  Matches 'config.**.host': " . ($location->matches(['config', '**', 'host']) ? 'Yes' : 'No') . "\n";

echo "\n";

// Example 6: Error Handling
echo "6. Error Handling (Fault Tolerant)\n";
echo str_repeat('-', 50) . "\n";

$invalidJson = '{
  "name": "Alice",
  "age": 30,
  "invalid": ,
  "another": true
}';

echo "Input with error:\n$invalidJson\n\n";

$errors = [];
$data = JsoncParser::parse($invalidJson, $errors);

echo "Parsed data (with recovery):\n";
print_r($data);

echo "\nErrors found:\n";
foreach ($errors as $error) {
    echo "  - Error: {$error->error->value}\n";
    echo "    Offset: {$error->offset}\n";
    echo "    Length: {$error->length}\n";
}

echo "\n";

// Example 7: Parsing Options
echo "7. Parse Options\n";
echo str_repeat('-', 50) . "\n";

use Kestrel\JsoncParser\Parser\ParseOptions;

$jsonWithComments = '{"key": "value" /* comment */}';
echo "Input: $jsonWithComments\n\n";

// Allow comments (default)
$data1 = JsoncParser::parse($jsonWithComments, $errors);
echo "With comments allowed: ";
print_r($data1);

// Disallow comments
$errors = [];
$data2 = JsoncParser::parse($jsonWithComments, $errors, new ParseOptions(
    disallowComments: true
));
echo "\nWith comments disallowed:\n";
echo "  Errors: " . count($errors) . "\n";

// Trailing comma
$jsonWithTrailingComma = '{"a": 1, "b": 2,}';
echo "\nInput: $jsonWithTrailingComma\n";

$errors = [];
$data3 = JsoncParser::parse($jsonWithTrailingComma, $errors, new ParseOptions(
    allowTrailingComma: true
));
echo "With trailing comma allowed: ";
print_r($data3);
echo "Errors: " . count($errors) . "\n";

echo "\n=== Examples Complete ===\n";
