<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kestrel\JsoncParser\JsoncParser;
use Kestrel\JsoncParser\Parser\JsonVisitor;

echo "=== PHP JSONC Parser - Visitor Pattern Examples ===\n\n";

// Example 1: Event Logger Visitor
echo "1. Event Logger - Tracking All Parse Events\n";
echo str_repeat('-', 50) . "\n";

class EventLoggerVisitor implements JsonVisitor
{
    private array $events = [];

    public function onObjectBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        $this->events[] = "Object started at offset $offset";
        return null;
    }

    public function onObjectEnd(int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        $this->events[] = "Object ended at offset $offset";
        return null;
    }

    public function onObjectProperty(string $property, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        $this->events[] = "Property '$property' at offset $offset";
        return null;
    }

    public function onArrayBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        $this->events[] = "Array started at offset $offset";
        return null;
    }

    public function onArrayEnd(int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        $this->events[] = "Array ended at offset $offset";
        return null;
    }

    public function onLiteralValue(mixed $value, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        $valueStr = json_encode($value);
        $this->events[] = "Literal value $valueStr at offset $offset";
        return null;
    }

    public function onSeparator(string $character, int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        $this->events[] = "Separator '$character' at offset $offset";
        return null;
    }

    public function onComment(int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        $this->events[] = "Comment at offset $offset (length $length)";
        return null;
    }

    public function onError(int $error, int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        $this->events[] = "Error $error at offset $offset";
        return null;
    }

    public function getEvents(): array
    {
        return $this->events;
    }
}

$json = '{"name": "Alice", "age": 30, "hobbies": ["reading", "coding"]}';
echo "Input: $json\n\n";

$logger = new EventLoggerVisitor();
JsoncParser::visit($json, $logger);

echo "Events logged:\n";
foreach ($logger->getEvents() as $i => $event) {
    echo sprintf("%2d. %s\n", $i + 1, $event);
}

echo "\n";

// Example 2: Property Counter
echo "2. Property Counter - Counting Object Properties\n";
echo str_repeat('-', 50) . "\n";

class PropertyCounterVisitor implements JsonVisitor
{
    private int $propertyCount = 0;
    private int $depth = 0;

