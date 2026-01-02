<?php

declare(strict_types=1);

namespace Otar;

/**
 * Parser state enumeration for JSONC parsing
 *
 * Tracks the current context while scanning through JSONC input
 * to correctly handle comments and strings.
 */
enum ParserState
{
    case Normal; // Normal parsing state - not inside a string or comment
    case InString; // Inside a string literal
    case InStringEscape; // Inside a string, immediately after an escape character (\)
    case SingleLineComment; // Inside a single-line comment (//)
    case MultiLineComment; // Inside a multi-line comment (/* *\/)
}
