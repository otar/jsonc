<?php

declare(strict_types=1);

namespace Otar\Tests;

use Otar\JSONC;
use PHPUnit\Framework\TestCase;

class JSONCTest extends TestCase
{
    /**
     * Test basic single-line comment removal
     */
    public function testSingleLineComments(): void
    {
        $jsonc = '{
            // This is a comment
            "key": "value" // Inline comment
        }';

        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Test multi-line comment removal
     */
    public function testMultiLineComments(): void
    {
        $jsonc = '{
            /* This is a
               multi-line comment */
            "key": "value" /* inline block */
        }';

        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Test trailing comma in object
     */
    public function testTrailingCommaInObject(): void
    {
        $jsonc = '{"a": 1, "b": 2,}';
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }

    /**
     * Test trailing comma in array
     */
    public function testTrailingCommaInArray(): void
    {
        $jsonc = '[1, 2, 3,]';
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals([1, 2, 3], $result);
    }

    /**
     * Test nested structures with trailing commas
     */
    public function testNestedTrailingCommas(): void
    {
        $jsonc = '{
            "arr": [1, 2,],
            "obj": {"x": 1,},
        }';

        $result = JSONC::decode($jsonc, true);
        $expected = [
            'arr' => [1, 2],
            'obj' => ['x' => 1]
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test comment-like syntax inside strings (should be preserved)
     */
    public function testCommentSyntaxInStrings(): void
    {
        $jsonc = '{
            "url": "https://example.com",
            "comment": "This is // not a comment",
            "block": "This is /* not a comment */"
        }';

        $result = JSONC::decode($jsonc, true);
        $this->assertEquals('https://example.com', $result['url']);
        $this->assertEquals('This is // not a comment', $result['comment']);
        $this->assertEquals('This is /* not a comment */', $result['block']);
    }

    /**
     * Test escaped quotes in strings
     */
    public function testEscapedQuotesInStrings(): void
    {
        $jsonc = '{
            "quote": "He said \"Hello\"",
            "backslash": "Path: C:\\\\Users\\\\file.txt"
        }';

