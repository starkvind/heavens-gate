<?php
// Editorial spoiler layers: authenticated Admin only, hidden by default.
require_once __DIR__ . '/../../helpers/admin_ajax.php';
require_once __DIR__ . '/../../domains/spoilers/admin.php';
if (!hg_admin_require_session(false)) {
    hg_admin_render_error('Acceso restringido', 'Se requiere una sesión de administración.', 403);
    return;
}
if (!hg_admin_require_db($link)) return;
include_once __DIR__ . '/../../partials/admin/admin_styles.php';

if (!function_exists('hg_admin_spoiler_h')) {
    function hg_admin_spoiler_h($text): string
    {
        return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!hg_spoilers_admin_schema_ready($link)) {
    admin_panel_open('Revelaciones', '');
    echo '<p>El módulo todavía no está habilitado: faltan las tablas de spoilers o su esquema es incompatible.</p>';
    echo '<p>Por seguridad, el administrador no puede guardar ni publicar capas hasta completar la migración verificada del issue #72.</p>';
    admin_panel_close();
    return;
}

$csrfKey = 'csrf_admin_spoilers';
$csrf = hg_admin_ensure_csrf_token($csrfKey);
$flash = [];
$characterId = max(0, (int)hg_request_query_param($hgRequest, 'character'));
$editId = max(0, (int)hg_request_query_param($hgRequest, 'edit'));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $token = hg_request_body_value($hgRequest, 'csrf');
    if (!hg_admin_csrf_valid($token, $csrfKey)) {
        $flash[] = ['ok' => false, 'message' => 'CSRF inválido. Recarga el editor.'];
    } else {
        $action = hg_request_body_value($hgRequest, 'action');
        $layerId = max(0, (int)hg_request_body_param($hgRequest, 'layer_id'));
        if ($action === 'save_layer') {
            $result = hg_spoilers_admin_save_layer(
                $link,
                $layerId,
                max(0, (int)hg_request_body_param($hgRequest, 'character_id')),
                hg_request_body_value($hgRequest, 'title'),
                hg_request_body_value($hgRequest, 'content_text')
            );
            if (!empty($result['ok'])) {
                $editId = (int)$result['id'];
                $characterId = (int)$result['character_id'];
            }
        } elseif ($action === 'publish' || $action === 'unpublish') {
            $result = hg_spoilers_admin_publish($link, $layerId, $action === 'publish');
            if (!empty($result['ok'])) {
                $characterId = (int)$result['character_id'];
                $editId = $layerId;
            }
        } elseif ($action === 'add_relation') {
            $result = hg_spoilers_admin_add_relation(
                $link,
                $layerId,
                max(0, (int)hg_request_body_param($hgRequest, 'target_id')),
                hg_request_body_value($hgRequest, 'relation_type'),
                hg_request_body_value($hgRequest, 'tag'),
                (int)hg_request_body_param($hgRequest, 'importance'),
                hg_request_body_value($hgRequest, 'description'),
                hg_request_body_value($hgRequest, 'arrows')
            );
            if (!empty($result['ok'])) {
                $characterId = (int)$result['character_id'];
                $editId = $layerId;
            }
        } elseif ($action === 'delete_relation') {
            $result = hg_spoilers_admin_delete_relation(
                $link,
                $layerId,
                max(0, (int)hg_request_body_param($hgRequest, 'relation_id'))
            );
            if (!empty($result['ok'])) {
                $characterId = (int)$result['character_id'];
                $editId = $layerId;
            }
        } else {
            $result = ['ok' => false, 'message' => 'Operación desconocida.'];
        }

        $flash[] = $result;
        if (!empty($result['ok']) && function_exists('hg_content_touch')) {
            $owner = max(0, (int)($result['character_id'] ?? $characterId));
            hg_content_touch($link, 'character', $owner);
            if ($action === 'add_relation' || $action === 'delete_relation') {
                // The linked character's page also changes when public.
                $target = max(0, (int)hg_request_body_param($hgRequest, 'target_id'));
                if ($target > 0) hg_content_touch($link, 'character', $target);
            }
        }
    }
}

