<?php

declare(strict_types=1);

use Otar\JSONC;

/**
 * Global helper function for JSONC parsing
 *
 * Provides a procedural interface similar to json_decode().
 */

// @codeCoverageIgnore
if (!function_exists('jsonc_decode')) {
    /**
     * Decodes a JSONC string
     *
     * This is a drop-in replacement for json_decode() that accepts JSONC format.
     * For error handling, use the native json_last_error() and json_last_error_msg() functions.
     *
     * @param string $jsonc The JSONC string being decoded
     * @param ?bool $associative When true, returns associative arrays instead of objects
     * @param int $depth Maximum nesting depth (must be greater than zero)
     * @param int $flags Bitmask of JSON decode options
     * @return mixed The decoded value
     */
    function jsonc_decode(
        string $jsonc,
        ?bool $associative = null,
        int $depth = 512,
        int $flags = 0
    ): mixed {
        return JSONC::decode($jsonc, $associative, $depth, $flags);
    }
}
