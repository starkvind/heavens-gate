<?php

/**
 * Build a small, explicit request contract from the historical route params.
 *
 * This layer is deliberately pure: it receives arrays, reads no superglobals
 * and performs no database work. Legacy query keys such as b/t/tc remain
 * accepted at the edge, but controllers can consume semantic names instead.
 */
function hg_request_context_from_query(array $query, array $body = []): array
{
    $route = hg_request_context_scalar($query['p'] ?? '');
    $params = [];
    $normalizedQuery = hg_request_context_normalize_input($query);
    $normalizedBody = hg_request_context_normalize_input($body);

    $map = [
        'temp' => ['season' => 't'],
        'seechapter' => ['chapter' => 't'],
        'biogroup' => ['character_type' => 't'],
        'bio_worlds' => ['world' => 't'],
        'chronicles' => ['chronicle' => 't'],
        'bio_chronicles' => ['chronicle' => 't'],
        'chronicle_image' => ['chronicle' => 't'],
        'muestrabio' => ['character' => 'b'],
        'seeplayer' => ['player' => 'b'],
        'verdoc' => ['document' => 'b'],
        'inv' => ['item_type' => 't'],
        'inv_type' => ['item_type' => 't'],
        'seeitem' => ['item' => 'b', 'item_type' => 't'],
        'verobj' => ['item' => 'b', 'item_type' => 't'],
        'sistemas' => ['system' => 'b'],
        'verforma' => ['form' => 'b'],
        'versistdetalle' => ['system_detail' => 'b', 'detail_type' => 'tc'],
        'verrasgo' => ['trait' => 'b'],
        'vercondition' => ['condition' => 'b'],
        'veraction' => ['action' => 'b'],
        'vermyd' => ['merit_flaw' => 'b'],
        'vermaneu' => ['maneuver' => 'b'],
        'verarch' => ['archetype' => 'b'],
        'tipodon' => ['gift_type' => 'b'],
        'muestradon' => ['gift' => 'b'],
        'tiporite' => ['rite_type' => 'b'],
        'seerite' => ['rite' => 'b'],
        'tipototm' => ['totem_type' => 'b'],
        'muestratotem' => ['totem' => 'b'],
        'tipodisc' => ['discipline_type' => 'b'],
        'muestradisc' => ['discipline_power' => 'b'],
        'timeline_event' => ['event' => 't'],
        'maps_detail' => ['poi' => 'id'],
        'combat_simulator_log' => ['combat' => 'b'],
        'vercombat' => ['combat' => 'b'],
        'mentions' => ['mention_type' => 'type'],
        'talim' => ['admin_section' => 's'],
        'forum_message' => ['id' => 'id', 'palette' => 'palette'],
        'forum_diceroll' => ['id' => 'id', 'palette' => 'palette'],
        'forum_item' => ['id' => 'id'],
    ];

    foreach ($map[$route] ?? [] as $semanticName => $legacyName) {
        $params[$semanticName] = hg_request_context_scalar($query[$legacyName] ?? '');
    }

    if ($route === 'seegroup') {
        $groupType = hg_request_context_scalar($query['t'] ?? '');
        $params['group_type'] = $groupType;
        if ($groupType === '2') {
            $params['organization'] = hg_request_context_scalar($query['b'] ?? '');
        } else {
            $params['group'] = hg_request_context_scalar($query['b'] ?? '');
            $params['organization'] = hg_request_context_scalar($query['org'] ?? '');
        }
    }

    if ($route === 'org_chart') {
        $organization = hg_request_context_scalar($query['org'] ?? '');
        if ($organization === '') {
            $organization = hg_request_context_scalar($query['b'] ?? '');
        }
        if ($organization === '') {
            $organization = 'justicia-metalica';
        }
        $params['organization'] = $organization;
    }

    return [
        'route' => $route,
        'params' => $params,
        'query' => $normalizedQuery,
        'body' => $normalizedBody,
    ];
}

function hg_request_context_normalize_input(array $input): array
{
    $normalized = [];
    foreach ($input as $key => $value) {
        if (!is_string($key) && !is_int($key)) {
            continue;
        }
        $normalized[(string)$key] = hg_request_context_string($value);
    }
    return $normalized;
}

function hg_request_context_string(mixed $value): string
{
    if ($value === null || (!is_scalar($value) && !($value instanceof Stringable))) {
        return '';
    }

    return (string)$value;
}

function hg_request_context_scalar(mixed $value): string
{
    return trim(hg_request_context_string($value));
}

function hg_request_route(array $request): string
{
    return hg_request_context_scalar($request['route'] ?? '');
}

function hg_request_param(array $request, string $name, string $default = ''): string
{
    $params = $request['params'] ?? [];
    if (!is_array($params) || !array_key_exists($name, $params)) {
        return $default;
    }

    $value = hg_request_context_scalar($params[$name]);
    return $value === '' ? $default : $value;
}

function hg_request_query_param(array $request, string $name, string $default = ''): string
{
    $value = hg_request_query_value($request, $name, '');
    $value = hg_request_context_scalar($value);
    return $value === '' ? $default : $value;
}

function hg_request_query_value(array $request, string $name, string $default = ''): string
{
    $query = $request['query'] ?? [];
    if (!is_array($query) || !array_key_exists($name, $query)) {
        return $default;
    }

    return hg_request_context_string($query[$name]);
}

function hg_request_body_param(array $request, string $name, string $default = ''): string
{
    $value = hg_request_body_value($request, $name, '');
    $value = hg_request_context_scalar($value);
    return $value === '' ? $default : $value;
}

function hg_request_body_value(array $request, string $name, string $default = ''): string
{
    $body = $request['body'] ?? [];
    if (!is_array($body) || !array_key_exists($name, $body)) {
        return $default;
    }

    return hg_request_context_string($body[$name]);
}
