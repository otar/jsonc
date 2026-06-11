<?php

declare(strict_types=1);

/**
 * Throughput benchmark for JSONC::decode() against native json_decode().
 *
 * Usage: composer bench (or: php bench/bench.php)
 *
 * Not wired into CI — absolute numbers are machine-dependent; use it to
 * compare before/after when touching the parser hot path.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "bench.php must be run from the CLI.\n");
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

use Otar\JSONC;

/**
 * A small, realistic JSONC config file (~400 B)
 */
function smallConfig(): string
{
    return <<<'JSONC'
    {
        // Application settings
        "name": "demo-app",
        "version": "1.2.3",
        /* Feature flags,
           toggled per environment */
        "features": {
            "darkMode": true,
            "beta": false, // not in prod
        },
        "limits": [10, 20, 30,],
    }
    JSONC;
}

/**
 * Comment-heavy JSONC of roughly $targetBytes (comments dominate the input)
 */
function commentHeavyJsonc(int $targetBytes): string
{
    $out = "{\n";
    $i = 0;

    while (strlen($out) < $targetBytes) {
        $out .= "    // entry {$i} with a reasonably long single-line comment for padding\n";
        $out .= "    /* block comment {$i} that adds a few more comment bytes to the corpus */\n";
        $out .= "    \"key{$i}\": {\"value\": {$i}, \"label\": \"item-{$i}\"},\n";
        $i++;
    }

    return $out . "    \"end\": true\n}";
}

/**
 * Array of $count objects, every one followed by a trailing comma
 */
function trailingCommaHeavy(int $count): string
{
    $items = [];

    for ($i = 0; $i < $count; $i++) {
        $items[] = "{\"id\": {$i}, \"name\": \"row-{$i}\",}";
    }

    return '[' . implode(',', $items) . ',]';
}

/**
 * Plain valid JSON (no comments, no trailing commas) of ~1 MB
 */
function largePlainJson(int $rows): string
{
    $data = [];

    for ($i = 0; $i < $rows; $i++) {
        $data["key{$i}"] = [
            'id' => $i,
            'name' => "item-{$i}",
            'tags' => ['alpha', 'beta', 'gamma'],
            'active' => ($i % 2) === 0,
        ];
    }

    $json = json_encode($data, JSON_PRETTY_PRINT);

    if ($json === false) {
        throw new RuntimeException('Failed to build plain JSON corpus');
    }

    return $json;
}

/**
 * Median wall time of $fn in microseconds over $iterations runs
 *
 * @param callable(): mixed $fn
 */
function medianMicros(callable $fn, int $iterations): float
{
    $fn(); // Warm up caches and lazy initialization
    $fn();

    $samples = [];

    for ($i = 0; $i < $iterations; $i++) {
        $t0 = hrtime(true);
        $fn();
        $samples[] = (hrtime(true) - $t0) / 1_000;
    }

    sort($samples);

    return $samples[intdiv(count($samples), 2)];
}

/**
 * Benchmark one corpus and print a result row
 *
 * The native baseline decodes the corpus directly when it is plain JSON,
 * otherwise it decodes the pre-cleaned output of JSONC::parse() — i.e. it
 * shows what a comment-free equivalent would cost.
 */
function run(string $name, string $corpus, int $iterations, bool $plainJson): void
{
    $bytes = strlen($corpus);

    $jsoncUs = medianMicros(static fn (): mixed => JSONC::decode($corpus, true), $iterations);

    // Native json_decode rejects a BOM outright, so strip it for the
    // baseline — otherwise the row would measure a failed parse
    $baselineInput = $plainJson
        ? (str_starts_with($corpus, "\xEF\xBB\xBF") ? substr($corpus, 3) : $corpus)
        : JSONC::parse($corpus);
    $baselineLabel = $plainJson ? 'native direct' : 'native on cleaned';
    $nativeUs = medianMicros(static fn (): mixed => json_decode($baselineInput, true), $iterations);

    printf(
        "%-22s %12s %12.1f µs/op %9.1f MB/s   %s: %10.1f µs/op (%.2fx)\n",
        $name,
        number_format($bytes) . ' B',
        $jsoncUs,
        $bytes / $jsoncUs, // bytes/µs equals MB/s
        $baselineLabel,
        $nativeUs,
        $nativeUs > 0.0 ? $jsoncUs / $nativeUs : INF
    );
}

printf("PHP %s — JSONC::decode() benchmark (median of N iterations)\n\n", PHP_VERSION);

run('small-config', smallConfig(), 5_000, false);
run('comment-heavy-1mb', commentHeavyJsonc(1_000_000), 30, false);
run('trailing-commas-10k', trailingCommaHeavy(10_000), 50, false);
run('plain-json-1mb', largePlainJson(11_000), 30, true);
run('bom-plain-json-1mb', "\xEF\xBB\xBF" . largePlainJson(11_000), 30, true);
