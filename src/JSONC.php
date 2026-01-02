<?php

declare(strict_types=1);

namespace Otar;

/**
 * JSONC Parser - Handles JSON with Comments (JSONC) format
 *
 * Supports:
 * - Single-line comments (//)
 * - Multi-line comments (/* *\/)
 * - Trailing commas in objects and arrays
 *
 * @package Otar\JSONC
 * @license MIT
 */
class JSONC
{
    /**
     * Parses JSONC string and returns cleaned JSON string
     *
     * This method removes comments and trailing commas but does not
     * decode the JSON. Use decode() method for full parsing.
     *
     * @param string $jsonc The JSONC string to parse
     * @return string Cleaned JSON string
     */
    public static function parse(string $jsonc): string
    {
        // Pass 1: Remove comments
        $json = self::removeComments($jsonc);

        // Pass 2: Remove trailing commas
        $json = self::removeTrailingCommas($json);

        return $json;
    }

    /**
     * Decodes a JSONC string
     *
     * This is a drop-in replacement for json_decode() that accepts JSONC format.
     *
     * @param string $jsonc The JSONC string being decoded
     * @param ?bool $associative When true, returns associative arrays instead of objects
     * @param int $depth Maximum nesting depth (must be greater than zero)
     * @param int $flags Bitmask of JSON decode options
     * @return mixed The decoded value
     */
    public static function decode(
        string $jsonc,
        ?bool $associative = null,
        int $depth = 512,
        int $flags = 0
    ): mixed {
        // Parse JSONC to JSON
        $json = self::parse($jsonc);

        // Decode using native json_decode
        // Errors are automatically tracked via json_last_error()
        return json_decode($json, $associative, $depth, $flags);
    }

    /**
     * Removes comments from JSONC string while preserving strings
     *
     * Uses a state machine to track context and avoid removing
     * comment-like syntax inside string values.
     *
     * @param string $input JSONC string with comments
     * @return string JSON string without comments
     */
    private static function removeComments(string $input): string
    {
        $state = ParserState::Normal;
        $result = '';
        $length = strlen($input);
        $i = 0;

        while ($i < $length) {
            $char = $input[$i];
            $next = ($i + 1 < $length) ? $input[$i + 1] : null;

            switch ($state) {
                case ParserState::Normal:
                    if ($char === '"') {
                        $state = ParserState::InString;
                        $result .= $char;
                    } elseif ($char === '/' && $next === '/') {
                        $state = ParserState::SingleLineComment;
                        $i++; // Skip second '/'
                    } elseif ($char === '/' && $next === '*') {
                        $state = ParserState::MultiLineComment;
                        $i++; // Skip '*'
                    } else {
                        $result .= $char;
                    }
                    break;

                case ParserState::InString:
                    $result .= $char;
                    if ($char === '\\') {
                        $state = ParserState::InStringEscape;
                    } elseif ($char === '"') {
                        $state = ParserState::Normal;
                    }
                    break;

                case ParserState::InStringEscape:
                    $result .= $char;
                    $state = ParserState::InString;
                    break;

                case ParserState::SingleLineComment:
                    if ($char === "\n" || $char === "\r") {
                        $result .= $char; // Preserve line breaks
                        $state = ParserState::Normal;
                    }
                    // Otherwise skip character (it's part of the comment)
                    break;

                case ParserState::MultiLineComment:
                    if ($char === '*' && $next === '/') {
                        $i++; // Skip '/'
                        $state = ParserState::Normal;
                    }
                    // Otherwise skip character (it's part of the comment)
                    break;
            }

            $i++;
        }

        return $result;
    }

    /**
     * Removes trailing commas from JSON string while preserving strings
     *
     * Uses a state machine to track context and only remove commas
     * that appear before closing brackets/braces.
     *
     * @param string $input JSON string with potential trailing commas
     * @return string JSON string without trailing commas
     */
    private static function removeTrailingCommas(string $input): string
    {
        $state = ParserState::Normal;
        $result = '';
        $length = strlen($input);
        $i = 0;

        while ($i < $length) {
            $char = $input[$i];

            switch ($state) {
                case ParserState::Normal:
                    if ($char === '"') {
                        $state = ParserState::InString;
                        $result .= $char;
                    } elseif ($char === ',') {
                        // Look ahead to find next non-whitespace character
                        $j = $i + 1;
                        $whitespace = '';

                        while ($j < $length && self::isWhitespace($input[$j])) {
                            $whitespace .= $input[$j];
                            $j++;
                        }

                        // Check if comma is trailing (before } or ])
                        if ($j < $length && ($input[$j] === '}' || $input[$j] === ']')) {
                            // Skip comma but preserve whitespace
                            $result .= $whitespace;
                            $i = $j - 1; // Will be incremented at end of loop
                        } else {
                            // Not a trailing comma, keep it
                            $result .= $char;
                        }
                    } else {
                        $result .= $char;
                    }
                    break;

                case ParserState::InString:
                    $result .= $char;
                    if ($char === '\\') {
                        $state = ParserState::InStringEscape;
                    } elseif ($char === '"') {
                        $state = ParserState::Normal;
                    }
                    break;

                case ParserState::InStringEscape:
                    $result .= $char;
                    $state = ParserState::InString;
                    break;
            }

            $i++;
        }

        return $result;
    }

    /**
     * Checks if a character is whitespace
     *
     * @param string $char Single character to check
     * @return bool True if whitespace
     */
    private static function isWhitespace(string $char): bool
    {
        return in_array($char, [' ', "\t", "\n", "\r"], true);
    }
}
