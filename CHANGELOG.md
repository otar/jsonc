# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-06-11

### Fixed

- Block comments were removed without leaving a separator, which could fuse
  adjacent tokens: `[1/**/2]` silently decoded to `[12]` and `tr/**/ue` to
  `true`. Each closed block comment is now replaced with a single space, so
  such inputs fail with a syntax error as they should.

### Changed

- **BREAKING**: `JSONC::parse()` now throws `Otar\JsoncSyntaxException` (a
  `JsonException` subclass carrying the byte offset where the construct
  opened) when input ends inside an unclosed string literal or block comment,
  instead of returning a `{JSONC_PARSE_ERROR: ...}` sentinel string.
  `JSONC::decode()` behavior is unchanged: `null` plus `json_last_error()`,
  or an exception when `JSON_THROW_ON_ERROR` is set.
- **BREAKING**: raw null bytes (`\x00`) are no longer stripped from input.
  They pass through to `json_decode()`, which rejects them
  (`JSON_ERROR_CTRL_CHAR`) exactly as it does for plain JSON. The previous
  sanitization could silently rewrite document structure — for example
  `/\x00/` collapsed into a `//` comment marker.
- **BREAKING**: the `JSONC` class is `final` and can no longer be
  instantiated or extended.
- `JSONC::parse()` output contains a single space where each block comment
  was (previously the comment vanished without a trace).

### Added

- `Otar\JsoncSyntaxException` with `getOffset()` and specific messages
  ("Unclosed block comment starting at offset 9").
- Fast path in `decode()`: input that is already plain JSON is decoded
  directly by native `json_decode()` (roughly an order of magnitude faster
  on comment-free input); the scanner only runs when the native parse fails.
- Fast path in `parse()` on PHP 8.3+ via `json_validate()`.
- Differential test suite (`JsonDecodeParityTest`) asserting parity with
  native `json_decode()` for both decoded values and `json_last_error()`
  codes across valid and invalid plain-JSON inputs.
- PHPStan at level max, wired into CI as a separate job.
- PHP 8.5 in the CI test matrix; PHPUnit 12 supported.
- `bench/bench.php` micro-benchmark (`composer bench`).

### Migration notes

- If you called `parse()` on potentially malformed input, wrap it in
  `try/catch (Otar\JsoncSyntaxException $e)` — it no longer returns sentinel
  strings.
- If you relied on null bytes being silently removed, sanitize input before
  decoding; the library now rejects such input like `json_decode()` does.

## [1.3.0] and earlier

See the git history.
