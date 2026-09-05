<?php
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => false,
    'error' => 'gone',
    'message' => 'El juego de cartas ha sido retirado del sitio activo.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
