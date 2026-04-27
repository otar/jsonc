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
        // Remove null bytes for security
        $jsonc = str_replace("\x00", '', $jsonc);

        // Strip UTF-8 BOM if present
        if (str_starts_with($jsonc, "\xEF\xBB\xBF")) {
            $jsonc = substr($jsonc, 3);
        }

        return self::processInput($jsonc);
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
     * Processes JSONC string in a single pass: removes comments and trailing commas
     *
     * Uses a state machine to track context, skipping comment content and
     * dropping trailing commas via a comment-aware lookahead.
     *
     * @param string $input JSONC string after null-byte and BOM removal
     * @return string Clean JSON string, or an error sentinel on unclosed constructs
     */
    private static function processInput(string $input): string
    {
        $result = '';
        $state  = ParserState::Normal;
        $length = strlen($input);
        $i      = 0;

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
                    } elseif ($char === ',') {
                        // Comment-aware lookahead to detect trailing commas
                        $j = $i + 1;
                        $skipped = '';

                        while ($j < $length) {
                            $c = $input[$j];
                            $n = ($j + 1 < $length) ? $input[$j + 1] : null;

                            if ($c === ' ' || $c === "\t" || $c === "\n" || $c === "\r") {
                                $whitespaceLength = strspn($input, " \t\n\r", $j);
                                $skipped .= substr($input, $j, $whitespaceLength);
                                $j += $whitespaceLength;
                            } elseif ($c === '/' && $n === '/') {
                                // Skip single-line comment body; newline is picked up as whitespace
                                $j += 2;
                                $j += strcspn($input, "\n\r", $j);
                            } elseif ($c === '/' && $n === '*') {
                                // Skip block comment
                                $commentEnd = strpos($input, '*/', $j + 2);
                                if ($commentEnd === false) {
                                    $j = $length;
                                    break;
                                }
                                $j = $commentEnd + 2;
                            } else {
                                break;
                            }
                        }

                        if ($j < $length && ($input[$j] === '}' || $input[$j] === ']')) {
                            // Trailing comma: drop it, emit accumulated whitespace, jump to closing bracket
                            $result .= $skipped;
                            $i = $j - 1; // Main loop $i++ lands on closing bracket
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

                case ParserState::SingleLineComment:
                    $lineEnd = $i + strcspn($input, "\n\r", $i);
                    if ($lineEnd < $length) {
                        $result .= $input[$lineEnd]; // Preserve line breaks
                        $state = ParserState::Normal;
                        $i = $lineEnd;
                    } else {
                        $i = $length;
                    }
                    break;

                case ParserState::MultiLineComment:
                    $commentEnd = strpos($input, '*/', $i);
                    if ($commentEnd !== false) {
                        $i = $commentEnd + 1; // Main loop $i++ lands after '/'
                        $state = ParserState::Normal;
                    } else {
                        $i = $length;
                    }
                    break;
            }

            $i++;
        }

        // Validate final state and return specific error sentinels for unclosed constructs
        $error = match ($state) {
            ParserState::Normal, ParserState::SingleLineComment => null,
            ParserState::MultiLineComment => '{JSONC_PARSE_ERROR: unclosed block comment}',
            ParserState::InString, ParserState::InStringEscape => '{JSONC_PARSE_ERROR: unclosed string literal}',
        };

        return $error ?? $result;
    }
}
