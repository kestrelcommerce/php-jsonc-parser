# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2026-09-16

### Fixed
- Scanning is linear in the length of the document instead of quadratic. The scanner
  addresses text by character offset, and resolving one against a UTF-8 string means
  walking it from the start, so calling `mb_substr()` once per character made the cost
  grow with the square of the length. The document is now split into characters once
  and indexed. Measured over a 183 KB document, a parse/modify/serialize round trip
  drops from 21.4s to 128ms.

### Added
- Scanner complexity tests, asserting a ratio between two input sizes rather than a
  wall-clock budget so they mean the same thing on any machine.
- Scanner multibyte tests covering offsets, token values, lengths, line and column
  numbers and the token stream across 2-, 3- and 4-byte characters, Greek, Thai,
  Cyrillic, CJK and emoji. Output is unchanged by this release: all 31 storefront
  locale files of a production Shopify theme round-trip byte-identically.

## [0.2.0] - 2026-01-26

### Fixed
- Edits landing at the wrong position when a multibyte character preceded the target.
  `Editor` and `Formatter` measured in bytes while the parser reported character
  offsets; both now agree on characters.

## [0.1.0] - 2026-01-19

### Added
- Initial release
- Scanner/Tokenizer for JSON with Comments (JSONC)
- Three parsing modes:
  - SAX-style visitor pattern with event callbacks
  - DOM-style tree builder with AST nodes
  - Direct evaluation to PHP arrays/objects
- Full support for `//` line comments and `/* */` block comments
- Fault-tolerant parsing with error collection
- JSON formatter with configurable options
- JSON editor for insert, update, and delete operations
- Navigation utilities (findNodeAtLocation, getNodePath, etc.)
- String interning for optimized formatting performance
- PHP 8.4+ compatibility
- Comprehensive test suite with 118 passing tests
- PSR-4 autoloading
- Examples demonstrating core features

[0.3.0]: https://github.com/kestrelcommerce/php-jsonc-parser/releases/tag/0.3.0
[0.2.0]: https://github.com/kestrelcommerce/php-jsonc-parser/releases/tag/0.2.0
[0.1.0]: https://github.com/kestrelwp/php-jsonc-parser/releases/tag/v0.1.0
