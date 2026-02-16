<?php

setMetaFromPage("Personajes | Heaven's Gate", "Listado completo de personajes.", null, 'website');

include("app/partials/main_nav_bar.php");



if (!$link) {

    die("Error de conexión a la base de datos: " . mysqli_connect_error());

}



// Sanitiza "1,2, 3" -> "1,2,3" (solo ints). Si queda vacío, devuelve ""

function sanitize_int_csv($csv){

    $csv = (string)$csv;

    if (trim($csv) === '') return '';

    $parts = preg_split('/\s*,\s*/', trim($csv));

    $ints = [];

    foreach ($parts as $p) {

        if ($p === '') continue;

        if (preg_match('/^\d+$/', $p)) $ints[] = (string)(int)$p;

    }

    $ints = array_values(array_unique($ints));

    return implode(',', $ints);

}



// EXCLUSIONES (si existe la variable global, la usamos; si no, mantenemos 2,7)

$excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '2,7';

$whereChron = ($excludeChronicles !== '') ? "p.chronicle_id NOT IN ($excludeChronicles)" : "1=1";



  // Cargar personajes con la query original (AHORA CON BRIDGES)

  $typeCol = 'character_type_id';

  $query = "

      SELECT 

          p.id, p.pretty_id AS character_pretty_id, p.name AS character_name, p.alias, p.concepto, p.img,



        -- MANADA del PJ (bridge personaje->grupo)

        nm2.id   AS pack_id,

        nm2.pretty_id AS pack_pretty_id,

        nm2.name AS pack_name,



        -- CLAN del PJ (bridge personaje->clan)

        nc2.id   AS clan_id,

        nc2.pretty_id AS clan_pretty_id,

        nc2.name AS clan_name,



        -- (Opcional) clan derivado de la manada (por si el PJ no tiene clan asignado)

        nc_from_pack.id   AS clan_from_pack_id,

        nc_from_pack.pretty_id AS clan_from_pack_pretty_id,

        nc_from_pack.name AS clan_from_pack_name,



          a.id AS type_id, a.pretty_id AS type_pretty_id, a.kind AS type_name,

          s.name AS system_name, p.system_name AS system_legacy, p.estado

      FROM fact_characters p



        -- Bridge: personaje -> manada

        LEFT JOIN bridge_characters_groups hcg

            ON hcg.character_id = p.id

           AND (hcg.is_active = 1 OR hcg.is_active IS NULL)

        LEFT JOIN dim_groups nm2

            ON nm2.id = hcg.group_id



        -- Bridge: personaje -> clan

        LEFT JOIN bridge_characters_organizations hcc

            ON hcc.character_id = p.id

           AND (hcc.is_active = 1 OR hcc.is_active IS NULL)

        LEFT JOIN dim_organizations nc2

            ON nc2.id = hcc.clan_id



        -- Bridge: manada -> clan (fallback / coherencia)

        LEFT JOIN bridge_organizations_groups hcg2

            ON hcg2.group_id = nm2.id

           AND (hcg2.is_active = 1 OR hcg2.is_active IS NULL)

        LEFT JOIN dim_organizations nc_from_pack

            ON nc_from_pack.id = hcg2.clan_id



        LEFT JOIN dim_character_types a ON a.id = p.$typeCol

        LEFT JOIN dim_systems s ON s.id = p.system_id



    WHERE $whereChron

    ORDER BY p.name ASC

";

  $result = mysqli_query($link, $query);

  if (!$result) {

      $err = mysqli_error($link);

      if (stripos($err, "Unknown column 'p.character_type_id'") !== false || stripos($err, "Unknown column `p`.`character_type_id`") !== false) {

          $typeCol = 'kind';
          $query = str_replace('p.character_type_id', 'p.kind', $query);
          $result = mysqli_query($link, $query);

          if (!$result) {
              $err2 = mysqli_error($link);
              if (stripos($err2, "Unknown column 'p.kind'") !== false || stripos($err2, "Unknown column `p`.`kind`") !== false) {
                  $typeCol = 'tipo';
                  $query = str_replace('p.kind', 'p.tipo', $query);
                  $result = mysqli_query($link, $query);
                  if (!$result) {
                      die("Error en consulta: " . mysqli_error($link));
                  }
              } else {
                  die("Error en consulta: " . $err2);
              }
          }

      } else {

          die("Error en consulta: " . $err);

      }

  }



