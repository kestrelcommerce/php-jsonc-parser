<?php

declare(strict_types=1);

use Kestrel\JsoncParser\Scanner\Scanner;
use Kestrel\JsoncParser\Scanner\SyntaxKind;
use Kestrel\JsoncParser\Scanner\ScanError;

describe('Scanner', function () {
    test('scans tokens', function () {
        $scanner = Scanner::create('{}');
        expect($scanner->scan())->toBe(SyntaxKind::OpenBraceToken);
        expect($scanner->scan())->toBe(SyntaxKind::CloseBraceToken);
        expect($scanner->scan())->toBe(SyntaxKind::EOF);
    });

    test('scans array brackets', function () {
        $scanner = Scanner::create('[]');
        expect($scanner->scan())->toBe(SyntaxKind::OpenBracketToken);
        expect($scanner->scan())->toBe(SyntaxKind::CloseBracketToken);
    });

    test('scans literals', function () {
        $scanner = Scanner::create('true false null');
        expect($scanner->scan())->toBe(SyntaxKind::TrueKeyword);
        expect($scanner->scan())->toBe(SyntaxKind::Trivia);
        expect($scanner->scan())->toBe(SyntaxKind::FalseKeyword);
        expect($scanner->scan())->toBe(SyntaxKind::Trivia);
        expect($scanner->scan())->toBe(SyntaxKind::NullKeyword);
    });

    test('scans string', function () {
        $scanner = Scanner::create('"test"');
        expect($scanner->scan())->toBe(SyntaxKind::StringLiteral);
        expect($scanner->getTokenValue())->toBe('test');
    });

    test('scans string with escaped characters', function () {
        $scanner = Scanner::create('"\\n\\r\\t"');
        expect($scanner->scan())->toBe(SyntaxKind::StringLiteral);
        expect($scanner->getTokenValue())->toBe("\n\r\t");
    });

    test('scans string with unicode escape', function () {
        $scanner = Scanner::create('"\\u0048"');
        expect($scanner->scan())->toBe(SyntaxKind::StringLiteral);
        expect($scanner->getTokenValue())->toBe('H');
    });

    test('scans numbers', function () {
        $scanner = Scanner::create('123');
        expect($scanner->scan())->toBe(SyntaxKind::NumericLiteral);
        expect($scanner->getTokenValue())->toBe('123');
    });

    test('scans negative numbers', function () {
        $scanner = Scanner::create('-123');
        expect($scanner->scan())->toBe(SyntaxKind::NumericLiteral);
        expect($scanner->getTokenValue())->toBe('-123');
    });

    test('scans decimal numbers', function () {
        $scanner = Scanner::create('1.23');
        expect($scanner->scan())->toBe(SyntaxKind::NumericLiteral);
        expect($scanner->getTokenValue())->toBe('1.23');
    });

    test('scans scientific notation', function () {
        $scanner = Scanner::create('1.23e-4');
        expect($scanner->scan())->toBe(SyntaxKind::NumericLiteral);
        expect($scanner->getTokenValue())->toBe('1.23e-4');
    });

    test('scans line comments', function () {
        $scanner = Scanner::create('// comment');
        expect($scanner->scan())->toBe(SyntaxKind::LineCommentTrivia);
        expect($scanner->getTokenValue())->toBe('// comment');
    });

    test('scans block comments', function () {
        $scanner = Scanner::create('/* comment */');
        expect($scanner->scan())->toBe(SyntaxKind::BlockCommentTrivia);
        expect($scanner->getTokenValue())->toBe('/* comment */');
    });

    test('ignores trivia when configured', function () {
        $scanner = Scanner::create('  true  ', true);
        expect($scanner->scan())->toBe(SyntaxKind::TrueKeyword);
        expect($scanner->scan())->toBe(SyntaxKind::EOF);
    });

    test('tracks position', function () {
        $scanner = Scanner::create('{"key": 123}');
        expect($scanner->scan())->toBe(SyntaxKind::OpenBraceToken);
        expect($scanner->getTokenOffset())->toBe(0);
        expect($scanner->scan())->toBe(SyntaxKind::StringLiteral);
        expect($scanner->getTokenOffset())->toBe(1);
        expect($scanner->getTokenValue())->toBe('key');
    });

    test('handles unterminated string', function () {
        $scanner = Scanner::create('"unterminated');
        expect($scanner->scan())->toBe(SyntaxKind::StringLiteral);
        expect($scanner->getTokenError())->toBe(ScanError::UnexpectedEndOfString);
    });
});