    public function onObjectBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        $this->depth++;
        return null;
    }

    public function onObjectEnd(int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        $this->depth--;
        return null;
    }

    public function onObjectProperty(string $property, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        $this->propertyCount++;
        $indent = str_repeat('  ', $this->depth);
        $path = implode('.', $pathSupplier());
        echo "{$indent}Property: $path\n";
        return null;
    }

    public function getCount(): int
    {
        return $this->propertyCount;
    }

    // Required but unused methods
    public function onArrayBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        return null;
    }
    public function onArrayEnd(int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
    public function onLiteralValue(mixed $value, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        return null;
    }
    public function onSeparator(string $character, int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
    public function onComment(int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
    public function onError(int $error, int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
}

$json = '{
  "user": {
    "name": "Bob",
    "email": "bob@example.com",
    "settings": {
      "theme": "dark",
      "notifications": true
    }
  },
  "timestamp": 1234567890
}';

echo "Input:\n$json\n\n";

$counter = new PropertyCounterVisitor();
JsoncParser::visit($json, $counter);

echo "\nTotal properties found: {$counter->getCount()}\n\n";

// Example 3: Data Extractor - Extract Specific Values
echo "3. Data Extractor - Finding Specific Values\n";
echo str_repeat('-', 50) . "\n";

class EmailExtractorVisitor implements JsonVisitor
{
    private array $emails = [];

    public function onObjectProperty(string $property, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        // Track when we enter an "email" property
        return null;
    }

    public function onLiteralValue(mixed $value, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        $path = $pathSupplier();
        $lastSegment = end($path);

        // Check if this is an email field
        if ($lastSegment === 'email' && is_string($value)) {
            $this->emails[] = [
                'path' => implode('.', $path),
                'email' => $value,
                'line' => $startLine
            ];
        }

        return null;
    }

    public function getEmails(): array
    {
        return $this->emails;
    }

    // Required methods
    public function onObjectBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        return null;
    }
    public function onObjectEnd(int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
    public function onArrayBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        return null;
    }
    public function onArrayEnd(int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
    public function onSeparator(string $character, int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
    public function onComment(int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
    public function onError(int $error, int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
}

$json = '{
  "users": [
    {"name": "Alice", "email": "alice@example.com"},
    {"name": "Bob", "email": "bob@example.com"}
  ],
  "admin": {
    "email": "admin@example.com"
  }
}';

echo "Input:\n$json\n\n";

$extractor = new EmailExtractorVisitor();
JsoncParser::visit($json, $extractor);

echo "Emails found:\n";
foreach ($extractor->getEmails() as $info) {
    echo "  - {$info['path']}: {$info['email']} (line {$info['line']})\n";
}

echo "\n";

// Example 4: Validator - Check Data Structure
echo "4. Validator - Ensuring Required Fields\n";
echo str_repeat('-', 50) . "\n";

class RequiredFieldsValidator implements JsonVisitor
{
    private array $requiredFields;
    private array $foundFields = [];
    private array $missingFields = [];

    public function __construct(array $requiredFields)
    {
        $this->requiredFields = $requiredFields;
    }

    public function onObjectProperty(string $property, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        $path = implode('.', $pathSupplier());
        $this->foundFields[] = $path;
        return null;
    }

    public function validate(): array
    {
        foreach ($this->requiredFields as $required) {
            if (!in_array($required, $this->foundFields, true)) {
                $this->missingFields[] = $required;
            }
        }
        return $this->missingFields;
    }

    // Required methods
    public function onObjectBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        return null;
    }
    public function onObjectEnd(int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
    public function onArrayBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        return null;
    }
    public function onArrayEnd(int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
    public function onLiteralValue(mixed $value, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        return null;
    }
    public function onSeparator(string $character, int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
    public function onComment(int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
    public function onError(int $error, int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
}

$json = '{
  "name": "MyApp",
  "version": "1.0.0",
  "author": "Alice"
}';

echo "Input:\n$json\n\n";

$requiredFields = ['name', 'version', 'author', 'license'];
$validator = new RequiredFieldsValidator($requiredFields);
JsoncParser::visit($json, $validator);

$missing = $validator->validate();

echo "Required fields: " . implode(', ', $requiredFields) . "\n";
if (empty($missing)) {
    echo "✓ All required fields present\n";
} else {
    echo "✗ Missing fields: " . implode(', ', $missing) . "\n";
}

echo "\n";

// Example 5: Early Termination
echo "5. Early Termination - Stopping Parsing Early\n";
echo str_repeat('-', 50) . "\n";

class FirstValueFinder implements JsonVisitor
{
    private mixed $firstValue = null;
    private bool $found = false;

    public function onLiteralValue(mixed $value, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        if (!$this->found) {
            $this->firstValue = $value;
            $this->found = true;
            // Return true to stop parsing
            return true;
        }
        return null;
    }

    public function getFirstValue(): mixed
    {
        return $this->firstValue;
    }

    // Required methods
    public function onObjectBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        return null;
    }
    public function onObjectEnd(int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
    public function onObjectProperty(string $property, int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        return null;
    }
    public function onArrayBegin(int $offset, int $length, int $startLine, int $startCharacter, \Closure $pathSupplier): bool|null
    {
        return null;
    }
    public function onArrayEnd(int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
    public function onSeparator(string $character, int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
    public function onComment(int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
    public function onError(int $error, int $offset, int $length, int $startLine, int $startCharacter): bool|null
    {
        return null;
    }
}

$largeJson = '{"a": 1, "b": 2, "c": 3, "d": 4, "e": 5}';
echo "Input: $largeJson\n\n";

$finder = new FirstValueFinder();
JsoncParser::visit($largeJson, $finder);

echo "First value found: " . json_encode($finder->getFirstValue()) . "\n";
echo "(Parsing stopped early after finding first value)\n";

echo "\n=== Examples Complete ===\n";
