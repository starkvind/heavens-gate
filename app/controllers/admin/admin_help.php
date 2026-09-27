<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../domains/help/admin.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
include(__DIR__ . '/../../partials/admin/admin_styles.php');

if (!function_exists('hg_admin_help_h')) {
    function hg_admin_help_h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$csrfKey = 'csrf_admin_help';
$csrf = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($csrfKey)
    : ($_SESSION[$csrfKey] ?? ($_SESSION[$csrfKey] = bin2hex(random_bytes(16))));
$flash = [];

$crudAction = hg_request_body_param($hgRequest, 'crud_action');
if ($crudAction !== '') {
    $token = hg_request_body_value($hgRequest, 'csrf');
    $csrfOk = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, $csrfKey)
        : ($token !== '' && isset($_SESSION[$csrfKey]) && hash_equals((string)$_SESSION[$csrfKey], $token));

    if (!$csrfOk) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF inválido. Recarga la página.'];
    } elseif ($crudAction === 'delete') {
        $result = hg_help_admin_delete($link, (int)hg_request_body_param($hgRequest, 'id'));
        $flash[] = ['type' => !empty($result['ok']) ? 'ok' : 'error', 'msg' => (string)$result['message']];
    } elseif ($crudAction === 'save') {
        $result = hg_help_admin_save($link, [
            'id' => (int)hg_request_body_param($hgRequest, 'id'),
            'slug' => hg_request_body_value($hgRequest, 'slug'),
            'nav_label' => hg_request_body_value($hgRequest, 'nav_label'),
            'title' => hg_request_body_value($hgRequest, 'title'),
            'summary' => hg_request_body_value($hgRequest, 'summary'),
            'lead' => hg_request_body_value($hgRequest, 'lead'),
            'meta_description' => hg_request_body_value($hgRequest, 'meta_description'),
            'content_html' => hg_request_body_value($hgRequest, 'content_html'),
            'sort_order' => (int)hg_request_body_param($hgRequest, 'sort_order'),
            'is_published' => hg_request_body_has($hgRequest, 'is_published') ? 1 : 0,
        ]);
        $flash[] = ['type' => !empty($result['ok']) ? 'ok' : 'error', 'msg' => (string)$result['message']];
    }
}

$editId = max(0, (int)hg_request_query_param($hgRequest, 'edit'));
$editing = $editId > 0 ? hg_help_admin_fetch_one($link, $editId) : null;
$rows = hg_help_admin_fetch_rows($link);

admin_panel_open('Ayuda', '<a class="btn btn-green" href="/talim?s=admin_help">+ Nueva página</a>');
?>

<?php if (!empty($flash)): ?>
    <div class="flash">
        <?php foreach ($flash as $item): ?>
            <div class="<?= ($item['type'] ?? '') === 'ok' ? 'ok' : 'err' ?>"><?= hg_admin_help_h($item['msg'] ?? '') ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf" value="<?= hg_admin_help_h($csrf) ?>">
    <input type="hidden" name="crud_action" value="save">
    <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

    <div class="adm-grid-1-2">
        <label for="help_title">Título</label>
        <input class="inp" id="help_title" name="title" required value="<?= hg_admin_help_h($editing['title'] ?? '') ?>">

        <label for="help_slug">Slug</label>
        <input class="inp" id="help_slug" name="slug" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="mi-pagina-de-ayuda" value="<?= hg_admin_help_h($editing['slug'] ?? '') ?>">

        <label for="help_nav_label">Etiqueta</label>
        <input class="inp" id="help_nav_label" name="nav_label" value="<?= hg_admin_help_h($editing['nav_label'] ?? 'Guía') ?>">

        <label for="help_summary">Resumen de tarjeta</label>
        <textarea class="ta" id="help_summary" name="summary" rows="3"><?= hg_admin_help_h($editing['summary'] ?? '') ?></textarea>

        <label for="help_lead">Entradilla</label>
        <textarea class="ta" id="help_lead" name="lead" rows="3" required><?= hg_admin_help_h($editing['lead'] ?? '') ?></textarea>

        <label for="help_meta">Meta descripción</label>
        <textarea class="ta" id="help_meta" name="meta_description" rows="2"><?= hg_admin_help_h($editing['meta_description'] ?? '') ?></textarea>

        <label for="help_sort">Orden</label>
        <input class="inp" id="help_sort" name="sort_order" type="number" value="<?= (int)($editing['sort_order'] ?? 0) ?>">

        <label for="help_published">Publicada</label>
        <label><input id="help_published" name="is_published" type="checkbox" value="1" <?= !isset($editing['is_published']) || !empty($editing['is_published']) ? 'checked' : '' ?>> Visible en /help</label>

        <label for="help_content">Contenido HTML</label>
        <textarea class="ta" id="help_content" name="content_html" rows="32" required><?= hg_admin_help_h($editing['content_html'] ?? '') ?></textarea>
    </div>

    <p class="adm-color-muted">Las imágenes dentro de <code>hg-help-figure</code> se pueden ampliar al hacer clic. El contenido no admite JavaScript, iframes ni manejadores inline.</p>

    <div class="adm-flex-gap-8">
        <button class="btn btn-green" type="submit"><?= $editing ? 'Guardar cambios' : 'Crear página' ?></button>
        <?php if ($editing): ?><a class="btn" href="/talim?s=admin_help">Cancelar edición</a><?php endif; ?>
    </div>
</form>

<table class="table">
    <thead>
        <tr>
            <th>Orden</th>
            <th>Título</th>
            <th>Slug</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="5" class="adm-color-muted">No hay páginas de ayuda.</td></tr>
    <?php else: ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= (int)$row['sort_order'] ?></td>
                <td><?= hg_admin_help_h($row['title']) ?></td>
                <td><code><?= hg_admin_help_h($row['slug']) ?></code></td>
                <td><?= !empty($row['is_published']) ? 'Publicada' : 'Oculta' ?></td>
                <td>
                    <a class="btn" href="/talim?s=admin_help&amp;edit=<?= (int)$row['id'] ?>">Editar</a>
                    <a class="btn" href="/help/<?= rawurlencode((string)$row['slug']) ?>" target="_blank" rel="noopener">Ver</a>
                    <form method="post" class="adm-inline-form" onsubmit="return confirm('¿Eliminar esta página de ayuda?');">
                        <input type="hidden" name="csrf" value="<?= hg_admin_help_h($csrf) ?>">
                        <input type="hidden" name="crud_action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <button class="btn btn-red" type="submit">Borrar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<?php admin_panel_close(); ?>
