<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This tool is CLI-only.\n";
    exit;
}

require_once __DIR__ . '/../app/tools/seed_game_cards.php';

exit(hg_gc_seed_cli_main($argv ?? []));
