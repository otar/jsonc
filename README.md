# otar/jsonc

A production-ready PHP library for parsing JSONC (JSON with Comments) format with drop-in compatibility for `json_decode()`.

[![CI](https://github.com/otar/jsonc/actions/workflows/ci.yml/badge.svg)](https://github.com/otar/jsonc/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/otar/jsonc/branch/main/graph/badge.svg)](https://codecov.io/gh/otar/jsonc)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

## Features

- **Single-line comments** (`//`) and **multi-line comments** (`/* */`)
- **Trailing commas** in objects and arrays
- **Drop-in replacement** for `json_decode()`
- **Edge case handling**: Preserves strings with comment syntax, escaped characters, Unicode
- **Security hardening**: UTF-8 BOM removal, null byte prevention, unclosed string/comment validation
- **Zero dependencies**: Uses native PHP JSON extension
- **Well tested**: 100+ tests with 100% code coverage

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
// '{"key": "value"}'

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

### `jsonc_decode()`

Global function alias for `JSONC::decode()`.

## Error Handling

The library uses native PHP error functions:

```php
$invalidJsonc = '{invalid json}';
$result = JSONC::decode($invalidJsonc);

if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
    echo 'Parse error: ' . json_last_error_msg();
}
```

## Requirements

- PHP 8.1 or higher
- JSON extension (enabled by default in PHP)

## Testing

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage
```

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please ensure all tests pass (`composer test`) and maintain code coverage.

## Credits & Related Projects

Inspired by existing JSONC parsers and the need for a robust, production-ready PHP implementation with proper edge case handling.

- [microsoft/node-jsonc-parser](https://github.com/microsoft/node-jsonc-parser) - Official JSONC parser for Node.js
- [VS Code](https://code.visualstudio.com/) - Uses JSONC for configuration files
