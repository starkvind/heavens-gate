<?php

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'success' => false,
    'error' => 'gone',
    'message' => 'El juego de cartas ha sido retirado.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
