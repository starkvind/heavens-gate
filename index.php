<?php
require_once __DIR__ . '/app/http/output.php';
hg_output_bootstrap_html();

$T_inicio = microtime(true);

//include("ip.php");
require_once __DIR__ . '/app/helpers/db_connection.php';
require_once __DIR__ . '/app/bootstrap/runtime.php';
require_once __DIR__ . '/app/routing/request_runtime.php';
require_once __DIR__ . '/app/http/request_context.php';
require_once __DIR__ . '/app/helpers/mobile_detection.php';
require_once __DIR__ . '/app/helpers/page_assets.php';

$hgRuntimeConfig = hg_bootstrap_runtime_config($link);
hg_bootstrap_apply_error_reporting($hgRuntimeConfig);
$excludeChronicles = (string)($hgRuntimeConfig['exclude_chronicles'] ?? 'FALSE');

$pageTitle = "Heaven's Gate";
$unknownOrigin = '-';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$uri = $_SERVER['REQUEST_URI'];
$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');

$pageURL = $scheme . '://' . $host . $uri;
$baseURL = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

$hgQuery = hg_request_routing_bootstrap($link, $uri, $_GET, $method);
$hgBody = $_POST;
$hgRequest = hg_request_context_from_query($hgQuery, $hgBody);

if (hg_should_render_mobile(
    hg_request_route($hgRequest),
    hg_request_query_param($hgRequest, 'view')
)) {
    include __DIR__ . '/app/mobile/mobile_index.php';
    exit;
}

ob_start();
include __DIR__ . '/app/http/page_dispatch.php';
$pageContent = hg_normalize_utf8_output((string)ob_get_clean());

if (!empty($isBarePage)) {
    echo $pageContent;
    exit;
}

require_once __DIR__ . '/app/presentation/desktop_context.php';
$bodyThemeClass = hg_desktop_theme_class($hgRequest);
$desktopMobileViewUrl = hg_view_switch_url((string)($_SERVER['REQUEST_URI'] ?? '/'), 'mobile');

include __DIR__ . '/app/views/layout/desktop.php';
