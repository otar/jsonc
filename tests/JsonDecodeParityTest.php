<?php

declare(strict_types=1);

namespace Otar\Tests;

use Otar\JSONC;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Differential tests against native json_decode()
 *
 * For plain JSON input — valid or invalid — JSONC::decode() must behave
 * exactly like json_decode(): same decoded value and same json_last_error()
 * afterwards. Inputs exercising JSONC features (comments, trailing commas)
 * are excluded by definition, as is a leading UTF-8 BOM, which JSONC
 * deliberately tolerates while native json_decode() rejects it.
 */
class JsonDecodeParityTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?bool, int, int}>
     */
    public static function corpus(): iterable
    {
        // Each case: [input, associative, depth, flags]

        // Valid plain JSON
        yield 'null literal' => ['null', true, 512, 0];
        yield 'true literal' => ['true', true, 512, 0];
        yield 'false literal' => ['false', true, 512, 0];
        yield 'integer' => ['42', true, 512, 0];
        yield 'negative float' => ['-3.14', true, 512, 0];
        yield 'exponent' => ['1.5e10', true, 512, 0];
        yield 'string' => ['"hello"', true, 512, 0];
        yield 'string with slashes' => ['"https:\/\/example.com\/path"', true, 512, 0];
        yield 'raw utf8 string' => ['"é中😀"', true, 512, 0];
        yield 'unicode escapes' => ['"é中😀"', true, 512, 0];
        yield 'empty object' => ['{}', true, 512, 0];
        yield 'empty array' => ['[]', true, 512, 0];
        yield 'nested structure' => ['{"a": [1, 2, {"b": null}], "c": {"d": false}}', true, 512, 0];
        yield 'whitespace padding' => ["  \t\r\n  [1, \t 2]\r\n", true, 512, 0];
        yield 'object mode' => ['{"a": {"b": 1}}', false, 512, 0];
        yield 'object as array flag' => ['{"a": {"b": 1}}', null, 512, JSON_OBJECT_AS_ARRAY];
        yield 'bignum default' => ['{"n": 123456789012345678901234567890}', true, 512, 0];
        yield 'bignum as string' => ['{"n": 123456789012345678901234567890}', true, 512, JSON_BIGINT_AS_STRING];
        yield 'depth exactly sufficient' => ['[[[1]]]', true, 4, 0];

        // Invalid plain JSON — same null result and same error code expected
        yield 'empty string' => ['', true, 512, 0];
        yield 'lone brace' => ['{', true, 512, 0];
        yield 'double comma' => ['[1,,]', true, 512, 0];
        yield 'unquoted key' => ['{key: 1}', true, 512, 0];
        yield 'single quotes' => ["{'a': 1}", true, 512, 0];
        yield 'truncated keyword' => ['tru', true, 512, 0];
        yield 'two top-level values' => ['1 2', true, 512, 0];
        yield 'depth exceeded' => ['[[[[1]]]]', true, 2, 0];
        yield 'raw control char in string' => ["\"a\x01b\"", true, 512, 0];
        yield 'raw null byte in string' => ["\"a\x00b\"", true, 512, 0];
        yield 'null byte between tokens' => ["[1,\x00 2]", true, 512, 0];
        yield 'lone surrogate' => ['"\ud800"', true, 512, 0];
        yield 'invalid utf8 no flags' => ["\"\xB1\x31\"", true, 512, 0];
        yield 'invalid utf8 ignore' => ["\"\xB1\x31\"", true, 512, JSON_INVALID_UTF8_IGNORE];
        yield 'invalid utf8 substitute' => ["\"\xB1\x31\"", true, 512, JSON_INVALID_UTF8_SUBSTITUTE];
    }

    /**
     * @dataProvider corpus
     * @param int<1, max> $depth
     */
    #[DataProvider('corpus')]
    public function testDecodeMatchesNativeJsonDecode(
        string $input,
        ?bool $associative,
        int $depth,
        int $flags
    ): void {
        $expected = json_decode($input, $associative, $depth, $flags);
        $expectedError = json_last_error();

        $actual = JSONC::decode($input, $associative, $depth, $flags);
        $actualError = json_last_error();

        if (is_object($expected)) {
            $this->assertEquals($expected, $actual);
        } else {
            $this->assertSame($expected, $actual);
        }

        $this->assertSame(
            $expectedError,
            $actualError,
            sprintf('json_last_error() diverged from native: expected %d, got %d', $expectedError, $actualError)
        );
    }
}
