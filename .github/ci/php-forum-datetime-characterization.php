<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/forum_datetime.php';

function hg_forum_datetime_expect(bool $condition, string $description): void
{
    if (!$condition) {
        fwrite(STDERR, 'Forum datetime regression: ' . $description . PHP_EOL);
        exit(1);
    }
}

// The web server currently defaults to UTC. Output must not depend on that setting.
date_default_timezone_set('UTC');

$cases = [
    // Winter and summer standard dates.
    ['2026-01-15T12:00:00+00:00', '15/01/2026 · 13:00 CET', '2026-01-15T13:00:00+01:00'],
    ['2026-07-15T12:00:00+00:00', '15/07/2026 · 14:00 CEST', '2026-07-15T14:00:00+02:00'],
    // Spring DST jump: the clock goes from 01:59:59 to 03:00:00.
    ['2026-03-29T00:59:59+00:00', '29/03/2026 · 01:59 CET', '2026-03-29T01:59:59+01:00'],
    ['2026-03-29T01:00:00+00:00', '29/03/2026 · 03:00 CEST', '2026-03-29T03:00:00+02:00'],
    // Autumn DST repeat: the 02:00 hour occurs twice with different offsets.
    ['2026-10-25T00:59:59+00:00', '25/10/2026 · 02:59 CEST', '2026-10-25T02:59:59+02:00'],
    ['2026-10-25T01:00:00+00:00', '25/10/2026 · 02:00 CET', '2026-10-25T02:00:00+01:00'],
];

foreach ($cases as [$utc, $display, $iso]) {
    $timestamp = (new DateTimeImmutable($utc))->getTimestamp();
    hg_forum_datetime_expect(
        hg_forum_format_post_time($timestamp) === $display,
        "display format for {$utc}"
    );
    hg_forum_datetime_expect(
        hg_forum_format_post_time($timestamp, 'c') === $iso,
        "datetime attribute for {$utc}"
    );
}

// The formatter must be independent from the system / PHP default timezone.
date_default_timezone_set('Pacific/Honolulu');
$timestamp = (new DateTimeImmutable('2026-10-17T13:48:40+00:00'))->getTimestamp();
hg_forum_datetime_expect(
    hg_forum_format_post_time($timestamp, 'Y-m-d H:i:s T') === '2026-10-17 15:48:40 CEST',
    'forum reader and quote labels use Madrid time despite PHP default timezone'
);
hg_forum_datetime_expect(hg_forum_format_post_time(0) === '', 'unset timestamp has no label');
hg_forum_datetime_expect(hg_forum_format_post_time(-1, 'c') === '', 'invalid timestamp has no datetime value');

// Keep all public forum surfaces wired to the same formatter.
$root = dirname(__DIR__, 2);
foreach ([
    'app/controllers/main/main_home.php' => ["hg_forum_format_post_time(\$postedAt)", "hg_forum_format_post_time(\$postedAt, 'c')"],
    'app/mobile/controllers/home.php' => ["hg_forum_format_post_time(\$postedAt)", "hg_forum_format_post_time(\$postedAt, 'c')"],
    'app/tools/forum_topic_viewer_tool.php' => [
        "hg_forum_format_post_time(\$posterTime",
        "hg_forum_format_post_time(\$tocPosterTime",
        "hg_forum_format_post_time(\$unix",
    ],
] as $path => $needles) {
    $source = file_get_contents($root . '/' . $path);
    hg_forum_datetime_expect($source !== false, "source exists: {$path}");
    hg_forum_datetime_expect(str_contains($source, 'forum_datetime.php'), "shared formatter included in {$path}");
    foreach ($needles as $needle) {
        hg_forum_datetime_expect(str_contains($source, $needle), "formatter used in {$path}: {$needle}");
    }
}

fwrite(STDOUT, "Forum datetime characterization: OK\n");
