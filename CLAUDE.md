# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

PHP 8.4+ port of Microsoft's node-jsonc-parser - a scanner and fault-tolerant parser for JSON with Comments (JSONC). Supports standard JSON plus `//` line comments and `/* */` block comments.

## Commands

```bash
# Run tests
composer test

# Run tests with coverage (minimum 80% required)
composer test-coverage

# Static analysis (PHPStan max level)
composer stan

# Format code (PSR-12 via Laravel Pint)
composer format

# Check formatting without changes
composer format-check
```

Run a single test file:
```bash
./vendor/bin/pest tests/Unit/Scanner/ScannerTest.php
```

Run tests matching a pattern:
```bash
./vendor/bin/pest --filter="parses objects"
```

## Architecture

### Entry Point
`JsoncParser` is the main facade providing static methods for all operations:
- `parse()` - Direct evaluation to PHP arrays/objects
- `parseTree()` - DOM-style AST with `Node` objects
- `visit()` - SAX-style streaming with `JsonVisitor` callbacks
- `format()` - Format with configurable options
- `modify()` - Path-based insert/update/delete operations
- `applyEdits()` - Apply edit operations to text

### Component Structure

```
src/
├── JsoncParser.php          # Facade (public API)
├── Scanner/                 # Tokenization
│   ├── Scanner              # Character-by-character lexer
│   └── SyntaxKind           # Token types enum (17 types)
├── Parser/                  # Parsing logic
│   ├── Parser               # Three parsing modes
│   ├── Node & NodeType      # AST representation
│   └── JsonVisitor          # Visitor interface
├── Format/                  # Formatting
│   └── Formatter            # Configurable pretty-printing
└── Edit/                    # Modification
    ├── Editor               # Insert/update/delete by path
    └── RemoveMarker         # Sentinel for deletion (since null is valid JSON)
```

### Parsing Flow

1. **Scanner** tokenizes input character-by-character, producing `SyntaxKind` tokens
2. **Parser** consumes tokens in one of three modes:
   - Direct evaluation: builds PHP arrays/objects via internal visitor
   - Tree building: creates `Node` AST with parent/child references
   - Visitor pattern: triggers user-provided `JsonVisitor` callbacks
3. **Fault tolerance**: Parser continues on errors, collecting them for reporting

### Key Design Decisions

- `RemoveMarker::instance()` used for deletion since `null` is a valid JSON value
- `ParseOptions` controls: `disallowComments`, `allowTrailingComma`, `allowEmptyContent`
- `Edit` operations are offset-based; use `applyEdits()` to apply them to text
- Node navigation via `findNodeAtLocation()`, `getNodePath()`, `getNodeValue()`

## Testing

Uses Pest PHP with custom assertion helpers in `tests/Pest.php`:
- `assertKinds()` - Verify scanner token sequence
- `assertValidParse()` / `assertInvalidParse()` - Parse verification
- `assertTree()` - AST structure verification
- `assertEdit()` - Edit operation verification
