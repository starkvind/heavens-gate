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

    echo '<script src="/assets/vendor/jquery/jquery-3.7.1.min.js"></script>' . "\n";
    echo '<script src="/assets/vendor/datatables/jquery.dataTables.min.js"></script>' . "\n";
    echo '<script src="/assets/js/hg-datatables.js"></script>' . "\n";
}
?>