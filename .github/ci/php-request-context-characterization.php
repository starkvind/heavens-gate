<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/http/request_context.php';

function hg_request_context_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function hg_request_context_same(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        hg_request_context_fail(
            $label . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual:   ' . var_export($actual, true)
        );
    }
}

$playerRequest = hg_request_context_from_query(['p' => 'seeplayer', 'b' => 'maurick']);
hg_request_context_same('seeplayer', hg_request_route($playerRequest), 'player route is explicit');
hg_request_context_same('maurick', hg_request_param($playerRequest, 'player'), 'legacy b becomes semantic player input');

$chapterRequest = hg_request_context_from_query(['p' => 'seechapter', 't' => 'capitulo-uno']);
hg_request_context_same('capitulo-uno', hg_request_param($chapterRequest, 'chapter'), 'legacy t becomes semantic chapter input');

$itemRequest = hg_request_context_from_query(['p' => 'verobj', 't' => 'armas', 'b' => 'klaive']);
hg_request_context_same('armas', hg_request_param($itemRequest, 'item_type'), 'inventory type is named');
hg_request_context_same('klaive', hg_request_param($itemRequest, 'item'), 'inventory item is named');

$groupRequest = hg_request_context_from_query(['p' => 'seegroup', 't' => '1', 'org' => 'justicia-metalica', 'b' => 'angeles-de-gaia']);
hg_request_context_same('1', hg_request_param($groupRequest, 'group_type'), 'group route preserves type discriminator');
hg_request_context_same('justicia-metalica', hg_request_param($groupRequest, 'organization'), 'group route names organization');
hg_request_context_same('angeles-de-gaia', hg_request_param($groupRequest, 'group'), 'group route names group');

$organizationRequest = hg_request_context_from_query(['p' => 'seegroup', 't' => '2', 'b' => 'justicia-metalica']);
hg_request_context_same('justicia-metalica', hg_request_param($organizationRequest, 'organization'), 'organization route names organization entity');
hg_request_context_same('', hg_request_param($organizationRequest, 'group'), 'organization route does not invent group input');

$orgChartRequest = hg_request_context_from_query(['p' => 'org_chart']);
hg_request_context_same('justicia-metalica', hg_request_param($orgChartRequest, 'organization'), 'org chart keeps historical default explicitly');

$filterRequest = hg_request_context_from_query(['p' => 'temp_order', 'order' => 'chronological', 'page' => 2]);
hg_request_context_same('chronological', hg_request_query_param($filterRequest, 'order'), 'ordinary query filter is normalized explicitly');
hg_request_context_same('2', hg_request_query_param($filterRequest, 'page'), 'numeric query values are normalized as strings');

$arrayInput = hg_request_context_from_query(['p' => 'seeplayer', 'b' => ['bad'], 'filter' => ['bad']]);
hg_request_context_same('', hg_request_param($arrayInput, 'player'), 'non-scalar route input is rejected');
hg_request_context_same('', hg_request_query_param($arrayInput, 'filter'), 'non-scalar generic query input is rejected');

$requestSource = file_get_contents(__DIR__ . '/../../app/http/request_context.php');
if ($requestSource === false) {
    hg_request_context_fail('Cannot read request context source');
}
foreach (['$_GET', '$_POST', 'mysqli', '->query(', '->prepare('] as $forbidden) {
    if (strpos($requestSource, $forbidden) !== false) {
        hg_request_context_fail("Request context gained forbidden implicit/data dependency: {$forbidden}");
    }
}

foreach ([
    __DIR__ . '/../../app/controllers/playr/playr_page.php',
    __DIR__ . '/../../app/mobile/controllers/players.php',
] as $controllerPath) {
    $source = file_get_contents($controllerPath);
    if ($source === false) {
        hg_request_context_fail("Cannot read migrated controller: {$controllerPath}");
    }
    if (strpos($source, 'hg_request_param($hgRequest') === false) {
        hg_request_context_fail("Migrated player controller is not consuming explicit request context: {$controllerPath}");
    }
    foreach (['$_GET', '$_POST'] as $forbidden) {
        if (strpos($source, $forbidden) !== false) {
            hg_request_context_fail("Migrated player controller regained raw request global {$forbidden}: {$controllerPath}");
        }
    }
}

$indexSource = file_get_contents(__DIR__ . '/../../index.php');
if ($indexSource === false) {
    hg_request_context_fail('Cannot read index.php');
}
$requirePos = strpos($indexSource, 'app/http/request_context.php');
$buildPos = strpos($indexSource, '$hgRequest = hg_request_context_from_query($_GET);');
$mobilePos = strpos($indexSource, 'hg_should_render_mobile(hg_request_route($hgRequest))');
if ($requirePos === false || $buildPos === false || $mobilePos === false || !($requirePos < $buildPos && $buildPos < $mobilePos)) {
    hg_request_context_fail('index.php is not building explicit request context before desktop/mobile dispatch');
}

fwrite(STDOUT, "PHP request context characterization: OK\n");
