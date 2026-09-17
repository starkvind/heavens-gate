<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

function hg_monolith_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$contracts = [
    'app/controllers/bio/bio_page.php' => [
        'max_lines' => 80,
        'required' => [
            "bio_page_prepare.php",
            "biography_export.php",
            "views/characters/biography.php",
        ],
    ],
    'app/controllers/bio/bio_pack_page.php' => [
        'max_lines' => 220,
        'required' => [
            "domains/organizations/queries.php",
            "views/organizations/detail.php",
        ],
    ],
    'app/mobile/controllers/character_detail.php' => [
        'max_lines' => 40,
        'required' => [
            "character_detail_data.php",
            "views/character_detail.php",
        ],
    ],
];

foreach ($contracts as $relative => $contract) {
    $path = $root . '/' . $relative;
    $source = file_get_contents($path);
    if ($source === false) {
        hg_monolith_fail("Cannot read {$relative}");
    }
    $lines = substr_count($source, "\n") + 1;
    if ($lines > $contract['max_lines']) {
        hg_monolith_fail("{$relative} grew back into a monolith: {$lines} lines");
    }
    foreach ($contract['required'] as $needle) {
        if (strpos($source, $needle) === false) {
            hg_monolith_fail("{$relative} lost ownership seam: {$needle}");
        }
    }
    foreach (['mysqli_query(', 'mysqli_prepare(', '->query(', '->prepare('] as $forbidden) {
        if (strpos($source, $forbidden) !== false) {
            hg_monolith_fail("{$relative} regained direct SQL: {$forbidden}");
        }
    }
}

$sqlFree = [
    'app/controllers/bio/bio_page_prepare.php',
    'app/presentation/characters/biography_export.php',
    'app/views/characters/biography.php',
    'app/views/organizations/detail.php',
    'app/mobile/views/character_detail.php',
];

foreach ($sqlFree as $relative) {
    $source = file_get_contents($root . '/' . $relative);
    if ($source === false) {
        hg_monolith_fail("Cannot read {$relative}");
    }
    foreach (['mysqli_query(', 'mysqli_prepare(', '->query(', '->prepare('] as $forbidden) {
        if (strpos($source, $forbidden) !== false) {
            hg_monolith_fail("Presentation/preparation layer regained direct SQL: {$relative}");
        }
    }
}

$desktopView = file_get_contents($root . '/app/views/characters/biography.php');
if ($desktopView === false || strpos($desktopView, 'class=\'bioLayout\'') === false) {
    hg_monolith_fail('Desktop biography view lost the classic biography layout marker');
}

$organizationView = file_get_contents($root . '/app/views/organizations/detail.php');
if ($organizationView === false || strpos($organizationView, 'bio-pack-copy-md-btn') === false) {
    hg_monolith_fail('Organization detail view lost Markdown copy behavior');
}

$mobileView = file_get_contents($root . '/app/mobile/views/character_detail.php');
if ($mobileView === false || strpos($mobileView, 'class="hg-mobile-bio"') === false) {
    hg_monolith_fail('Mobile biography view lost the mobile biography layout marker');
}

$mobileData = file_get_contents($root . '/app/mobile/controllers/character_detail_data.php');
if ($mobileData === false || strpos($mobileData, '$mobileCharacterDetailReady = true;') === false) {
    hg_monolith_fail('Mobile biography data preparation no longer signals successful completion');
}

fwrite(STDOUT, "PHP public monolith characterization: OK\n");
