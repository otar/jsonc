<?php

declare(strict_types=1);

namespace Otar;

use JsonException;

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
final class JSONC
{
    /**
     * UTF-8 byte order mark, tolerated at the start of input
     */
    private const BOM = "\xEF\xBB\xBF";

    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * Parses JSONC string and returns cleaned JSON string
     *
     * This method removes comments and trailing commas but does not
     * decode the JSON. Use decode() method for full parsing.
     *
     * Note: this method may probe the input with json_validate(), which
     * writes the global JSON error state. json_last_error() is only
     * meaningful after decode(), never after parse().
     *
     * @param string $jsonc The JSONC string to parse
     * @return string Cleaned JSON string
     * @throws JsoncSyntaxException When the input ends inside an unclosed string literal or block comment
     */
    public static function parse(string $jsonc): string
    {
        // Skip a leading UTF-8 BOM by scanning from an offset instead of
        // copying the remainder of the input
        $start = str_starts_with($jsonc, self::BOM) ? 3 : 0;

        // Fast path (PHP >= 8.3): input that already validates as plain JSON
        // passes through the scanner byte-for-byte, so return it as-is.
        // json_validate() rejects BOMs and comments, so those fall through.
        if ($start === 0 && function_exists('json_validate') && json_validate($jsonc)) {
            return $jsonc;
        }

        return self::processInput($jsonc, $start);
    }

    /**
     * Decodes a JSONC string
     *
     * This is a drop-in replacement for json_decode() that accepts JSONC format.
     *
     * @param string $jsonc The JSONC string being decoded
     * @param ?bool $associative When true, returns associative arrays instead of objects
     * @param int<1, max> $depth Maximum nesting depth (must be greater than zero)
     * @param int $flags Bitmask of JSON decode options
     * @return mixed The decoded value
     * @throws JsonException When JSON_THROW_ON_ERROR is set and the input is invalid
     */
    public static function decode(
        string $jsonc,
        ?bool $associative = null,
        int $depth = 512,
        int $flags = 0
    ): mixed {
        $start = str_starts_with($jsonc, self::BOM) ? 3 : 0;

        // Fast path: JSONC without comments or trailing commas is plain JSON,
        // so try the native parser first. Flags stay unmasked so the trial
        // leaves json_last_error() exactly as a native call would; in throw
        // mode a JsonException from the trial means "fall back", not "report".
        try {
            $result = json_decode($start === 0 ? $jsonc : substr($jsonc, $start), $associative, $depth, $flags);

            // In throw mode a non-throwing trial is a success and the global
            // error state may hold a stale value from an earlier call, so it
            // must not be consulted
            if (($flags & JSON_THROW_ON_ERROR) !== 0 || json_last_error() === JSON_ERROR_NONE) {
                return $result;
            }
        } catch (JsonException) {
            // Not plain JSON; fall through to the JSONC scanner
        }

        try {
            $json = self::processInput($jsonc, $start);
        } catch (JsoncSyntaxException $exception) {
            if (($flags & JSON_THROW_ON_ERROR) !== 0) {
                throw $exception;
            }

            // Mirror json_decode()'s no-throw contract for invalid input:
            // poison the global error state with a failed decode, return null
            json_decode('{');

            return null;
        }

        return json_decode($json, $associative, $depth, $flags);
    }

    /**
     * Processes JSONC string in a single pass: removes comments and trailing commas
     *
     * Uses a state machine to track context, skipping comment content and
     * dropping trailing commas via a comment-aware lookahead. Closed block
     * comments are replaced with a single space so they keep separating
     * tokens the way whitespace does.
     *
     * @param string $input Full JSONC input
     * @param int $start Byte offset to start scanning from (skips a leading BOM)
     * @return string Clean JSON string
     * @throws JsoncSyntaxException When the input ends inside an unclosed string literal or block comment
     */
    private static function processInput(string $input, int $start): string
    {
        $result = '';
        $state  = ParserState::Normal;
        $length = strlen($input);
        $i      = $start;

        // Offset of the opening quote or '/*' of the construct currently
        // being scanned, reported if the input ends before it is closed
        $constructStart = 0;

        while ($i < $length) {
            switch ($state) {
                case ParserState::Normal:
                    $spanLength = strcspn($input, '"/,', $i);
                    if ($spanLength > 0) {
                        $result .= substr($input, $i, $spanLength);
                        $i += $spanLength;

                        if ($i >= $length) {
                            break 2;
                        }
                    }

                    $char = $input[$i];
                    $next = ($i + 1 < $length) ? $input[$i + 1] : null;

                    if ($char === '"') {
                        $state = ParserState::InString;
                        $constructStart = $i;
                        $result .= $char;
                    } elseif ($char === '/' && $next === '/') {
                        $state = ParserState::SingleLineComment;
                        $i++; // Skip second '/'
                    } elseif ($char === '/' && $next === '*') {
                        $state = ParserState::MultiLineComment;
                        $constructStart = $i;
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
                    $spanLength = strcspn($input, "\\\"", $i);
                    if ($spanLength > 0) {
                        $result .= substr($input, $i, $spanLength);
                        $i += $spanLength;

                        if ($i >= $length) {
                            break 2;
                        }
                    }

                    $char = $input[$i];

                    $result .= $char;
                    if ($char === '\\') {
                        $state = ParserState::InStringEscape;
                    } elseif ($char === '"') {
                        $state = ParserState::Normal;
                    }
                    break;

                case ParserState::InStringEscape:
                    $char = $input[$i];

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
                        // Replace the comment with a single space so adjacent
                        // tokens stay separated ([1/**/2] must not fuse into
                        // the single token 12)
                        $result .= ' ';
                        $i = $commentEnd + 1; // Main loop $i++ lands after '/'
                        $state = ParserState::Normal;
                    } else {
                        $i = $length;
                    }
                    break;
            }

            $i++;
        }

        // Unclosed strings and block comments are syntax errors; single-line
        // comments are legitimately terminated by end of input
        return match ($state) {
            ParserState::Normal, ParserState::SingleLineComment => $result,
            ParserState::MultiLineComment => throw JsoncSyntaxException::unclosedBlockComment($constructStart),
            ParserState::InString, ParserState::InStringEscape => throw JsoncSyntaxException::unclosedStringLiteral($constructStart),
        };
    }
}
