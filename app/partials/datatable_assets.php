<?php
if (!defined('HG_DATATABLE_ASSETS_INCLUDED')) {
    define('HG_DATATABLE_ASSETS_INCLUDED', true);

    if (function_exists('hg_page_register_stylesheet')) {
        hg_page_register_stylesheet('/assets/vendor/datatables/jquery.dataTables.min.css');
        hg_page_register_stylesheet('/assets/css/hg-datatables.css');
    } else {
        // Bare/mobile compatibility until their asset pipelines are migrated.
        echo '<link rel="stylesheet" href="/assets/vendor/datatables/jquery.dataTables.min.css">' . "\n";
        echo '<link rel="stylesheet" href="/assets/css/hg-datatables.css">' . "\n";
    }

    $hgDataTablesJs = '/assets/js/hg-datatables.js';
    $hgDataTablesJsPath = dirname(__DIR__, 2) . $hgDataTablesJs;
    if (is_file($hgDataTablesJsPath)) {
        $hgDataTablesJs .= '?v=' . filemtime($hgDataTablesJsPath);
    }

    echo '<script src="/assets/vendor/jquery/jquery-3.7.1.min.js"></script>' . "\n";
    echo '<script src="/assets/vendor/datatables/jquery.dataTables.min.js"></script>' . "\n";
    echo '<script src="' . htmlspecialchars($hgDataTablesJs, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
}
?>