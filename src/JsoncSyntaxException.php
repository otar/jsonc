<?php

declare(strict_types=1);

namespace Otar;

use JsonException;

/**
 * Thrown when JSONC input ends inside an unclosed construct
 *
 * Raised by JSONC::parse() for input that ends inside an unterminated
 * string literal or block comment. Extends JsonException so callers using
 * JSON_THROW_ON_ERROR can handle JSONC-level and native JSON errors with a
 * single catch block; getCode() returns JSON_ERROR_SYNTAX like native
 * throw-mode decoding does.
 */
final class JsoncSyntaxException extends JsonException
{
    private function __construct(
        string $message,
        private readonly int $offset
    ) {
        parent::__construct($message, JSON_ERROR_SYNTAX);
    }

    /**
     * Byte offset into the original input where the unclosed construct starts
     *
     * Points at the opening quote of an unterminated string literal, or at
     * the '/' of an unterminated '/*' block comment.
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    public static function unclosedStringLiteral(int $offset): self
    {
        return new self(sprintf('Unclosed string literal starting at offset %d', $offset), $offset);
    }

    public static function unclosedBlockComment(int $offset): self
    {
        return new self(sprintf('Unclosed block comment starting at offset %d', $offset), $offset);
    }
}