$personajes = [];

while ($row = mysqli_fetch_assoc($result)) {



    // Si no hay clan directo del PJ, usamos el clan derivado de su manada

    if (empty($row['clan_id']) && !empty($row['clan_from_pack_id'])) {

        $row['clan_id']   = $row['clan_from_pack_id'];

        $row['clan_name'] = $row['clan_from_pack_name'];

        $row['clan_pretty_id'] = $row['clan_from_pack_pretty_id'];

    }



    // Limpieza de columnas auxiliares (no las necesitamos en JS)

    unset($row['clan_from_pack_id'], $row['clan_from_pack_name'], $row['clan_from_pack_pretty_id']);



    $personajes[] = $row;

}

mysqli_free_result($result);



function ensure_utf8($value) {

    if (is_string($value)) {

        if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {

            if (function_exists('mb_convert_encoding')) {

                return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');

            }

            if (function_exists('utf8_encode')) {

                return utf8_encode($value);

            }

        }

        return $value;

    }

    if (is_array($value)) {

        foreach ($value as $k => $v) {

            $value[$k] = ensure_utf8($v);

        }

        return $value;

    }

    return $value;

}



$personajes = ensure_utf8($personajes);



$pageSect = "Lista de personajes - Biografías";

?>



<link rel="stylesheet" href="assets/vendor/datatables/jquery.dataTables.min.css">

<script src="assets/vendor/jquery/jquery-3.7.1.min.js"></script>

<script src="assets/vendor/datatables/jquery.dataTables.min.js"></script>



<style>

/* Toolbar: Multi-checks (izq) + Buscar DT (dcha) */

.dt-toolbar{

	display:flex;

	align-items:center;

	justify-content:space-between;

	gap:10px;

	margin: 0 0 10px 0;

	flex-wrap: wrap;

}

.dt-toolbar .left{

	flex: 0 0 auto;

	display:flex;

	align-items:center;

	gap:10px;

	flex-wrap: wrap;

}

.dt-toolbar .right{ flex: 1 1 auto; display:flex; justify-content:flex-end; }



/* ===== Multi-select con checks ===== */

.ms-wrap{ position:relative; width: 150px; }

.ms-btn{

	width:100%;

	box-sizing:border-box;

	cursor:pointer;

	display:flex;

	justify-content:space-between;

	align-items:center;

	background:#fff;

}

.ms-btn .ms-label{ opacity:.9; }

.ms-btn .ms-summary{ opacity:.8; margin-left:10px; }



.ms-panel{

	text-align:left;

	position:absolute;

	z-index:9999;

	top: calc(100% + 6px);

	left:0; right:0;

	border:1px solid rgb(0, 0, 153);

	background: rgb(0, 0, 102);

	border-radius:6px;

	box-shadow:0 8px 24px rgba(0,0,0,.12);

	padding:4px;

	display:none;

	max-height:280px;

	overflow:auto;

}

.ms-row{

	display:flex;

	align-items:center;

	background: rgb(0, 0, 102);

	gap:10px;

	padding:6px 8px;

	border-radius:4px;

	cursor:pointer;

	margin:0;

}

.ms-row:hover{ background: rgba(0,0,0,.04); }

.ms-row input{ width:16px; height:16px; }



.ms-row span{ text-align:left; }



.ms-actions{

	display:flex;

	gap:8px;

}

.ms-actions button{

	font-family: verdana;

	font-size: 10px;

	background-color: #000066;

	color: #fff;

	padding: 0.5em;

	border: 1px solid #000099;

}

