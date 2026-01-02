# otar/jsonc

A production-ready PHP library for parsing JSONC (JSON with Comments) format with drop-in compatibility for `json_decode()`.

[![PHP Version](https://img.shields.io/badge/php-%5E8.0-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-39%20passed-brightgreen)]()
[![Coverage](https://img.shields.io/badge/coverage-97.67%25-brightgreen)]()

## Features

- **Single-line comments** (`//`)
- **Multi-line comments** (`/* */`)
- **Trailing commas** in objects and arrays
- **Drop-in replacement** for `json_decode()`
- **Edge case handling**: Preserves strings with comment syntax, escaped characters, Unicode
- **Error handling**: Uses native `json_last_error()` and `json_last_error_msg()`
- **Zero dependencies**: Uses native PHP JSON extension
- **Well tested**: 39 tests with 97.67% code coverage

## Installation

```bash
composer require otar/jsonc
```

## Usage

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

### Global Function

```php
// Use global function wrapper
$data = jsonc_decode($jsonc, true);

// Check for errors using native PHP functions
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_last_error_msg();
}
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

**Parameters:**
- `$jsonc` - The JSONC string to decode
- `$associative` - When `true`, returns associative arrays instead of objects
- `$depth` - Maximum nesting depth (must be greater than zero)
- `$flags` - Bitmask of JSON decode options

**Returns:** The decoded value. Returns `null` on error.

### `JSONC::parse()`

Parses JSONC string and returns cleaned JSON string.

```php
public static function parse(string $jsonc): string
```

**Parameters:**
- `$jsonc` - The JSONC string to parse

**Returns:** Cleaned JSON string (without comments and trailing commas)

### Global Function

- `jsonc_decode()` - Alias for `JSONC::decode()`

### Error Handling

Use native PHP functions for error handling:

- `json_last_error()` - Returns error code (JSON_ERROR_* constants)
- `json_last_error_msg()` - Returns error message string

## Supported JSONC Features

### Comments

Single-line comments:
```jsonc
{
    // This is a comment
    "key": "value" // Inline comment
}
```

Multi-line comments:
```jsonc
{
    /* This is a
       multi-line comment */
    "key": "value"
}
```

### Trailing Commas

In objects:
```jsonc
{
    "name": "John",
    "age": 30,
}
```

In arrays:
```jsonc
[
    "apple",
    "banana",
    "orange",
]
```

Nested structures:
```jsonc
{
    "person": {
        "name": "John",
        "hobbies": [
            "reading",
            "coding",
        ],
    },
}
```

## Edge Cases Handled

The library correctly handles complex scenarios:

### Comments in Strings
```php
$jsonc = '{"url": "https://example.com", "note": "Use // for comments"}';
$data = JSONC::decode($jsonc, true);
// Preserves "//" inside strings
```

### Escaped Characters
```php
$jsonc = '{"quote": "He said \\"Hello\\"", "path": "C:\\\\Users"}';
$data = JSONC::decode($jsonc, true);
// Correctly handles escaped quotes and backslashes
```

### Commas in Strings
```php
$jsonc = '{"csv": "a,b,c,", "list": ["item,with,comma"]}';
$data = JSONC::decode($jsonc, true);
// Preserves commas inside strings
```

### Unicode Support
```php
$jsonc = '{/* emoji */"icon": "🚀", "text": "你好"}';
$data = JSONC::decode($jsonc, true);
// Full Unicode support
```

## Real-World Examples

### TypeScript Config (tsconfig.json)

```php
$tsconfig = file_get_contents('tsconfig.json');
$config = JSONC::decode($tsconfig, true);

echo $config['compilerOptions']['target']; // "ES2020"
```

### VS Code Settings

```php
$settings = file_get_contents('.vscode/settings.json');
$config = JSONC::decode($settings, true);

echo $config['editor.fontSize']; // 14
```

### Package.json with Comments

```php
$package = file_get_contents('package.json');
$config = JSONC::decode($package, true);

print_r($config['dependencies']);
```

## Error Handling

The library uses native PHP error functions:

```php
$invalidJsonc = '{invalid json}';
$result = JSONC::decode($invalidJsonc);

if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
    echo 'Parse error: ' . json_last_error_msg();
}
```

Error codes are standard PHP JSON constants:
- `JSON_ERROR_NONE` - No error
- `JSON_ERROR_DEPTH` - Maximum stack depth exceeded
- `JSON_ERROR_STATE_MISMATCH` - Invalid or malformed JSON
- `JSON_ERROR_CTRL_CHAR` - Control character error
- `JSON_ERROR_SYNTAX` - Syntax error
- `JSON_ERROR_UTF8` - Malformed UTF-8 characters

## Testing

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage
```

## Requirements

- PHP 8.0 or higher
- JSON extension (enabled by default in PHP)

## How It Works

The library uses a two-pass state-machine approach:

1. **Pass 1: Remove comments** while preserving string content
   - Tracks parser state (Normal, InString, InStringEscape, SingleLineComment, MultiLineComment)
   - Only removes comment syntax outside of strings

2. **Pass 2: Remove trailing commas** while preserving string content
   - Identifies commas followed only by whitespace and closing brackets
   - Preserves commas inside strings

3. **Pass 3: Delegate to native `json_decode()`** for actual parsing
   - Errors automatically tracked via `json_last_error()`

This approach ensures:
- **Performance**: Minimal overhead over native `json_decode()`
- **Correctness**: Handles all edge cases properly
- **Reliability**: Leverages PHP's battle-tested JSON parser

## Implementation Details

### State Machine

Uses PHP 8.0 ENUMs for type-safe state management:

```php
enum ParserState
{
    case Normal;
    case InString;
    case InStringEscape;
    case SingleLineComment;
    case MultiLineComment;
}
```

### Character-by-Character Scanning

The parser scans input character by character, maintaining state to correctly identify:
- When we're inside a string (preserve everything)
- When we're in a comment (remove)
- When a comma is trailing (remove)

## License

MIT License - see [LICENSE](LICENSE) file for details

## Contributing

Contributions are welcome! Please ensure:
- All tests pass (`composer test`)
- Code follows PSR-12 standards
- New features include comprehensive tests
- Maintain >95% code coverage

## Credits

Inspired by existing JSONC parsers and the need for a robust, production-ready PHP implementation with proper edge case handling.

## Related Projects

- [microsoft/node-jsonc-parser](https://github.com/microsoft/node-jsonc-parser) - Official JSONC parser for Node.js
- [VS Code](https://code.visualstudio.com/) - Uses JSONC for configuration files
- [JSONC Specification](https://jsonc.org/) - JSON with Comments format

## Support

For bugs and feature requests, please create an issue on GitHub.