$editing = $editId > 0 ? hg_spoilers_admin_get($link, $editId) : null;
if ($editing) $characterId = (int)$editing['entity_id'];
$characters = hg_spoilers_admin_characters($link);
$layers = hg_spoilers_admin_list($link, $characterId);
$relations = $editing ? hg_spoilers_admin_list_relations($link, (int)$editing['id']) : [];
$characterNameById = [];
foreach ($characters as $character) $characterNameById[(int)$character['id']] = (string)$character['name'];

admin_panel_open('Revelaciones / Spoilers', '<a class="btn" href="/talim?s=admin_spoilers">Todas las capas</a>');
?>
<p class="adm-color-muted">Estas capas son independientes de la biografía pública. Las capas nuevas nacen <strong>ocultas</strong>. Solo publicar desde aquí hace visibles sus textos y relaciones en las fichas y mapas públicos.</p>

<?php foreach ($flash as $notice): ?>
    <div class="flash">
        <div class="<?= !empty($notice['ok']) ? 'ok' : 'err' ?>"><?= hg_admin_spoiler_h($notice['message'] ?? 'Error desconocido') ?></div>
    </div>
<?php endforeach; ?>

<form method="get" action="/talim">
    <input type="hidden" name="s" value="admin_spoilers">
    <label for="spoiler-character-filter">Filtrar por personaje</label>
    <select class="select" name="character" id="spoiler-character-filter">
        <option value="0">Todos los personajes</option>
        <?php foreach ($characters as $character): ?>
            <option value="<?= (int)$character['id'] ?>" <?= $characterId === (int)$character['id'] ? 'selected' : '' ?>><?= hg_admin_spoiler_h($character['name']) ?> (#<?= (int)$character['id'] ?>)</option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filtrar</button>
</form>

<h3><?= $editing ? 'Editar capa #' . (int)$editing['id'] : 'Nueva capa oculta' ?></h3>
<form method="post">
    <input type="hidden" name="csrf" value="<?= hg_admin_spoiler_h($csrf) ?>">
    <input type="hidden" name="action" value="save_layer">
    <input type="hidden" name="layer_id" value="<?= (int)($editing['id'] ?? 0) ?>">
    <?php if ($editing): ?>
        <p>Personaje: <strong><?= hg_admin_spoiler_h($characterNameById[$characterId] ?? ('#' . $characterId)) ?></strong></p>
        <input type="hidden" name="character_id" value="<?= $characterId ?>">
    <?php else: ?>
        <label>Personaje
            <select class="select" name="character_id" required>
                <option value="">— Selecciona —</option>
                <?php foreach ($characters as $character): ?>
                    <option value="<?= (int)$character['id'] ?>" <?= $characterId === (int)$character['id'] ? 'selected' : '' ?>><?= hg_admin_spoiler_h($character['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php endif; ?>
    <label>Título de la revelación
        <input class="inp" type="text" name="title" maxlength="190" required value="<?= hg_admin_spoiler_h($editing['title'] ?? '') ?>" placeholder="Título que verá el lector tras la publicación">
    </label>
    <label>Texto adicional (texto plano; se respetan saltos de línea)
        <textarea class="ta" name="content_text" rows="10"><?= hg_admin_spoiler_h($editing['content_text'] ?? '') ?></textarea>
    </label>
    <p class="adm-color-muted">No pegues aquí información ya pública. Esta capa solo se muestra cuando se publica.</p>
    <button class="btn btn-green" type="submit"><?= $editing ? 'Guardar texto' : 'Crear capa oculta' ?></button>
</form>

<?php if ($editing): ?>
    <hr>
    <h3>Estado editorial</h3>
    <p>Esta capa está <strong><?= !empty($editing['is_published']) ? 'PUBLICADA' : 'OCULTA' ?></strong>.</p>
    <form method="post" onsubmit="return confirm('¿Confirmas el cambio global de visibilidad para todos los lectores?');">
        <input type="hidden" name="csrf" value="<?= hg_admin_spoiler_h($csrf) ?>">
        <input type="hidden" name="layer_id" value="<?= (int)$editing['id'] ?>">
        <input type="hidden" name="action" value="<?= !empty($editing['is_published']) ? 'unpublish' : 'publish' ?>">
        <button class="btn <?= !empty($editing['is_published']) ? 'btn-red' : 'btn-green' ?>" type="submit"><?= !empty($editing['is_published']) ? 'Volver a ocultar' : 'Publicar revelación' ?></button>
    </form>

    <hr>
    <h3>Relaciones de esta capa</h3>
    <p class="adm-color-muted">Origen: <?= hg_admin_spoiler_h($characterNameById[$characterId] ?? ('#' . $characterId)) ?>. Las relaciones solo aparecen en la web cuando esta capa está publicada. No se duplican en las relaciones públicas existentes.</p>
    <?php if ($relations): ?>
        <table class="table"><thead><tr><th>Destino</th><th>Relación</th><th>Descripción</th><th>Acción</th></tr></thead><tbody>
        <?php foreach ($relations as $relation): ?>
            <tr>
                <td><?= hg_admin_spoiler_h($relation['target_name']) ?></td>
                <td><?= hg_admin_spoiler_h($relation['relation_type']) ?></td>
                <td><?= hg_admin_spoiler_h($relation['description']) ?></td>
                <td><form method="post" onsubmit="return confirm('¿Eliminar esta relación de la capa?');">
                    <input type="hidden" name="csrf" value="<?= hg_admin_spoiler_h($csrf) ?>">
                    <input type="hidden" name="action" value="delete_relation">
                    <input type="hidden" name="layer_id" value="<?= (int)$editing['id'] ?>">
                    <input type="hidden" name="relation_id" value="<?= (int)$relation['id'] ?>">
                    <button class="btn btn-red" type="submit">Eliminar</button>
                </form></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= hg_admin_spoiler_h($csrf) ?>">
        <input type="hidden" name="action" value="add_relation">
        <input type="hidden" name="layer_id" value="<?= (int)$editing['id'] ?>">
        <label>Personaje relacionado
            <select class="select" name="target_id" required>
                <option value="">— Selecciona —</option>
                <?php foreach ($characters as $character): ?>
                    <?php if ((int)$character['id'] === $characterId) continue; ?>
                    <option value="<?= (int)$character['id'] ?>"><?= hg_admin_spoiler_h($character['name']) ?> (#<?= (int)$character['id'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Tipo de relación
            <input class="inp" type="text" name="relation_type" maxlength="100" required placeholder="Alianza, parentesco, enemistad…">
        </label>
        <label>Etiqueta
            <select class="select" name="tag">
                <option value="">Sin etiqueta</option>
                <option value="familia">Familia</option>
                <option value="amistad">Amistad</option>
                <option value="alianza">Alianza</option>
                <option value="conflicto">Conflicto</option>
            </select>
        </label>
        <label>Flechas
            <select class="select" name="arrows">
                <option value="to">Origen a destino</option>
                <option value="from">Destino a origen</option>
                <option value="to,from">Ambas direcciones</option>
                <option value="">Sin flechas</option>
            </select>
        </label>
        <label>Importancia (0-10)
            <input class="inp" type="number" name="importance" value="1" min="0" max="10">
        </label>
        <label>Descripción
            <textarea class="ta" name="description" rows="3"></textarea>
        </label>
        <button class="btn btn-green" type="submit">Añadir relación oculta</button>
    </form>
<?php endif; ?>

<hr>
<h3>Capas registradas<?= $characterId > 0 ? ' para este personaje' : '' ?></h3>
<table class="table">
    <thead><tr><th>Personaje</th><th>Revelación</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
        <?php if (!$layers): ?>
            <tr><td colspan="4">Todavía no hay capas registradas.</td></tr>
        <?php else: ?>
            <?php foreach ($layers as $layer): ?>
                <tr>
                    <td><?= hg_admin_spoiler_h($layer['character_name']) ?></td>
                    <td><?= hg_admin_spoiler_h($layer['title']) ?></td>
                    <td><?= !empty($layer['is_published']) ? 'Publicada' : 'Oculta' ?></td>
                    <td><a class="btn" href="/talim?s=admin_spoilers&amp;edit=<?= (int)$layer['id'] ?>">Editar / ver relaciones</a></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
<?php admin_panel_close(); ?>
