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
     * Test parse() preserves CRLF line endings when removing single-line comments
     */
    public function testParsePreservesCRLFWhenSkippingSingleLineComments(): void
    {
        $jsonc = "{\r\n// comment\r\n\"key\":\"value\"\r\n}";
        $json = JSONC::parse($jsonc);

        $this->assertSame("{\r\n\r\n\"key\":\"value\"\r\n}", $json);
        $this->assertSame(['key' => 'value'], json_decode($json, true));
    }

    /**
     * Test trailing comma lookahead skips comments before closing brackets
     */
    public function testTrailingCommaLookaheadSkipsComments(): void
    {
        $jsonc = '{"a": 1, /* block */ // line
}';
        $result = JSONC::decode($jsonc, true);

        $this->assertSame(['a' => 1], $result);
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

    /**
     * Odd number of backslashes before quote
     */
    public function testSecurityStringOddBackslashes(): void
    {
        // Valid: even backslashes
        $jsonc = '{"path": "C:\\\\Users"}';
        $result = JSONC::decode($jsonc, true);
        $this->assertNotNull($result);

        // Invalid: odd backslashes (string never closes)
        $jsonc = '{"key": "value\\"}';
        $result = JSONC::decode($jsonc, true);
        $this->assertNull($result);
        // Just verify there's an error, don't check specific code
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Unclosed multi-line comment
     */
    public function testSecurityCommentUnclosedMultiLine(): void
    {
        $jsonc = '{"key": "value" /* unclosed comment';
        $result = JSONC::decode($jsonc, true);
        $this->assertNull($result);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Unclosed string at EOF
     */
    public function testSecurityStringUnclosedAtEOF(): void
    {
        $jsonc = '{"key": "unclosed string';
        $result = JSONC::decode($jsonc, true);
        $this->assertNull($result);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Null byte injection
     */
    public function testSecurityInputNullByteInjection(): void
    {
        $jsonc = "{\"key\": \"val\x00ue\"}";
        $result = JSONC::decode($jsonc, true);
        // Should strip null bytes and parse successfully
        $this->assertNotNull($result);
        $this->assertEquals('value', $result['key']);
    }

    /**
     * Comments between key and value
     */
    public function testSecurityCommentBetweenKeyValue(): void
    {
        $jsonc = '{"key" /* comment */ : "value"}';
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Escaped forward slash
     */
    public function testSecurityStringEscapedForwardSlash(): void
    {
        $jsonc = '{"url": "https:\/\/example.com"}';
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['url' => 'https://example.com'], $result);
    }

    /**
     * BOM (Byte Order Mark) handling
     */
    public function testSecurityInputBOMHandling(): void
    {
        $jsonc = "\xEF\xBB\xBF{\"key\": \"value\"}";
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Invalid UTF-8 sequences
     */
    public function testSecurityInputInvalidUTF8(): void
    {
        $jsonc = "{\"key\": \"val\xC0\xC1ue\"}";
        $result = JSONC::decode($jsonc, true);
        // Should handle gracefully - either parse or return null
        // We don't enforce specific behavior, just no crash
        $this->assertTrue($result === null || is_array($result));
    }

    /**
     * DoS protection - very large input
     */
    public function testSecurityInputSizeLimit(): void
    {
        // Test with very large but reasonable input
        $large = str_repeat('{"key": "value"},', 10000);
        $jsonc = '[' . $large . '{}]';
        $result = JSONC::decode($jsonc, true);
        $this->assertNotNull($result);
    }

    /**
     * Nested comment syntax in strings
     */
    public function testSecurityStringNestedCommentSyntax(): void
    {
        $jsonc = '{"msg": "Use /* and */ for comments"}';
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['msg' => 'Use /* and */ for comments'], $result);
    }

    /**
     * Multiple consecutive backslashes
     */
    public function testSecurityStringMultipleBackslashes(): void
    {
        $jsonc = '{"path": "\\\\\\\\server"}';
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['path' => '\\\\server'], $result);
    }

    /**
     * Comment start after escape
     */
    public function testSecurityStringCommentStartAfterEscape(): void
    {
        $jsonc = '{"msg": "This is \\/* not a comment"}';
        $result = JSONC::decode($jsonc, true);
        $this->assertNotNull($result);
    }

    /**
     * Empty comment
     */
    public function testSecurityCommentEmpty(): void
    {
        $jsonc = '{/**/"key": "value"}';
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Comment-only input
     */
    public function testSecurityCommentOnlyInput(): void
    {
        $jsonc = '// Just a comment';
        $result = JSONC::decode($jsonc, true);
        $this->assertNull($result);
    }

    /**
     * Trailing comma after comment
     */
    public function testSecurityTrailingCommaAfterComment(): void
    {
        $jsonc = '{"a": 1, // comment
}';
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['a' => 1], $result);
    }

    /**
     * Mixed line endings
     */
    public function testSecurityInputMixedLineEndings(): void
    {
        $jsonc = "{\n\"a\": 1,\r\n\"b\": 2,\r\"c\": 3\n}";
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3], $result);
    }

    /**
     * Very deep nesting
     */
    public function testSecurityInputVeryDeepNesting(): void
    {
        $deep = str_repeat('[', 100) . '1' . str_repeat(']', 100);
        $result = JSONC::decode($deep, true, 200);
        $this->assertNotNull($result);
    }

    /**
     * Unicode in comments
     */
    public function testSecurityCommentUnicodeContent(): void
    {
        $jsonc = '{/* 你好 🚀 */"key": "value"}';
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Backslash before comment
     */
    public function testSecurityBackslashBeforeComment(): void
    {
        $jsonc = '{"key": "value"}\\ // not in string';
        $result = JSONC::decode($jsonc, true);
        $this->assertNull($result); // Invalid JSON
    }

    /**
     * Control characters in strings
     */
    public function testSecurityStringControlCharacters(): void
    {
        $jsonc = '{"msg": "line1\\nline2\\ttab"}';
        $result = JSONC::decode($jsonc, true);
        $this->assertStringContainsString("\n", $result['msg']);
    }

    /**
     * Comment syntax as keys
     */
    public function testSecurityCommentSyntaxAsKeys(): void
    {
        $jsonc = '{"//": "value1", "/**/": "value2", "/": "value3", "*": "value4"}';
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['//' => 'value1', '/**/' => 'value2', '/' => 'value3', '*' => 'value4'], $result);
    }

    /**
     * Multiple inline comments
     */
    public function testSecurityMultipleInlineComments(): void
    {
        $jsonc = '{
  "key": /* comment1 */ /* comment2 */ "value"
}';
        $result = JSONC::decode($jsonc, true);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Empty input
     */
    public function testSecurityEmptyInput(): void
    {
        $result = JSONC::decode('', true);
        $this->assertNull($result);
    }

    /**
     * Null byte before BOM (order of operations)
     * The BOM check happens before null byte removal, so if there's a null byte
     * before the BOM, the BOM won't be detected and will remain in the output.
     */
    public function testBugNullByteBeforeBOM(): void
    {
        $jsonc = "\x00\xEF\xBB\xBF{\"key\": \"value\"}";
        $result = JSONC::decode($jsonc, true);

        // If BOM is properly removed, this should parse correctly
        // If BOM remains (bug), json_decode will likely fail
        $this->assertNotNull($result, "Parser should handle null byte before BOM");
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Null byte in comment marker (creates comment after sanitization)
     * If there's a null byte between the two slashes, it's not a comment before sanitization.
     * After null removal, it becomes a comment, changing the parsing behavior.
     */
    public function testBugNullByteInCommentMarker(): void
    {
        // Null byte between slashes: "/" + "\x00" + "/" = not a comment initially
        // After null removal: "//" = comment!
        $jsonc = "{\"a\": 1}/\x00/ comment } {\"b\": 2}";
        $result = JSONC::decode($jsonc, true);

        // Expected: If bug exists, "comment } {\"b\": 2}" becomes a comment
        // Result would be just {"a": 1} (invalid JSON - missing closing brace)
        // If no bug: /\x00/ is kept, becomes //, making everything after it a comment

        // This test verifies this behavior
        if ($result !== null && isset($result['a'])) {
            // Null byte turned non-comment into comment
            $this->assertEquals(['a' => 1], $result);
            $this->assertArrayNotHasKey('b', $result, "Second object should be commented out due to null byte creating comment marker");
        } else {
            $this->fail("Unexpected parsing result");
        }
    }

    /**
     * Null byte splitting multi-line comment start
     */
    public function testBugNullByteSplittingMultiLineCommentStart(): void
    {
        $jsonc = "{/\x00* not a comment */\"key\": \"value\"}";
        $result = JSONC::decode($jsonc, true);

        // After null removal: "{/* not a comment */\"key\": \"value\"}"
        // The /* */ becomes a comment, removing " not a comment "
        // Result: "{\"key\": \"value\"}"
        $this->assertNotNull($result);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Null byte splitting multi-line comment end
     */
    public function testBugNullByteSplittingMultiLineCommentEnd(): void
    {
        $jsonc = "{\"key\": \"value\"} /* comment *\x00/ still comment? */";
        $result = JSONC::decode($jsonc, true);

        // After null removal: "{\"key\": \"value\"} /* comment */ still comment? */"
        // First */ closes the comment, " still comment? */" remains
        // Invalid JSON after preprocessing
        $this->assertNull($result, "Should produce invalid JSON");
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Multiple BOMs at start
     */
    public function testMultipleBOMsAtStart(): void
    {
        $jsonc = "\xEF\xBB\xBF\xEF\xBB\xBF{\"key\": \"value\"}";
        $result = JSONC::decode($jsonc, true);

        // Only first BOM should be stripped, second remains
        // Second BOM (3 bytes) in JSON should cause parsing to fail
        // unless json_decode() is tolerant of these bytes
        if ($result === null) {
            // Second BOM caused failure (expected)
            $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
        } else {
            // json_decode was tolerant - document this
            $this->assertEquals(['key' => 'value'], $result);
        }
    }

    /**
     * Incomplete BOM sequence
     */
    public function testIncompleteBOM(): void
    {
        $jsonc = "\xEF\xBB{\"key\": \"value\"}";
        $result = JSONC::decode($jsonc, true);

        // Incomplete BOM (only 2 bytes) should not be recognized
        // The bytes should remain and likely cause json_decode to fail
        $this->assertNull($result, "Incomplete BOM should not be recognized");
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * BOM in middle of file (should not be stripped)
     */
    public function testBOMInMiddleOfFile(): void
    {
        $jsonc = "{\"a\": \"\xEF\xBB\xBF\", \"b\": 2}";
        $result = JSONC::decode($jsonc, true);

        // BOM bytes in string should be preserved
        $this->assertNotNull($result);
        $this->assertEquals("\xEF\xBB\xBF", $result['a']);
        $this->assertEquals(2, $result['b']);
    }

    /**
     * Escaped forward slash followed by slash
     */
    public function testStateMachineEscapedForwardSlashBeforeComment(): void
    {
        $jsonc = '{"url": "https:\/\//comment?"}';
        $result = JSONC::decode($jsonc, true);

        // The \/ is an escaped forward slash (valid in JSON)
        // json_decode converts \/ to /, so \/\/ becomes ///
        // All slashes are inside the string, NOT a comment marker
        $this->assertNotNull($result);
        $this->assertEquals('https:///comment?', $result['url']);
    }

    /**
     * Escaped quote after backslash
     */
    public function testStateMachineEscapedQuoteAfterBackslash(): void
    {
        $jsonc = '{"test": "\\\\\""}';
        $result = JSONC::decode($jsonc, true);

        // Actual string (after PHP single-quote processing): {"test": "\\\""}
        // Input: \\\" = 3 backslashes + 2 quotes
        // First \\ = escaped backslash → adds one \ to result
        // Second \  + " = escape sequence, adds \" to result (still in string)
        // Third " = closes string
        // Result: backslash + quote character
        $this->assertNotNull($result);
        $this->assertEquals('\\"', $result['test']);
    }

    /**
     * Backslash-escaped forward slash in comment pattern
     */
    public function testStateMachineEscapedSlashInCommentPattern(): void
    {
        $jsonc = '{"test": "\\/* not a comment */"}';
        $result = JSONC::decode($jsonc, true);

        // \/* inside string should be preserved
        $this->assertNotNull($result);
        $this->assertStringContainsString('/*', $result['test']);
    }

    /**
     * Space between * and / in multi-line comment
     */
    public function testStateMachineSpaceBetweenCommentClose(): void
    {
        $jsonc = '{"a": 1} /* comment * / still in comment */ {"b": 2}';
        $result = JSONC::decode($jsonc, true);

        // * followed by space and / should NOT close comment
        // Comment continues until the real */
        // After comment removal: {"a": 1}  {"b": 2}
        // Two objects side-by-side = invalid JSON
        $this->assertNull($result);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Nested comment-like pattern
     */
    public function testStateMachineNestedCommentPattern(): void
    {
        $jsonc = '/* outer /* inner */ after */{"key": "value"}';
        $result = JSONC::decode($jsonc, true);

        // First /* starts comment
        // Second /* is just comment content
        // First */ ends comment
        // " after " remains as JSON content
        // This should cause invalid JSON
        $this->assertNull($result);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Slash-asterisk at comment start
     */
    public function testStateMachineSlashAsteriskCommentStart(): void
    {
        $jsonc = '/*/ pattern */{"key": "value"}';
        $result = JSONC::decode($jsonc, true);

        // /*/ starts a multi-line comment
        // Comment content is: " pattern "
        // */ closes it
        // Valid JSON remains
        $this->assertNotNull($result);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Multiple consecutive slashes
     */
    public function testStateMachineMultipleSlashes(): void
    {
        $jsonc = '{"a": 1}/// triple slash comment';
        $result = JSONC::decode($jsonc, true);

        // First two // start single-line comment
        // Third / is part of comment content
        $this->assertNotNull($result);
        $this->assertEquals(['a' => 1], $result);
    }

    /**
     * Multiple asterisks before slash
     */
    public function testStateMachineMultipleAsterisks(): void
    {
        $jsonc = '/* comment ***/{"key": "value"}';
        $result = JSONC::decode($jsonc, true);

        // Multiple asterisks before /
        // First */ should close the comment
        $this->assertNotNull($result);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Empty string key missing value after comment
     */
    public function testStateMachineEmptyStringKeyMissingValue(): void
    {
        $jsonc = '{"": // comment
"key": "value"}';
        $result = JSONC::decode($jsonc, true);

        // After comment removal: {"": \n"key": "value"}
        // Empty string key has no value! Invalid JSON
        $this->assertNull($result);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Escaped quote in string value
     */
    public function testStateMachineEscapedQuoteInString(): void
    {
        $jsonc = '{"key": "value\\""}// comment';
        $result = JSONC::decode($jsonc, true);

        // "value\"" = value + escaped quote, then closing "
        // The \\" is an escaped quote character (becomes " in decoded string)
        // Then // comment is removed
        $this->assertNotNull($result);
        $this->assertEquals('value"', $result['key']);
    }

    /**
     * Comment syntax as object key
     */
    public function testStateMachineCommentSyntaxAsObjectKey(): void
    {
        $jsonc = '{
            "//": "single line marker",
            "/*": "multi start",
            "*/": "multi end",
            "/**/": "empty comment"
        }';
        $result = JSONC::decode($jsonc, true);

        // All comment syntax should work as keys when properly quoted
        $this->assertNotNull($result);
        $this->assertEquals('single line marker', $result['//']);
        $this->assertEquals('multi start', $result['/*']);
        $this->assertEquals('multi end', $result['*/']);
        $this->assertEquals('empty comment', $result['/**/']);
    }

    /**
     * Single slash at EOF
     */
    public function testStateMachineSingleSlashAtEOF(): void
    {
        $jsonc = '{"key": "value"}/';
        $result = JSONC::decode($jsonc, true);

        // Single slash at EOF - not a comment, just invalid JSON
        $this->assertNull($result);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Slash followed by non-comment character
     */
    public function testStateMachineSlashNonComment(): void
    {
        $jsonc = '{"key": "value"}/x';
        $result = JSONC::decode($jsonc, true);

        // /x is not a comment marker, kept as-is
        // Results in invalid JSON
        $this->assertNull($result);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Asterisk without slash
     */
    public function testStateMachineAsteriskWithoutSlash(): void
    {
        $jsonc = '{"key": "value"} * test';
        $result = JSONC::decode($jsonc, true);

        // Lone asterisk - not a comment, invalid JSON
        $this->assertNull($result);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Comment close without open
     */
    public function testStateMachineCloseWithoutOpen(): void
    {
        $jsonc = '{"key": "value"} */ test';
        $result = JSONC::decode($jsonc, true);

        // */ without /* - not a comment marker, invalid JSON
        $this->assertNull($result);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Non-breaking space (U+00A0) after trailing comma
     * This tests if Unicode whitespace is recognized in trailing comma detection
     */
    public function testUnicodeNonBreakingSpaceAfterComma(): void
    {
        // U+00A0 = non-breaking space (UTF-8: 0xC2 0xA0)
        $jsonc = "{\"a\": 1,\xC2\xA0}";
        $result = JSONC::decode($jsonc, true);

        // isWhitespace() only checks for space, tab, \n, \r
        // U+00A0 is NOT recognized, so comma is NOT removed
        // Result: {"a": 1,<nbsp>} which is invalid JSON
        $this->assertNull($result);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Line separator (U+2028) in single-line comment
     */
    public function testUnicodeLineSeparatorInComment(): void
    {
        // U+2028 = line separator (UTF-8: 0xE2 0x80 0xA8)
        $jsonc = "{\"a\": 1} // comment\xE2\x80\xA8{\"b\": 2}";
        $result = JSONC::decode($jsonc, true);

        // U+2028 is NOT recognized as line terminator (only \n and \r are)
        // Everything after // is treated as comment, including the second object
        // After comment removal: {"a": 1}
        $this->assertNotNull($result);
        $this->assertEquals(['a' => 1], $result);
        $this->assertArrayNotHasKey('b', $result);
    }

    /**
     * Paragraph separator (U+2029) in single-line comment
     */
    public function testUnicodeParagraphSeparatorInComment(): void
    {
        // U+2029 = paragraph separator (UTF-8: 0xE2 0x80 0xA9)
        $jsonc = "{\"a\": 1} // comment\xE2\x80\xA9{\"b\": 2}";
        $result = JSONC::decode($jsonc, true);

        // U+2029 is NOT recognized as line terminator
        // Second object becomes part of comment
        $this->assertNotNull($result);
        $this->assertEquals(['a' => 1], $result);
        $this->assertArrayNotHasKey('b', $result);
    }

    /**
     * Zero-width space (U+200B) after trailing comma
     */
    public function testUnicodeZeroWidthSpaceAfterComma(): void
    {
        // U+200B = zero-width space (UTF-8: 0xE2 0x80 0x8B)
        $jsonc = "{\"a\": 1,\xE2\x80\x8B}";
        $result = JSONC::decode($jsonc, true);

        // Zero-width space is NOT recognized as whitespace
        // Comma is NOT removed, invalid JSON
        $this->assertNull($result);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Form feed (\f / 0x0C) in single-line comment
     */
    public function testUnicodeFormFeedInComment(): void
    {
        $jsonc = "{\"a\": 1} // comment\x0C{\"b\": 2}";
        $result = JSONC::decode($jsonc, true);

        // Form feed (0x0C) is NOT recognized as line terminator
        // Second object becomes part of comment
        $this->assertNotNull($result);
        $this->assertEquals(['a' => 1], $result);
        $this->assertArrayNotHasKey('b', $result);
    }

    /**
     * Vertical tab (\v / 0x0B) in single-line comment
     */
    public function testUnicodeVerticalTabInComment(): void
    {
        $jsonc = "{\"a\": 1} // comment\x0B{\"b\": 2}";
        $result = JSONC::decode($jsonc, true);

        // Vertical tab (0x0B) is NOT recognized as line terminator
        // Second object becomes part of comment
        $this->assertNotNull($result);
        $this->assertEquals(['a' => 1], $result);
        $this->assertArrayNotHasKey('b', $result);
    }

    /**
     * Tab character after trailing comma (should work)
     */
    public function testUnicodeTabAfterTrailingComma(): void
    {
        $jsonc = "{\"a\": 1,\t}";
        $result = JSONC::decode($jsonc, true);

        // Tab IS recognized as whitespace
        // Comma should be removed
        $this->assertNotNull($result);
        $this->assertEquals(['a' => 1], $result);
    }

    /**
     * Multiple Unicode whitespace types after comma
     */
    public function testUnicodeMultipleWhitespaceAfterComma(): void
    {
        // Mix of regular space, nbsp, zero-width space
        $jsonc = "{\"a\": 1, \xC2\xA0\xE2\x80\x8B}";
        $result = JSONC::decode($jsonc, true);

        // Only regular space is recognized
        // When lookahead encounters nbsp, it stops and checks next char
        // Next char is nbsp (not } or ]), so comma is kept
        $this->assertNull($result);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * RTL override in comment
     */
    public function testUnicodeRTLOverrideInComment(): void
    {
        // U+202E = right-to-left override
        $jsonc = "{\"key\": \"value\"} /* \xE2\x80\xAEtest */";
        $result = JSONC::decode($jsonc, true);

        // RTL override doesn't affect parsing (only visual)
        // Comment is removed correctly
        $this->assertNotNull($result);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Combining characters in comment
     */
    public function testUnicodeCombiningCharsInComment(): void
    {
        // e + combining acute accent (U+0301)
        $jsonc = "{\"key\": \"value\"} /* e\xCC\x81 */";
        $result = JSONC::decode($jsonc, true);

        // Combining characters don't affect parsing
        $this->assertNotNull($result);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Large whitespace after trailing comma
     * Tests if lookahead accumulation causes performance issues
     */
    public function testPerformanceLargeWhitespaceAfterComma(): void
    {
        // 100KB of whitespace after trailing comma
        $whitespace = str_repeat(" \t\n", (int)(100000 / 3));
        $jsonc = "{\"a\": 1,{$whitespace}}";

        $start = microtime(true);
        $result = JSONC::decode($jsonc, true);
        $duration = microtime(true) - $start;

        // Should complete quickly (< 2 seconds)
        $this->assertLessThan(2.0, $duration, "Parsing took too long: {$duration}s");

        // Comma should be removed, whitespace preserved
        $this->assertNotNull($result);
        $this->assertEquals(['a' => 1], $result);
    }

    /**
     * Huge single-line comment
     */
    public function testPerformanceHugeSingleLineComment(): void
    {
        // 1MB comment
        $comment = str_repeat('x', 1000000);
        $jsonc = "{\"a\": 1} // {$comment}";

        $start = microtime(true);
        $result = JSONC::decode($jsonc, true);
        $duration = microtime(true) - $start;

        // Should complete quickly despite large comment
        $this->assertLessThan(1.0, $duration, "Parsing took too long: {$duration}s");

        $this->assertNotNull($result);
        $this->assertEquals(['a' => 1], $result);
    }

    /**
     * Huge multi-line comment
     */
    public function testPerformanceHugeMultiLineComment(): void
    {
        // 1MB comment
        $comment = str_repeat('x', 1000000);
        $jsonc = "/* {$comment} */ {\"a\": 1}";

        $start = microtime(true);
        $result = JSONC::decode($jsonc, true);
        $duration = microtime(true) - $start;

        // Should complete in reasonable time (< 2 seconds)
        $this->assertLessThan(2.0, $duration, "Parsing took too long: {$duration}s");

        $this->assertNotNull($result);
        $this->assertEquals(['a' => 1], $result);
    }

    /**
     * Many trailing commas
     */
    public function testPerformanceManyTrailingCommas(): void
    {
        // 10,000 objects with trailing commas
        $objects = [];
        for ($i = 0; $i < 10000; $i++) {
            $objects[] = "{\"x{$i}\": {$i},}";
        }
        $jsonc = '[' . implode(',', $objects) . ']';

        $start = microtime(true);
        $result = JSONC::decode($jsonc, true);
        $duration = microtime(true) - $start;

        // Should complete in reasonable time (< 2 seconds)
        $this->assertLessThan(2.0, $duration, "Parsing took too long: {$duration}s");

        $this->assertNotNull($result);
        $this->assertCount(10000, $result);
    }

    /**
     * Rapid state transitions
     */
    public function testPerformanceRapidStateTransitions(): void
    {
        // 10,000 alternating string/comment pairs with commas
        $pattern = str_repeat('"",/**/', 10000);
        $jsonc = "[{$pattern}null]"; // Add null at end to make valid JSON

        $start = microtime(true);
        $result = JSONC::decode($jsonc, true);
        $duration = microtime(true) - $start;

        // Should handle rapid state changes efficiently
        $this->assertLessThan(2.0, $duration, "Parsing took too long: {$duration}s");

        // After comment removal: ["","",...,null] = array of 10k empty strings + null
        $this->assertNotNull($result);
        $this->assertCount(10001, $result);
    }

    /**
     * Very long string with no comments
     */
    public function testPerformanceVeryLongString(): void
    {
        // 1MB string value
        $longString = str_repeat('a', 1000000);
        $jsonc = "{\"key\": \"{$longString}\"}";

        $start = microtime(true);
        $result = JSONC::decode($jsonc, true);
        $duration = microtime(true) - $start;

        // Should handle long strings efficiently (< 2 seconds)
        $this->assertLessThan(2.0, $duration, "Parsing took too long: {$duration}s");

        $this->assertNotNull($result);
        $this->assertEquals($longString, $result['key']);
    }
}
