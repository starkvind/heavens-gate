<?php

require_once __DIR__ . '/../domains/configuration/queries.php';

/**
 * Load the tiny set of application-wide runtime configuration values needed
 * before request dispatch. Bootstrap owns startup only; data access lives in
 * the configuration domain.
 */
function hg_bootstrap_runtime_config(mysqli $link): array
{
    return [
        'error_reporting' => hg_configuration_get_value($link, 'error_reporting', 'FALSE'),
        'exclude_chronicles' => hg_configuration_get_value($link, 'exclude_chronicles', 'FALSE'),
    ];
}

function hg_bootstrap_apply_error_reporting(array $config): void
{
    if (($config['error_reporting'] ?? 'FALSE') !== 'TRUE') {
        return;
    }

    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}
