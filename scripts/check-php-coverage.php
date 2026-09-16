<?php

declare(strict_types=1);

/**
 * Advisory (or hard) line-coverage check against a Clover XML report.
 *
 * Usage: php scripts/check-php-coverage.php [clover-path] [min-percent]
 * Exit 0 when coverage >= min; exit 1 when below (or on I/O errors).
 */
$cloverPath = $argv[1] ?? 'coverage/clover.xml';
$minPercent = isset($argv[2]) ? (float) $argv[2] : 50.0;

if (! is_file($cloverPath)) {
    fwrite(STDERR, "Clover report not found: {$cloverPath}\n");
    exit(1);
}

$xml = @simplexml_load_file($cloverPath);
if ($xml === false) {
    fwrite(STDERR, "Failed to parse Clover report: {$cloverPath}\n");
    exit(1);
}

$metrics = $xml->project->metrics ?? null;
if ($metrics === null) {
    fwrite(STDERR, "Clover report missing project metrics: {$cloverPath}\n");
    exit(1);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percent = $statements > 0 ? round(100 * $covered / $statements, 2) : 0.0;

echo sprintf(
    "PHP line coverage: %.2f%% (%d/%d statements); advisory minimum: %.1f%%\n",
    $percent,
    $covered,
    $statements,
    $minPercent,
);

if ($percent < $minPercent) {
    fwrite(STDERR, "Coverage is below the advisory minimum.\n");
    exit(1);
}

exit(0);