.ms-actions button:hover{ border-color: #003399; background: #000099; color: #01b3fa; cursor: pointer; }



/* DataTables filter alineado */

.dataTables_wrapper .dataTables_filter{ margin:0 !important; }

.dataTables_wrapper .dataTables_filter label{

	display:flex;

	align-items:center;

	gap:8px;

	margin:0;

	white-space:nowrap;

}

.dataTables_wrapper .dataTables_filter input{ margin-left:0 !important; }



@media (max-width: 720px){

	.dt-toolbar{ flex-direction:column; align-items:stretch; }

	.dt-toolbar .left{ justify-content:flex-start; }

	.dt-toolbar .right{ justify-content:flex-start; }

	.ms-wrap{ width: 100%; }

}

</style>



<h2 style="text-align:right;">Lista de personajes</h2>



<div style="display:flex; justify-content:center; width: 100%;">

  <div style="flex: 1; max-width:640px; min-width:640px;">

    <div class="dt-toolbar">

      <div class="left">

        <div class="ms-wrap" id="filter-pack">

          <div class="ms-btn" id="ms-toggle-pack" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">

            <span class="ms-label">Grupo</span>

            <span class="ms-summary" id="ms-summary-pack">Todos</span>

          </div>

          <div class="ms-panel" id="ms-panel-pack" aria-hidden="true">

            <div id="ms-options-pack"></div>

            <div class="ms-actions">

              <button type="button" id="ms-select-all-pack">Todo</button>

              <button type="button" id="ms-clear-pack">Limpiar</button>

            </div>

          </div>

        </div>



        <div class="ms-wrap" id="filter-clan">

          <div class="ms-btn" id="ms-toggle-clan" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">

            <span class="ms-label">Organización</span>

            <span class="ms-summary" id="ms-summary-clan">Todas</span>

          </div>

          <div class="ms-panel" id="ms-panel-clan" aria-hidden="true">

            <div id="ms-options-clan"></div>

            <div class="ms-actions">

              <button type="button" id="ms-select-all-clan">Todo</button>

              <button type="button" id="ms-clear-clan">Limpiar</button>

            </div>

          </div>

        </div>



        <div class="ms-wrap" id="filter-system">

          <div class="ms-btn" id="ms-toggle-system" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">

            <span class="ms-label">Sistema</span>

            <span class="ms-summary" id="ms-summary-system">Todos</span>

          </div>

          <div class="ms-panel" id="ms-panel-system" aria-hidden="true">

            <div id="ms-options-system"></div>

            <div class="ms-actions">

              <button type="button" id="ms-select-all-system">Todo</button>

              <button type="button" id="ms-clear-system">Limpiar</button>

            </div>

          </div>

        </div>

	</div>

	<div class="left">



        <div class="ms-wrap" id="filter-type">

          <div class="ms-btn" id="ms-toggle-type" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">

            <span class="ms-label">Tipo</span>

            <span class="ms-summary" id="ms-summary-type">Todos</span>

          </div>

          <div class="ms-panel" id="ms-panel-type" aria-hidden="true">

            <div id="ms-options-type"></div>

            <div class="ms-actions">

              <button type="button" id="ms-select-all-type">Todo</button>

              <button type="button" id="ms-clear-type">Limpiar</button>

            </div>

          </div>

        </div>



        <div class="ms-wrap" id="filter-status">

          <div class="ms-btn" id="ms-toggle-status" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">

            <span class="ms-label">Estado</span>

            <span class="ms-summary" id="ms-summary-status">Todos</span>

          </div>

          <div class="ms-panel" id="ms-panel-status" aria-hidden="true">

            <div id="ms-options-status"></div>

            <div class="ms-actions">

              <button type="button" id="ms-select-all-status">Todo</button>

              <button type="button" id="ms-clear-status">Limpiar</button>

            </div>

          </div>

        </div>

      </div>

      <div class="right" id="dt-search-slot"></div>

    </div>

    <table id="tabla-personajes" class="display" style="width:100%">

        <thead>

            <tr>

                <th>ID</th>

                <th>Nombre</th>

                <th>Grupo</th>

                <th>Organización</th>

                <th>Sistema</th>

                <th>Tipo</th>

                <th>Estado</th>

            </tr>

        </thead>

        <tbody></tbody>

    </table>

  </div>

</div>



<script>

function escapeHtml(text) {

	if (!text) return '';

	return String(text).replace(/[&<>"']/g, function (m) {

		return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];

	});

}



// Escape específico para atributos (incluye backticks)

function escapeAttr(text) {

	if (!text) return '';

	return String(text).replace(/[&<>"'`]/g, function (m) {

		return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '`': '&#96;' })[m];

	});

}



function escapeRegex(text){

	return String(text).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

}



function sortValues(values){

	return values.sort((a,b)=>{

		if (a === '-' && b !== '-') return 1;

		if (b === '-' && a !== '-') return -1;

		return a.localeCompare(b, 'es');

	});

}



// Bloquea cosas raras tipo "javascript:" y similares

