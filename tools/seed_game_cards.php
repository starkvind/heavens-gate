<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This tool is CLI-only.\n";
    exit;
}

require __DIR__ . '/../app/tools/seed_game_cards.php';
