<?php
// Separate OAuth consent surface, reusing a valid /talim admin session.
require_once __DIR__ . '/../../helpers/tool_api.php';
require_once __DIR__ . '/../../helpers/admin_auth.php';
require_once __DIR__ . '/../../domains/api/mcp_oauth.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (hg_mcp_oauth_secret() === '') {
    http_response_code(503);
    echo '<h1>OAuth no configurado</h1>';
    return;
}
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    return;
}
hg_admin_session_start();
$admin = hg_admin_is_authenticated();
$esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$message = '';
if ($method === 'GET') {
    $params = [];
    foreach (['response_type','client_id','redirect_uri','code_challenge','code_challenge_method','resource','scope','state'] as $key) {
        $params[$key] = hg_request_query_value($hgRequest, $key, '');
    }
    $validated = hg_mcp_oauth_authorization_params($params);
    if ($validated === null) {
        http_response_code(400);
        echo '<h1>Solicitud OAuth inválida</h1>';
        return;
    }
    $csrf = bin2hex(random_bytes(24));
    $_SESSION['hg_mcp_oauth_pending'] = ['params' => $validated, 'csrf' => $csrf, 'expires' => time() + 600];
} else {
    $pending = $_SESSION['hg_mcp_oauth_pending'] ?? null;
    $csrf = hg_request_body_value($hgRequest, 'csrf', '');
    $consent = hg_request_body_value($hgRequest, 'consent', '');
    if (!$admin || !is_array($pending) || ($pending['expires'] ?? 0) < time()
        || !is_string($pending['csrf'] ?? null)
        || $csrf === '' || !hash_equals($pending['csrf'], $csrf)
        || !in_array($consent, ['approve', 'deny'], true)) {
        http_response_code(403);
        echo '<h1>Solicitud caducada o sesión no autorizada</h1>';
        return;
    }
    unset($_SESSION['hg_mcp_oauth_pending']);
    $params = $pending['params'];
    $result = ['iss' => HG_MCP_OAUTH_ISSUER];
    if ($consent === 'deny') {
        $result['error'] = 'access_denied';
    } else {
        $issued = hg_mcp_oauth_issue_code(hg_mcp_oauth_secret(), $params, time());
        if ($issued === false) {
            http_response_code(503);
            echo '<h1>Almacenamiento temporal OAuth no disponible</h1>';
            return;
        }
        $result['code'] = $issued;
    }
    if ($params['state'] !== '') $result['state'] = $params['state'];
    header('Location: ' . HG_MCP_OAUTH_REDIRECT . '?' . http_build_query($result, '', '&', PHP_QUERY_RFC3986), true, 302);
    return;
}
$pending = $_SESSION['hg_mcp_oauth_pending'];
echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<title>Autorizar Heaven\'s Gate</title>';
echo '<main style="max-width:34rem;margin:5rem auto;padding:2rem;color:#eee;background:#162034;font:16px system-ui;border-radius:12px">';
echo '<h1>Conectar ChatGPT a Heaven\'s Gate</h1>';
echo '<p>Solicita acceso de <strong>solo lectura</strong> a personajes, episodios, reglas y temas del foro publicados, mediante el permiso <code>content:read</code>.</p>';
if (!$admin) {
    echo '<p>Primero inicia sesión en <a style="color:#9dc6ff" href="/talim">la administración existente</a>, después vuelve a esta pestaña y recárgala. No se modifica el inicio de sesión de /talim.</p>';
} else {
    echo '<p>Has iniciado sesión como administrador. Puedes aprobar o rechazar la conexión.</p>';
    echo '<form method="post" action="/oauth/authorize">';
    echo '<input type="hidden" name="csrf" value="' . $esc((string)$pending['csrf']) . '">';
    echo '<button type="submit" name="consent" value="approve">Autorizar solo lectura</button> ';
    echo '<button type="submit" name="consent" value="deny">Rechazar</button></form>';
}
echo '</main></html>';
