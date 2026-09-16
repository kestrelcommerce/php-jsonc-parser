<?php

declare(strict_types=1);

use Kestrel\JsoncParser\Scanner\Scanner;
use Kestrel\JsoncParser\Scanner\SyntaxKind;

/*
|--------------------------------------------------------------------------
| Scanner complexity
|--------------------------------------------------------------------------
|
| Scanning must cost time proportional to the input, not to its square.
|
| These assert a *ratio* between two input sizes rather than an absolute
| duration, so they mean the same thing on a fast laptop and a slow CI box.
| Quadratic scanning takes ~16x as long for 4x the input; linear scanning
| takes ~4x. The gap is wide enough that a threshold of 8 separates them
| without ever landing on timing noise.
|
*/

/**
 * Build a JSONC document of roughly the requested character count.
 *
 * Shaped like a Shopify theme locale file — nested groups of short keys
 * mapping to sentence-length strings — because that is the payload that
 * exposed this in production.
 */
function localeShapedDocument(int $approximateChars, string $filler = 'Some typical storefront string'): string
{
    $data = [];
    $group = 0;
    $key = 0;

    while (strlen((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) < $approximateChars) {
        for ($i = 0; $i < 50; $i++) {
            $data["group_{$group}"]["key_{$key}"] = "{$filler} number {$key}";
            $key++;
        }
        $group++;
    }

    return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Tokenize the whole document and return the fastest of several runs.
 *
 * The minimum is the least noisy summary of repeated timings: it is the run
 * that got the fewest interruptions, and it cannot be dragged upward by an
 * unrelated process the way a mean can.
 */
function fastestScanMs(string $document, int $runs = 3): float
{
    $best = INF;

    for ($run = 0; $run < $runs; $run++) {
        $scanner = Scanner::create($document);

        $started = hrtime(true);
        while ($scanner->scan() !== SyntaxKind::EOF) {
            // Tokenizing for its own sake; the tokens are not the point here.
        }
        $elapsed = (hrtime(true) - $started) / 1e6;

        $best = min($best, $elapsed);
    }

    return $best;
}

describe('Scanner complexity', function () {
    test('scanning 4x the input costs about 4x the time, not 16x', function () {
        $small = localeShapedDocument(12_000);
        $large = localeShapedDocument(48_000);

        // Guard the premise: the ratio below is only meaningful if the inputs
        // really do differ by ~4x.
        $sizeRatio = mb_strlen($large, 'UTF-8') / mb_strlen($small, 'UTF-8');
        expect($sizeRatio)->toBeGreaterThan(3.5)->toBeLessThan(4.5);

        $timeRatio = fastestScanMs($large) / fastestScanMs($small);

        expect($timeRatio)->toBeLessThan(
            8.0,
            sprintf(
                'Scanning scaled %.1fx for %.1fx the input, which is quadratic, not linear.',
                $timeRatio,
                $sizeRatio,
            ),
        );
    });

    test('multibyte input scales no worse than ASCII', function () {
        // Every character access walks the string when it is indexed by
        // character offset, so a document full of 3-byte characters must not
        // be dramatically worse than an ASCII one of the same length.
        $ascii = localeShapedDocument(24_000);
        $cjk = localeShapedDocument(24_000, '日本語のテキストです');

        $penalty = fastestScanMs($cjk) / fastestScanMs($ascii);

        expect($penalty)->toBeLessThan(
            4.0,
            sprintf('Multibyte input scanned %.1fx slower than ASCII of the same length.', $penalty),
        );
    });
});
