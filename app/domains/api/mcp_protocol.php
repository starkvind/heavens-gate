<?php
/**
 * Minimal stateless MCP Streamable HTTP JSON-RPC adapter. Read-only.
 * Keep the existing Content API as the sole source of domain data.
 */
function hg_mcp_tool_definitions(): array
{
    return [[
        'name' => 'heavens_gate_content',
        'description' => 'Read Heaven\'s Gate website information: catalog, characters, episodes, seasons, rules, powers, inventory and published forum topics. The website is not the canon authority; consult the continuity repository for authoritative decisions.',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'resource' => ['type' => 'string', 'description' => 'Content section; start with catalog to discover supported values.'],
                'q' => ['type' => 'string', 'maxLength' => 160, 'description' => 'Optional search text'],
                'id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Optional entity ID for detail'],
                'topic_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Forum topic ID; only approved topics can be read'],
                'page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10000],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 25],
            ],
            'additionalProperties' => false,
        ],
        'securitySchemes' => [['type' => 'oauth2', 'scopes' => ['content:read']]],
        'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false],
    ]];
}

function hg_mcp_reply(mixed $id, array|stdClass $result): array
{
    return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
}

function hg_mcp_error(mixed $id, int $code, string $message): array
{
    return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
}

function hg_mcp_dispatch(array $message, callable $readContent): ?array
{
    $method = $message['method'] ?? null;
    $hasId = array_key_exists('id', $message);
    $id = $message['id'] ?? null;
    if (($message['jsonrpc'] ?? null) !== '2.0' || !is_string($method)) {
        return hg_mcp_error($hasId ? $id : null, -32600, 'Invalid Request');
    }
    // JSON-RPC notifications have no response.
    if (!$hasId) return null;
    if ($method === 'initialize') {
        return hg_mcp_reply($id, [
            'protocolVersion' => '2025-06-18',
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => 'heavens-gate', 'version' => '0.1.0'],
        ]);
    }
    if ($method === 'ping') return hg_mcp_reply($id, new stdClass());
    if ($method === 'tools/list') return hg_mcp_reply($id, ['tools' => hg_mcp_tool_definitions()]);
    if ($method !== 'tools/call') return hg_mcp_error($id, -32601, 'Method not found');

    $params = $message['params'] ?? null;
    if (!is_array($params) || ($params['name'] ?? null) !== 'heavens_gate_content') {
        return hg_mcp_error($id, -32602, 'Unknown tool');
    }
    $args = $params['arguments'] ?? [];
    if (!is_array($args)) return hg_mcp_error($id, -32602, 'Invalid arguments');
    $safe = [];
    foreach (['resource', 'q', 'id', 'topic_id', 'page', 'limit'] as $key) {
        if (!array_key_exists($key, $args)) continue;
        $value = $args[$key];
        if (in_array($key, ['resource', 'q'], true)) {
            if (!is_string($value)) return hg_mcp_error($id, -32602, 'Invalid text parameter');
            if ($key === 'q' && strlen($value) > 160) return hg_mcp_error($id, -32602, 'Search too long');
        } elseif (!is_int($value) || $value < 1) {
            return hg_mcp_error($id, -32602, 'Invalid numeric parameter');
        }
        $safe[$key] = (string)$value;
    }
    if (isset($safe['limit']) && (int)$safe['limit'] > 25) return hg_mcp_error($id, -32602, 'Limit exceeds 25');
    if (isset($safe['page']) && (int)$safe['page'] > 10000) return hg_mcp_error($id, -32602, 'Page exceeds 10000');
    try {
        $data = $readContent($safe);
        if (!is_array($data)) throw new RuntimeException('Invalid data');
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($encoded)) throw new RuntimeException('Invalid JSON');
        return hg_mcp_reply($id, [
            'content' => [['type' => 'text', 'text' => $encoded]],
            'structuredContent' => $data,
            'isError' => ($data['ok'] ?? false) !== true,
        ]);
    } catch (Throwable $e) {
        error_log('HG MCP tool read failed: ' . $e->getMessage());
        return hg_mcp_reply($id, [
            'content' => [['type' => 'text', 'text' => '{"ok":false,"error":"service_unavailable"}']],
            'isError' => true,
        ]);
    }
}
