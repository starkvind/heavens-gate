<?php
// admin_styles.php - helpers y CSS comun del panel de administracion
if (!function_exists('admin_panel_open')) {
    function admin_panel_open(string $title, string $actionsHtml = ''): void {
        echo "<div class='panel-wrap adm-panel'>";
        echo "<div class='hdr adm-panel-header'>";
        echo "<h2>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</h2>";
        echo "<a class='btn' href='/talim'>&larr; Panel</a>";
        if ($actionsHtml !== '') {
            echo $actionsHtml;
        }
        echo "</div>";
    }

    function admin_panel_close(): void {
        echo "</div>";
    }
}
?>