function safeUrl(url) {

	if (!url) return '';

	const u = String(url).trim();



	// Bloquea esquemas peligrosos

	const m = u.match(/^([a-z][a-z0-9+.-]*):/i);

	if (m) {

		const scheme = m[1].toLowerCase();

		if (scheme !== 'http' && scheme !== 'https') return '';

		return u; // http(s)://...

	}



	// Permite protocol-relative //cdn...

	if (u.startsWith('//')) return 'https:' + u;



	// Permite rutas relativas normales: img/foo.jpg, uploads/x.png, sep/img/a.webp, ./, ../, /

	return u;

}



$(document).ready(function () {

	const personajes = <?= json_encode(

        $personajes,

        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT

    ) ?>;



	// Construimos el array final para DataTables (más rápido que append por fila)

	const data = personajes.map(p => {

		const imgUrl = safeUrl(p.img);

		const pj_img = imgUrl ? `<img src="${escapeAttr(imgUrl)}" height="12" alt="" loading="lazy" />` : '';



		const charSlug = p.character_pretty_id || Number(p.id);

		const packSlug = p.pack_pretty_id || Number(p.pack_id);

		const clanSlug = p.clan_pretty_id || Number(p.clan_id);

		const typeSlug = p.type_pretty_id || Number(p.type_id);



		const nombre = `<a href="/characters/${escapeAttr(charSlug)}" target="_blank">${pj_img} ${escapeHtml(p.character_name)}</a>`;

		const manada = p.pack_id ? `<a href="/groups/${escapeAttr(packSlug)}" target="_blank">${escapeHtml(p.pack_name)}</a>` : '-';

		const clan   = p.clan_id ? `<a href="/organizations/${escapeAttr(clanSlug)}" target="_blank">${escapeHtml(p.clan_name)}</a>` : '-';

		const tipo   = p.type_id ? `<a href="/characters/type/${escapeAttr(typeSlug)}" target="_blank">${escapeHtml(p.type_name)}</a>` : '-';



		return [

			Number(p.id),

			nombre,

			manada,

			clan,

			escapeHtml(p.system_name || p.system_legacy || ''),

			tipo,

			escapeHtml(p.estado || '')

		];

	});



	const dt = $('#tabla-personajes').DataTable({

		data: data,

		columns: [

			{ title: "ID" },

			{ title: "Nombre" },

			{ title: "Grupo" },

			{ title: "Organización" },

			{ title: "Sistema" },

			{ title: "Tipo" },

			{ title: "Estado" }

		],

		pageLength: 25,

		lengthMenu: [10, 25, 50, 100],

		order: [[1, "asc"]],

		language: {

			search: "🔍 Buscar:&nbsp;",

			lengthMenu: "Mostrar _MENU_ personajes",

			info: "Mostrando _START_ a _END_ de _TOTAL_ personajes",

			infoEmpty: "No hay personajes disponibles",

			emptyTable: "No hay datos en la tabla",

			paginate: {

				first: "Primero",

				last: "Último",

				next: "▶",

				previous: "◀"

			}

		},

		initComplete: function(){

			// Mover buscador a la derecha

			$('#dt-search-slot').append($('#tabla-personajes_filter'));



			// Copiar estilo del input de buscar a los botones de los multiselects

			const $inp = $('#tabla-personajes_filter input');

			if ($inp.length) {

				const cs = window.getComputedStyle($inp[0]);

				['#ms-toggle-pack', '#ms-toggle-clan', '#ms-toggle-system', '#ms-toggle-type', '#ms-toggle-status'].forEach(sel => {

					const $btn = $(sel);

					$btn.css({

						'font-family': cs.fontFamily,

						'font-size': cs.fontSize,

						'font-weight': cs.fontWeight,

						'line-height': cs.lineHeight,

						'padding': cs.padding,

						'border': cs.border,

						'border-radius': cs.borderRadius,

						'background-color': cs.backgroundColor,

						'color': cs.color,

						'box-sizing': cs.boxSizing,

						'height': cs.height,

						'min-height': cs.height

					});



					$btn.on('focus', function(){

						$(this).css({

							'outline':'none',

							'border-color':'#3b82f6',

							'box-shadow':'0 0 0 3px rgba(59,130,246,.18)'

						});

					}).on('blur', function(){

						$(this).css({

							'border': cs.border,

							'box-shadow': 'none'

						});

					});

				});

			}

		}



	});



	// ========= Generar opciones =========

	const packsSet = new Set();

	const clansSet = new Set();

	const systemsSet = new Set();

	const typesSet = new Set();

	const statusSet = new Set();



	personajes.forEach(p => {

		packsSet.add((p.pack_name && String(p.pack_name).trim() !== '') ? String(p.pack_name).trim() : '-');

		clansSet.add((p.clan_name && String(p.clan_name).trim() !== '') ? String(p.clan_name).trim() : '-');

		systemsSet.add((p.system_name && String(p.system_name).trim() !== '') ? String(p.system_name).trim() : ((p.system_legacy && String(p.system_legacy).trim() !== '') ? String(p.system_legacy).trim() : '-'));

		typesSet.add((p.type_name && String(p.type_name).trim() !== '') ? String(p.type_name).trim() : '-');

		statusSet.add((p.estado && String(p.estado).trim() !== '') ? String(p.estado).trim() : '-');

	});



	const filterConfigs = [

		{ key: 'pack', column: 2, allLabel: 'Todos', values: sortValues(Array.from(packsSet)) },

		{ key: 'clan', column: 3, allLabel: 'Todas', values: sortValues(Array.from(clansSet)) },

		{ key: 'system', column: 4, allLabel: 'Todos', values: sortValues(Array.from(systemsSet)) },

		{ key: 'type', column: 5, allLabel: 'Todos', values: sortValues(Array.from(typesSet)) },

		{ key: 'status', column: 6, allLabel: 'Todos', values: sortValues(Array.from(statusSet)) }

	];



	function openPanel(key){ $('#ms-panel-' + key).show().attr('aria-hidden','false'); $('#ms-toggle-' + key).attr('aria-expanded','true'); }

	function closePanel(key){ $('#ms-panel-' + key).hide().attr('aria-hidden','true'); $('#ms-toggle-' + key).attr('aria-expanded','false'); }

	function togglePanel(key){ $('#ms-panel-' + key).is(':visible') ? closePanel(key) : openPanel(key); }



	function getSelected(key){

		const selected = $('#ms-options-' + key + ' input:checked').map(function(){ return $(this).val(); }).get();

		return selected.length ? selected : null;

	}

	function updateSummary(key, selected, allLabel){

		const $summary = $('#ms-summary-' + key);

		if (selected === null) { $summary.text(allLabel); return; }

		if (selected.length === 1) $summary.text(selected[0]);

		else $summary.text(selected.length + ' selecc.');

	}

	function applyFilters(){

		filterConfigs.forEach(cfg => {

			const selected = getSelected(cfg.key);

			updateSummary(cfg.key, selected, cfg.allLabel);

			if (selected === null) {

				dt.column(cfg.column).search('', true, false);

			} else {

				const pat = '^(?:' + selected.map(s => escapeRegex(s)).join('|') + ')$';

				dt.column(cfg.column).search(pat, true, false);

			}

		});

		dt.draw();

	}



	// Pintar opciones y eventos

	filterConfigs.forEach(cfg => {

		const $opts = $('#ms-options-' + cfg.key);

		cfg.values.forEach(v => {

			const safe = escapeHtml(v);

			$opts.append(`

				<label class="ms-row">

					<input type="checkbox" value="${safe}" checked>

					<span>${safe}</span>

				</label>

			`);

		});



		$('#ms-toggle-' + cfg.key).on('click', () => togglePanel(cfg.key));

		$('#ms-toggle-' + cfg.key).on('keydown', (e)=>{ if(e.key==='Enter' || e.key===' ') { e.preventDefault(); togglePanel(cfg.key); } });



		$opts.on('change', 'input', applyFilters);



		$('#ms-select-all-' + cfg.key).on('click', function(){

			$opts.find('input').prop('checked', true);

			applyFilters();

		});

		$('#ms-clear-' + cfg.key).on('click', function(){

			$opts.find('input').prop('checked', false);

			applyFilters();

		});

	});



	// Cierre al click fuera

	$(document).on('click', function(e){

		filterConfigs.forEach(cfg => {

			if (!$(e.target).closest('#filter-' + cfg.key).length) closePanel(cfg.key);

		});

	});



	// Estado inicial

	applyFilters();



});

</script>