        $result = JSONC::decode($jsonc, true);
        $this->assertEquals('He said "Hello"', $result['quote']);
        $this->assertEquals('Path: C:\\Users\\file.txt', $result['backslash']);
    }

    /**
     * Test commas in strings (should be preserved)
     */
    public function testCommasInStrings(): void
    {
        $jsonc = '{
            "csv": "a,b,c,",
            "list": ["item,with,comma"]
        }';

        $result = JSONC::decode($jsonc, true);
        $this->assertEquals('a,b,c,', $result['csv']);
        $this->assertEquals(['item,with,comma'], $result['list']);
    }

    /**
     * Test closing brackets in strings (should be preserved)
     */
    public function testClosingBracketsInStrings(): void
    {
        $jsonc = '{
            "brackets": "array[]",
            "braces": "object{}"
        }';

        $result = JSONC::decode($jsonc, true);
        $this->assertEquals('array[]', $result['brackets']);
        $this->assertEquals('object{}', $result['braces']);
    }

    /**
     * Test Unicode characters
     */
    public function testUnicodeCharacters(): void
    {
        $jsonc = '{
            // Unicode test
            "emoji": "🚀",
            "chinese": "你好",
            "arabic": "مرحبا"
        }';

        $result = JSONC::decode($jsonc, true);
        $this->assertEquals('🚀', $result['emoji']);
        $this->assertEquals('你好', $result['chinese']);
        $this->assertEquals('مرحبا', $result['arabic']);
    }

    /**
     * Test escaped Unicode
     */
    public function testEscapedUnicode(): void
    {
        $jsonc = '{"unicode": "\\u0048\\u0065\\u006C\\u006C\\u006F"}';
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals('Hello', $result['unicode']);
    }

    /**
     * Test empty structures
     */
    public function testEmptyStructures(): void
    {
        $this->assertEquals([], JSONC::decode('[]', true));
        $this->assertEquals([], JSONC::decode('{}', true));
        $this->assertEquals([], JSONC::decode('[/* comment */]', true));
        $this->assertEquals([], JSONC::decode('{/* comment */}', true));
    }

    /**
     * Test deeply nested structure
     */
    public function testDeeplyNestedStructure(): void
    {
        $jsonc = '{
            // Config file
            "database": {
                "host": "localhost", // DB host
                "port": 5432,
                "credentials": {
                    "username": "admin",
                    "password": "secret",
                },
            },
            "features": [
                "auth", // Authentication
                "logging", // Logging system
            ],
        }';

        $result = JSONC::decode($jsonc, true);

        $this->assertEquals('localhost', $result['database']['host']);
        $this->assertEquals('admin', $result['database']['credentials']['username']);
        $this->assertCount(2, $result['features']);
    }

    /**
     * Test mixed comment types
     */
    public function testMixedComments(): void
    {
        $jsonc = '{
            /* Block comment */
            "a": 1, // Line comment
            /* Another */ "b": 2 // End
        }';

        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }

    /**
     * Test parse() method returns valid JSON string
     */
    public function testParseReturnsValidJSON(): void
    {
        $jsonc = '{/* comment */"key": "value",}';
        $json = JSONC::parse($jsonc);

        // Should be valid JSON
        $this->assertNotNull(json_decode($json));

        // Should have no comments
        $this->assertStringNotContainsString('//', $json);
        $this->assertStringNotContainsString('/*', $json);
    }

    /**
     * Test invalid JSON returns null
     */
    public function testInvalidJSONReturnsNull(): void
    {
        $jsonc = '{invalid json}';
        $result = JSONC::decode($jsonc);

        $this->assertNull($result);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Test json_last_error() is set on error
     */
    public function testJsonLastError(): void
    {
        JSONC::decode('{invalid}');
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());

        // Valid JSON should reset error
        JSONC::decode('{"valid": true}');
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Test global function jsonc_decode()
     */
    public function testGlobalFunctionJsoncDecode(): void
    {
        $jsonc = '{/* test */"key": "value"}';
        $result = jsonc_decode($jsonc, true);

        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Test global function error handling
     */
    public function testGlobalFunctionErrorHandling(): void
    {
        jsonc_decode('{invalid}');
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Test associative parameter
     */
    public function testAssociativeParameter(): void
    {
        $jsonc = '{"key": "value"}';

        // Associative = true
        $resultArray = JSONC::decode($jsonc, true);
        $this->assertIsArray($resultArray);

        // Associative = false
        $resultObject = JSONC::decode($jsonc, false);
        $this->assertIsObject($resultObject);
    }

    /**
     * Test depth parameter
     */
    public function testDepthParameter(): void
    {
        $jsonc = '{"a":{"b":{"c":{"d":{"e":"deep"}}}}}';

        // Should succeed with sufficient depth
        $result = JSONC::decode($jsonc, true, 10);
        $this->assertNotNull($result);

        // Should fail with insufficient depth
        $result = JSONC::decode($jsonc, true, 2);
        $this->assertNull($result);
        $this->assertEquals(JSON_ERROR_DEPTH, json_last_error());
    }

    /**
     * Test flags parameter
     */
    public function testFlagsParameter(): void
    {
        $jsonc = '{"number": 123456789012345678901234567890}';

        // With BIGINT_AS_STRING flag
        $result = JSONC::decode($jsonc, true, 512, JSON_BIGINT_AS_STRING);
        $this->assertIsString($result['number']);
    }

    /**
     * Test whitespace preservation in strings
     */
    public function testWhitespacePreservation(): void
    {
        $jsonc = '{
            "multiline": "line1\\nline2",
            "tabs": "a\\tb\\tc"
        }';

        $result = JSONC::decode($jsonc, true);
        $this->assertStringContainsString("\n", $result['multiline']);
        $this->assertStringContainsString("\t", $result['tabs']);
    }

    /**
     * Test real-world TypeScript config example
     */
    public function testTSConfigExample(): void
    {
        $jsonc = '{
            // TypeScript configuration
            "compilerOptions": {
                "target": "ES2020",
                "module": "commonjs",
                "strict": true,
                "esModuleInterop": true,
            },
            "include": [
                "src/**/*",
            ],
            "exclude": [
                "node_modules",
                "dist",
            ],
        }';

        $result = JSONC::decode($jsonc, true);

        $this->assertEquals('ES2020', $result['compilerOptions']['target']);
        $this->assertTrue($result['compilerOptions']['strict']);
        $this->assertCount(1, $result['include']);
        $this->assertCount(2, $result['exclude']);
    }

    /**
     * Test real-world VS Code settings example
     */
    public function testVSCodeSettingsExample(): void
    {
        $jsonc = '{
            // Editor settings
            "editor.fontSize": 14,
            "editor.tabSize": 2,
            "files.exclude": {
                "**/.git": true,
                "**/.DS_Store": true,
            },
        }';

        $result = JSONC::decode($jsonc, true);

        $this->assertEquals(14, $result['editor.fontSize']);
        $this->assertTrue($result['files.exclude']['**/.git']);
    }

    /**
     * Test comments at start and end
     */
    public function testCommentsAtStartAndEnd(): void
    {
        $jsonc = '// Start comment
        {
            "key": "value"
        }
        // End comment';

        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Test comments between elements
     */
    public function testCommentsBetweenElements(): void
    {
        $jsonc = '{
            "a": 1
            // Comment between
            ,
            "b": 2
        }';

        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }

    /**
     * Test Windows carriage returns
     */
    public function testCarriageReturns(): void
    {
        $jsonc = "{\r\n// Windows line ending\r\n\"key\": \"value\"\r\n}";
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Test trailing comma with lots of whitespace
     */
    public function testTrailingCommaWithWhitespace(): void
    {
        $jsonc = '{
            "a": 1,
            "b": 2,

        }';

        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }

    /**
     * Test non-trailing comma is preserved
     */
    public function testNonTrailingCommaPreserved(): void
    {
        $jsonc = '{"a": 1, "b": 2}';
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }

    /**
     * Test multiple trailing commas (should fail)
     */
    public function testMultipleTrailingCommas(): void
    {
        $jsonc = '[1, 2,,]';
        $result = JSONC::decode($jsonc, true);

        // Should handle gracefully (json_decode will handle the error)
        $this->assertNull($result);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Test comment in the middle of array
     */
    public function testCommentInArray(): void
    {
        $jsonc = '[
            1,
            // Middle comment
            2,
            3
        ]';

        $result = JSONC::decode($jsonc, true);
        $this->assertEquals([1, 2, 3], $result);
    }

    /**
     * Test complex nested comments and trailing commas
     */
    public function testComplexNesting(): void
    {
        $jsonc = '{
            // Top level comment
            "users": [
                {
                    "name": "John", // User name
                    "roles": ["admin", "user",], // Roles with trailing comma
                },
                {
                    "name": "Jane",
                    "roles": ["user"],
                }, // Trailing comma in array
            ],
            /* Settings block */
            "settings": {
                "theme": "dark",
                "notifications": true,
            },
        }';

        $result = JSONC::decode($jsonc, true);

        $this->assertCount(2, $result['users']);
        $this->assertEquals('John', $result['users'][0]['name']);
        $this->assertCount(2, $result['users'][0]['roles']);
        $this->assertEquals('dark', $result['settings']['theme']);
    }

    /**
     * Test boolean values
     */
    public function testBooleanValues(): void
    {
        $jsonc = '{
            // Boolean test
            "enabled": true,
            "disabled": false,
        }';

        $result = JSONC::decode($jsonc, true);
        $this->assertTrue($result['enabled']);
        $this->assertFalse($result['disabled']);
    }

    /**
     * Test null value
     */
    public function testNullValue(): void
    {
        $jsonc = '{
            // Null test
            "value": null,
        }';

        $result = JSONC::decode($jsonc, true);
        $this->assertNull($result['value']);
    }

    /**
     * Test numeric values
     */
    public function testNumericValues(): void
    {
        $jsonc = '{
            "integer": 42,
            "float": 3.14,
            "negative": -10,
            "exp": 1.23e10,
        }';

        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(42, $result['integer']);
        $this->assertEquals(3.14, $result['float']);
        $this->assertEquals(-10, $result['negative']);
        $this->assertEquals(1.23e10, $result['exp']);
    }

    /**
     * Test comment at end of file without newline
     */
    public function testCommentAtEOF(): void
    {
        $jsonc = '{"key": "value"} // EOF comment';
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Test block comment at end of file
     */
    public function testBlockCommentAtEOF(): void
    {
        $jsonc = '{"key": "value"} /* EOF */';
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Test empty string value
     */
    public function testEmptyStringValue(): void
    {
        $jsonc = '{"empty": ""}';
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals('', $result['empty']);
    }
}
