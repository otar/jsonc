# otar/jsonc

A production-ready PHP library for parsing [JSONC (JSON with Comments)](https://jsonc.org/) format with drop-in compatibility for `json_decode()`.

[![CI](https://github.com/otar/jsonc/actions/workflows/ci.yml/badge.svg)](https://github.com/otar/jsonc/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/otar/jsonc/branch/main/graph/badge.svg)](https://codecov.io/gh/otar/jsonc)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

## Features

- **Single-line comments** (`//`) and **multi-line comments** (`/* */`)
- **Trailing commas** in objects and arrays
- **Drop-in replacement** for `json_decode()` — same signature, same error behavior, verified by a differential test suite
- **Edge case handling**: Preserves strings with comment syntax, escaped characters, Unicode
- **Strict by default**: malformed input (raw control bytes, unclosed strings or comments) is rejected like native `json_decode()` does — never silently sanitized
- **Fast**: plain JSON takes a native `json_decode()` fast path; JSONC goes through a single-pass scanner
- **Zero dependencies**: Uses native PHP JSON extension
- **Well tested**: 150+ tests with 100% code coverage, PHPStan at max level

## Installation

```bash
composer require otar/jsonc
```

## Usage

### Global Function

```php
// Use global function wrapper
$data = jsonc_decode($jsonc, true);

// Check for errors using native PHP functions
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_last_error_msg();
}
```

### Basic Usage

```php
use Otar\JSONC;

$jsonc = '{
    // This is a comment
    "name": "John Doe",
    "age": 30,
    "hobbies": [
        "reading",
        "coding", // Inline comment
    ],
}';

$data = JSONC::decode($jsonc, true);
// ['name' => 'John Doe', 'age' => 30, 'hobbies' => ['reading', 'coding']]
```

### Parse to JSON String

```php
use Otar\JSONC;

$jsonc = '{/* comment */"key": "value",}';
$json = JSONC::parse($jsonc);
// '{ "key": "value"}' — comments become a space, trailing commas are dropped

// Now you can use with standard json_decode
$data = json_decode($json, true);
```

## API Reference

### `JSONC::decode()`

Decodes a JSONC string. Drop-in replacement for `json_decode()`.

```php
public static function decode(
    string $jsonc,
    ?bool $associative = null,
    int $depth = 512,
    int $flags = 0
): mixed
```

### `JSONC::parse()`

Parses JSONC string and returns cleaned JSON string (without comments and trailing commas).

```php
public static function parse(string $jsonc): string
```

Throws `Otar\JsoncSyntaxException` when the input ends inside an unclosed string literal or block comment — it never returns invalid JSON.

### `jsonc_decode()`

Global function alias for `JSONC::decode()`.

## Error Handling

`decode()` follows `json_decode()` exactly: invalid input returns `null` and sets the native error state.

```php
$invalidJsonc = '{invalid json}';
$result = JSONC::decode($invalidJsonc);

if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
    echo 'Parse error: ' . json_last_error_msg();
}
```

With `JSON_THROW_ON_ERROR` it throws instead. Unclosed JSONC constructs surface as `Otar\JsoncSyntaxException` — a `JsonException` subclass that reports where the construct opened:

```php
use Otar\JsoncSyntaxException;

try {
    JSONC::decode('{"a": 1, /* unterminated', flags: JSON_THROW_ON_ERROR);
} catch (JsoncSyntaxException $e) {
    echo $e->getMessage(); // Unclosed block comment starting at offset 9
    $e->getOffset();       // 9
} catch (JsonException $e) {
    // any other JSON syntax error
}
```

`JSONC::parse()` always throws `JsoncSyntaxException` for unclosed constructs, with or without flags.

### Differences from `json_decode()`

- A leading UTF-8 BOM is tolerated and stripped (native `json_decode()` rejects it) — JSONC config files frequently carry one.
- Comments and trailing commas are accepted.
- Everything else matches native behavior, including `json_last_error()` codes.
- `json_last_error()` is only meaningful after `decode()`; `parse()` may probe the input internally, which writes the global JSON error state.

## Requirements

- PHP 8.1 or higher
- JSON extension (enabled by default in PHP)

## Testing

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Static analysis (PHPStan, level max)
composer phpstan

# Micro-benchmark
composer bench
```

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please ensure all tests pass (`composer test`) and maintain code coverage.

## Credits & Related Projects

Inspired by existing JSONC parsers and the need for a robust, production-ready PHP implementation with proper edge case handling.

- [microsoft/node-jsonc-parser](https://github.com/microsoft/node-jsonc-parser) - Official JSONC parser for Node.js
- [VS Code](https://code.visualstudio.com/) - Uses JSONC for configuration files
