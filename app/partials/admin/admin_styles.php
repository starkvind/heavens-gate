<?php
// admin_styles.php - helpers y CSS comun del panel de administracion
if (!function_exists('admin_panel_open')) {
    function admin_panel_open(string $title, string $actionsHtml = ''): void {
        echo "<br />";
        echo "<div class='panel-wrap'>";
        echo "<div class='hdr'>";
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
<link rel="stylesheet" href="/assets/css/admin.css">
