<?php

declare(strict_types=1);

use Kestrel\JsoncParser\JsoncParser;
use Kestrel\JsoncParser\Scanner\SyntaxKind;
use Kestrel\JsoncParser\Scanner\ScanError;
use Kestrel\JsoncParser\Parser\ParseOptions;
use Kestrel\JsoncParser\Parser\Node;
use Kestrel\JsoncParser\Parser\JsonVisitor;
use Kestrel\JsoncParser\Parser\ParseError;
use Kestrel\JsoncParser\Edit\Edit;

/*
|--------------------------------------------------------------------------
| Test Helpers
|--------------------------------------------------------------------------
|
| Helper functions for Pest tests, ported from the TypeScript test suite
|
*/

/**
 * Assert that scanning the given text produces the expected sequence of token kinds
 */
function assertKinds(string $text, SyntaxKind ...$expected): void
{
    $scanner = JsoncParser::createScanner($text);
    $actual = [];

    while (($kind = $scanner->scan()) !== SyntaxKind::EOF) {
        $actual[] = $kind;
        expect($scanner->getTokenError())->toBe(ScanError::None, "Unexpected scan error in: {$text}");
    }

    expect($actual)->toBe(array_values($expected));
}

/**
 * Assert that parsing the given input produces the expected value with no errors
 */
function assertValidParse(string $input, mixed $expected, ?ParseOptions $options = null): void
{
    $errors = [];
    $actual = JsoncParser::parse($input, $errors, $options);

    expect($errors)->toBeEmpty("Expected no parse errors but got: " . json_encode($errors));
    expect($actual)->toEqual($expected);
}

/**
 * Assert that parsing the given input produces the expected value but WITH errors
 */
function assertInvalidParse(string $input, mixed $expected, ?ParseOptions $options = null): void
{
    $errors = [];
    $actual = JsoncParser::parse($input, $errors, $options);

    expect($errors)->not->toBeEmpty("Expected parse errors but got none");
    expect($actual)->toEqual($expected);
}

/**
 * Assert that parsing the given input as a tree produces the expected node structure
 *
 * @param array<ParseError> $expectedErrors
 */
function assertTree(string $input, mixed $expected, array $expectedErrors = []): void
{
    $errors = [];
    $actual = JsoncParser::parseTree($input, $errors);

    if ($expectedErrors !== []) {
        expect(count($errors))->toBe(count($expectedErrors));
    }

    // Verify parent references
    $checkParent = function (?Node $node) use (&$checkParent): void {
        if ($node !== null && $node->children !== null && $node->children !== []) {
            foreach ($node->children as $child) {
                expect($child->parent)->toBe($node);
                $checkParent($child);
            }
        }
    };

    $checkParent($actual);

    // Compare structure (simplified comparison)
    expect($actual)->toEqual($expected);
}

/**
 * Assert that visiting produces the expected result
 */
function assertVisit(string $input, JsonVisitor $visitor, mixed $expected, ?ParseOptions $options = null): void
{
    $actual = JsoncParser::visit($input, $visitor, $options);
    expect($actual)->toEqual($expected);
}

/**
 * Apply edits and assert the result
 *
 * @param array<Edit> $edits
 */
function assertEdit(string $input, array $edits, string $expected): void
{
    $actual = JsoncParser::applyEdits($input, $edits);
    expect($actual)->toBe($expected);
}
