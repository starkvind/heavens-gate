<?php
// AJAX handlers for admin_characters.php

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../domains/characters/admin_queries.php');

if (!function_exists('hg_admin_characters_handle_ajax')) {
    function hg_admin_characters_handle_ajax(mysqli $link): bool
    {
        if (!isset($_GET['ajax']) || $_GET['ajax'] !== '1') {
            return false;
        }

        $mode = (string)($_GET['mode'] ?? '');
        // Delegar CRUD POST al controlador principal.
        if ($mode === '' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
            return false;
        }
        // Si no es un modo AJAX propio de este archivo, no interceptar.
        if ($mode !== 'details') {
            return false;
        }

        hg_admin_require_session(true);

        $id = max(0, (int)($_GET['id'] ?? 0));
        if ($id <= 0) {
            hg_admin_json_error('bad_id', 400, ['id' => 'required_positive'], null, ['mode' => $mode]);
        }

        $row = hg_characters_admin_fetch_ajax_details($link, $id);

        if (!$row) {
            hg_admin_json_error('not_found', 404, ['id' => 'not_found'], null, ['mode' => $mode, 'id' => $id]);
        }

        $legacyData = [
            'status'      => (string)($row['status'] ?? ''),
            'status_id'   => (int)($row['status_id'] ?? 0),
            'causamuerte' => '',
            'rango'       => (string)($row['rank'] ?? ''),
            'infotext'    => (string)($row['info_text'] ?? ''),
        ];

        // Keep legacy top-level fields while providing standard contract keys.
        $payload = array_merge([
            'ok' => true,
            'message' => 'OK',
            'msg' => 'OK',
            'data' => $legacyData,
            'errors' => [],
            'meta' => ['mode' => $mode, 'id' => $id],
        ], $legacyData);

        hg_admin_json_response($payload, 200);
        return true;
    }
}

