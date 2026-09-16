<?php

declare(strict_types=1);

use Kestrel\JsoncParser\Scanner\Scanner;
use Kestrel\JsoncParser\Scanner\SyntaxKind;

/*
|--------------------------------------------------------------------------
| Scanner multibyte handling
|--------------------------------------------------------------------------
|
| The scanner reports offsets and lengths in characters, not bytes, and the
| editor relies on that: a mismatch between the two is what once produced
| mangled output when a multibyte character appeared before the edit site.
|
| The editor tests cover that end to end. These cover the scanner directly,
| so a change to how it addresses characters fails here first, next to the
| code that caused it.
|
*/

describe('Scanner multibyte handling', function () {
    test('reports offsets in characters, not bytes', function () {
        // `{` plus a 3-character key in quotes plus `:` puts the value at offset 7,
        // whatever those three characters weigh in bytes.
        $cases = [
            'ascii' => ['abc', 7],
            '2-byte (é)' => ['éée', 7],
            '3-byte (日)' => ['日本語', 7],
            '4-byte (🎉)' => ['🎉🎉🎉', 7],
            'mixed widths' => ['a é 日 🎉', 11],
        ];

        foreach ($cases as $label => [$key, $expectedOffset]) {
            $scanner = Scanner::create('{"'.$key.'":"x"}');
            $scanner->scan(); // {
            $scanner->scan(); // the key
            $scanner->scan(); // :
            $scanner->scan(); // the value

            expect($scanner->getToken())->toBe(SyntaxKind::StringLiteral, $label);
            expect($scanner->getTokenValue())->toBe('x', $label);
            expect($scanner->getTokenOffset())->toBe($expectedOffset, $label);
        }
    });

    test('returns multibyte string values intact', function () {
        $values = [
            'greek' => 'Καλάθι αγορών',
            'cyrillic' => 'Корзина покупок',
            'thai' => 'ตะกร้าสินค้า',
            'japanese' => 'カートに追加する',
            'korean' => '장바구니에 추가',
            'chinese' => '加入購物車',
            'emoji' => 'Sold out 🎉🛒',
            'accented' => 'Panier — livraison incluse',
        ];

        foreach ($values as $label => $value) {
            $scanner = Scanner::create('"'.$value.'"');

            expect($scanner->scan())->toBe(SyntaxKind::StringLiteral, $label);
            expect($scanner->getTokenValue())->toBe($value, $label);
            expect($scanner->getTokenLength())->toBe(mb_strlen($value, 'UTF-8') + 2, $label);
        }
    });

    test('counts lines and columns in characters across multibyte content', function () {
        $json = "{\n  \"日本語\": \"値\",\n  \"target\": 1\n}";

        $scanner = Scanner::create($json, ignoreTrivia: true);
        while ($scanner->scan() !== SyntaxKind::EOF) {
            if ($scanner->getTokenValue() === 'target') {
                break;
            }
        }

        expect($scanner->getTokenStartLine())->toBe(2);
        // Two spaces of indent, so the quote opening "target" is character 2.
        expect($scanner->getTokenStartCharacter())->toBe(2);
    });

    test('scans a multibyte document to the same tokens as its ascii twin', function () {
        // Same structure, same character count, different byte widths: the token
        // stream must not notice the difference.
        $ascii = '{"aaa":"bbb","ccc":[1,2,3]}';
        $wide = '{"日本語":"한국어","中文字":[1,2,3]}';

        $kindsOf = function (string $json): array {
            $scanner = Scanner::create($json);
            $kinds = [];
            while (($kind = $scanner->scan()) !== SyntaxKind::EOF) {
                $kinds[] = $kind;
            }

            return $kinds;
        };

        expect($kindsOf($wide))->toBe($kindsOf($ascii));
    });

    test('handles a multibyte character split across a comment boundary', function () {
        $json = "{\n  // 日本語のコメント 🎉\n  \"key\": \"値\"\n}";

        $scanner = Scanner::create($json, ignoreTrivia: true);
        $scanner->scan(); // {

        expect($scanner->scan())->toBe(SyntaxKind::StringLiteral);
        expect($scanner->getTokenValue())->toBe('key');
    });
});
