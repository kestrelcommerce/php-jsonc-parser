# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[0.1.0]: https://github.com/kestrelwp/php-jsonc-parser/releases/tag/v0.1.0
