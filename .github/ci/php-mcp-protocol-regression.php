<?php
require_once __DIR__ . '/../../app/domains/api/mcp_protocol.php';
$tests = [];
$read = static function(array $params): array { return ['ok'=>true,'resource'=>$params['resource'] ?? 'catalog','items'=>[]]; };
$init = hg_mcp_dispatch(['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize','params'=>['protocolVersion'=>'2025-06-18']], $read);
$ping = hg_mcp_dispatch(['jsonrpc'=>'2.0','id'=>9,'method'=>'ping'], $read);
$tests['ping object without type error'] = ($ping['jsonrpc'] ?? '') === '2.0'
    && ($ping['id'] ?? null) === 9
    && ($ping['result'] instanceof stdClass)
    && json_encode($ping['result']) === '{}';
$tests['initialize'] = ($init['result']['protocolVersion'] ?? '') === '2025-06-18';
$tools = hg_mcp_dispatch(['jsonrpc'=>'2.0','id'=>2,'method'=>'tools/list'], $read);
$tests['tools list'] = ($tools['result']['tools'][0]['name'] ?? '') === 'heavens_gate_content';
$call = hg_mcp_dispatch(['jsonrpc'=>'2.0','id'=>3,'method'=>'tools/call','params'=>['name'=>'heavens_gate_content','arguments'=>['resource'=>'characters','limit'=>2]]], $read);
$tests['tools call read-only'] = ($call['result']['structuredContent']['resource'] ?? '') === 'characters';
$invalid = hg_mcp_dispatch(['jsonrpc'=>'2.0','id'=>4,'method'=>'tools/call','params'=>['name'=>'heavens_gate_content','arguments'=>['limit'=>500]]], $read);
$tests['limit validation'] = ($invalid['error']['code'] ?? null) === -32602;
$unknown = hg_mcp_dispatch(['jsonrpc'=>'2.0','id'=>5,'method'=>'dangerous/write'], $read);
$tests['unknown methods forbidden'] = ($unknown['error']['code'] ?? null) === -32601;
$notification = hg_mcp_dispatch(['jsonrpc'=>'2.0','method'=>'notifications/initialized'], $read);
$tests['notification response suppressed'] = $notification === null;
foreach ($tests as $name=>$ok) {
    if (!$ok) {fwrite(STDERR,'FAIL: '.$name.PHP_EOL);exit(1);}
    echo 'PASS: '.$name.PHP_EOL;
}
