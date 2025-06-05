<?php
// ... Tu código PHP previo (igual que lo mandas) ...
$query = "
    SELECT 
        p.id, p.nombre AS character_name, p.alias, p.concepto, p.img,
        nm2.id AS pack_id, nm2.name AS pack_name, 
        nc2.id AS clan_id, nc2.name AS clan_name,
        a.id AS type_id, a.tipo AS type_name
    FROM pjs1 p
        LEFT JOIN nuevo2_manadas nm2 ON p.manada = nm2.id
        LEFT JOIN nuevo2_clanes nc2 ON p.clan = nc2.id
        LEFT JOIN afiliacion a ON a.id = p.tipo
    WHERE p.cronica NOT IN (2,7) 
    ORDER BY p.nombre ASC
";
$result = mysqli_query($link, $query);

$personajes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $personajes[] = $row;
}
mysqli_free_result($result);

$pageSect = "Lista de personajes - Biografías";
include("sep/main/main_nav_bar.php");	// Barra Navegación
?>

<h2 style="text-align:right; margin-top:28px;">Lista de personajes</h2>
<div style="display:flex;justify-content:center;">
  <div style="min-width:900px;max-width:1100px;width:100%;">
    <div id="filtros-personajes" style="text-align:right; margin-bottom:10px;"></div>
    <div id="paginacion-personajes" style="text-align:right; margin-bottom:10px;"></div>
    <table id="tabla-personajes" class="tabla-pj"></table>
  </div>
</div>

<script>
function getQueryParam(name) {
    const url = new URL(window.location.href);
    return url.searchParams.get(name);
}

function setQueryParams(params) {
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([key, value]) => {
        if (value === '' || value === null || value === undefined) {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, value);
        }
    });
    history.replaceState(null, '', url.toString());
}

// Datos cargados desde PHP
var personajesRaw = <?php echo json_encode($personajes, JSON_UNESCAPED_UNICODE); ?>;

// Construir diccionario de manadas por clan
var manadasPorClan = {};
personajesRaw.forEach(function(p) {
    if (p.pack_id && p.clan_id && p.pack_name) {
        if (!manadasPorClan[p.clan_id]) manadasPorClan[p.clan_id] = {};
        manadasPorClan[p.clan_id][p.pack_id] = p.pack_name;
    }
});

var personajes = personajesRaw.slice();
var resultadosPorPagina = 50;
var paginaActual = 1;
var ordenActual = { campo: 'character_name', asc: true };

// Estado de filtros
var filtroClan = '';
var filtroManada = '';

// Leer valores iniciales desde la URL
filtroClan = getQueryParam('clan') || '';
filtroManada = getQueryParam('manada') || '';
const paginaURL = parseInt(getQueryParam('pagina'));
if (!isNaN(paginaURL) && paginaURL > 0) {
    paginaActual = paginaURL;
}

// Calcular listas únicas de clanes y manadas:
function getUniqueOptions(arr, campo, campoId) {
    var options = {};
    arr.forEach(function(p) {
        if (p[campoId] && p[campo]) options[p[campoId]] = p[campo];
    });
    return Object.entries(options)
        .map(function([id, name]) { return {id: id, name: name}; })
        .sort((a, b) => a.name.localeCompare(b.name, 'es', {sensitivity:'base'}));
}

function renderFiltros() {
    var clanes = getUniqueOptions(personajesRaw, 'clan_name', 'clan_id');

    var html = '';

    // Clan
    html += '<label style="margin-right:8px;">Clan: <select id="filtroClan" onchange="cambiarFiltroClan(this.value)" style="font-size:11px;">';
    html += '<option value="">(Todos)</option>';
    clanes.forEach(function(clan) {
        html += '<option value="' + clan.id + '"' + (clan.id == filtroClan ? ' selected' : '') + '>' + clan.name + '</option>';
    });
    html += '</select></label>';

    // Manada (inicialmente vacío, se rellenará por JS)
    html += '<label style="margin-right:8px;">Manada: <select id="filtroManada" onchange="cambiarFiltroManada(this.value)" style="font-size:11px;"></select></label>';

    document.getElementById('filtros-personajes').innerHTML = html;
    actualizarSelectManadas();
}

function actualizarSelectManadas() {
    var selectManada = document.getElementById('filtroManada');
    selectManada.innerHTML = ''; // Limpiar

    var opcionBase = document.createElement('option');
    opcionBase.value = '';
    opcionBase.textContent = '(Todas)';
    selectManada.appendChild(opcionBase);

    var lista;
    if (filtroClan && manadasPorClan[filtroClan]) {
        lista = manadasPorClan[filtroClan];
    } else {
        // Mostrar todas
        lista = {};
        personajesRaw.forEach(function(p) {
            if (p.pack_id && p.pack_name) {
                lista[p.pack_id] = p.pack_name;
            }
        });
    }

    Object.entries(lista).sort((a, b) => a[1].localeCompare(b[1], 'es')).forEach(function([id, name]) {
        var opt = document.createElement('option');
        opt.value = id;
        opt.textContent = name;
        if (filtroManada === id) opt.selected = true;
        selectManada.appendChild(opt);
    });
}

function aplicarFiltros() {
    personajes = personajesRaw.filter(function(p) {
        var matchClan = !filtroClan || (p.clan_id == filtroClan);
        var matchManada = !filtroManada || (p.pack_id == filtroManada);
        return matchClan && matchManada;
    });
    paginaActual = 1;
    renderTablaPersonajes();
    renderPaginacion();
}

function cambiarFiltroClan(clanId) {
    filtroClan = clanId;
    filtroManada = ''; // reset manada
    actualizarSelectManadas();
    aplicarFiltros();
    setQueryParams({
        clan: filtroClan,
        manada: '',
        pagina: 1
    });
}

function cambiarFiltroManada(manadaId) {
    filtroManada = manadaId;
    aplicarFiltros();
    setQueryParams({
        clan: filtroClan,
        manada: filtroManada,
        pagina: 1
    });
}

// Utilidad para ordenar el array
function ordenarPorCampo(arr, campo, asc) {
    return arr.slice().sort(function(a, b) {
        var valA = (a[campo] || '').toString().toLowerCase();
        var valB = (b[campo] || '').toString().toLowerCase();
        if (!isNaN(valA) && !isNaN(valB) && campo === "id") {
            return asc ? (parseInt(valA) - parseInt(valB)) : (parseInt(valB) - parseInt(valA));
        }
        return asc ? (valA > valB ? 1 : valA < valB ? -1 : 0) : (valA < valB ? 1 : valA > valB ? -1 : 0);
    });
}

function renderTablaPersonajes() {
    var inicio = (paginaActual-1) * resultadosPorPagina;
    var fin = Math.min(personajes.length, inicio + resultadosPorPagina);
    var datos = ordenarPorCampo(personajes, ordenActual.campo, ordenActual.asc);

    var tabla = '';
    tabla += '<thead><tr class="pj-row-head">';
    tabla += '<th class="pj-cell-id" onclick="ordenarTabla(\'id\')" title="Ordenar por ID">ID ' + iconoOrden('id') + '</th>';
    tabla += '<th class="pj-cell" onclick="ordenarTabla(\'character_name\')" title="Ordenar por nombre">Nombre ' + iconoOrden('character_name') + '</th>';
    tabla += '<th class="pj-cell" onclick="ordenarTabla(\'concepto\')" title="Ordenar por concepto">Concepto ' + iconoOrden('concepto') + '</th>';
    tabla += '<th class="pj-cell" onclick="ordenarTabla(\'pack_name\')" title="Ordenar por manada">Manada ' + iconoOrden('pack_name') + '</th>';
    tabla += '<th class="pj-cell" onclick="ordenarTabla(\'clan_name\')" title="Ordenar por clan">Clan ' + iconoOrden('clan_name') + '</th>';
    tabla += '<th class="pj-cell" onclick="ordenarTabla(\'type_name\')" title="Ordenar por tipo">Tipo ' + iconoOrden('type_name') + '</th>';
    tabla += '</tr></thead>';
    tabla += '<tbody>';
    for (var i = inicio; i < fin; i++) {
        var pj = datos[i];
        let pj_img = '<img src="' + pj.img + '" />';
        let pack_id_link = "";
        let clan_id_link = "";
        let type_id_link = "";
        let close_a_pack = "";
        let close_a_clan = "";
        let close_a_type = "";
        if (pj.pack_id != null) {
            pack_id_link = '<a href="?p=seegroup&t=1&b=' + pj.pack_id + '" target="_blank">';
            close_a_pack = '</a>';
        }
        if (pj.clan_id != null) {
            clan_id_link = '<a href="?p=seegroup&t=2&b=' + pj.clan_id + '" target="_blank">';
            close_a_clan = '</a>';
        }
        if (pj.type_id != null) {
            type_id_link = '<a href="?p=bios&t=' + pj.type_id + '" target="_blank">';
            close_a_type = '</a>';
        }
        tabla += '<tr class="pj-row">'
            + '<td class="pj-cell-id">' + pj.id + '</td>'
            + '<td class="pj-cell">' + pj_img + ' <a href="?p=muestrabio&b=' + pj.id + '" target="_blank">' + escapeHtml(pj.character_name) + '</a></td>'
            + '<td class="pj-cell">' + escapeHtml(pj.concepto) + '</td>'
            + '<td class="pj-cell">' + pack_id_link + escapeHtml(pj.pack_name) + close_a_pack + '</td>'
            + '<td class="pj-cell">' + clan_id_link + escapeHtml(pj.clan_name) + close_a_clan + '</td>'
            + '<td class="pj-cell">' + type_id_link + escapeHtml(pj.type_name) + close_a_type + '</td>'
            + '</tr>';
    }
    tabla += '</tbody>';
    document.getElementById('tabla-personajes').innerHTML = tabla;
}

function renderPaginacion() {
    var totalPaginas = Math.ceil(personajes.length / resultadosPorPagina);
    var pagHTML = '';
    if (totalPaginas > 1) {
        if (paginaActual > 1) {
            pagHTML += '<button class="pj-btn-pag" onclick="gotoPagina(1)">«</button>';
            pagHTML += '<button class="pj-btn-pag" onclick="gotoPagina('+(paginaActual-1)+')">‹</button>';
        }
        for (var p = 1; p <= totalPaginas; p++) {
            if (p === paginaActual) {
                pagHTML += '<button class="pj-btn-pag active">' + p + '</button>';
            } else if (p === 1 || p === totalPaginas || Math.abs(p-paginaActual)<3) {
                pagHTML += '<button class="pj-btn-pag" onclick="gotoPagina(' + p + ')">' + p + '</button>';
            } else if (p === paginaActual-3 || p === paginaActual+3) {
                pagHTML += '<span style="padding:0 6px;">…</span>';
            }
        }
        if (paginaActual < totalPaginas) {
            pagHTML += '<button class="pj-btn-pag" onclick="gotoPagina('+(paginaActual+1)+')">›</button>';
            pagHTML += '<button class="pj-btn-pag" onclick="gotoPagina('+totalPaginas+')">»</button>';
        }
    }
    document.getElementById('paginacion-personajes').innerHTML = pagHTML;
}

function gotoPagina(p) {
    paginaActual = p;
    renderTablaPersonajes();
    renderPaginacion();
    setQueryParams({
        clan: filtroClan,
        manada: filtroManada,
        pagina: paginaActual
    });
}

function ordenarTabla(campo) {
    if (ordenActual.campo === campo) {
        ordenActual.asc = !ordenActual.asc;
    } else {
        ordenActual.campo = campo;
        ordenActual.asc = true;
    }
    paginaActual = 1;
    renderTablaPersonajes();
    renderPaginacion();
}
function iconoOrden(campo) {
    if (ordenActual.campo !== campo) return '';
    return ordenActual.asc ? '▲' : '▼';
}

// Para evitar XSS en datos
function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>\"']/g, function(m) {
        return ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[m];
    });
}

// Inicializar
renderFiltros();
aplicarFiltros();
</script>

<style>
.tabla-pj {
    width: 100%;
    background: #05014E;
    border: 1px solid #000088;
    border-collapse: collapse;
    margin: 0 auto;
    font-family: Verdana, Arial, sans-serif;
    font-size: 11px;
}
.pj-row-head th {
    background: #050b36;
    color: #33CCCC;
    font-weight: bold;
    border-bottom: 2px solid #000088;
    border-top: 0;
    border-left: 0;
    border-right: 0;
    padding: 6px 10px;
    cursor: pointer;
    text-align: left;
    transition: background 0.18s, color 0.18s;
    white-space: nowrap;
}
.pj-row-head th.pj-cell-id {
    text-align: center;
    color: #33FFFF;
    min-width: 24px;
    background: #05014E;
}
.tabla-pj td, .tabla-pj th {
    border: 1px solid #000088;
    background: #05014E;
    padding: 6px 10px;
    vertical-align: middle;
    white-space: nowrap;
}
.tabla-pj td.pj-cell-id {
    text-align: center;
    color: #33FFFF;
    font-weight: bold;
    background: #05014E;
}
.tabla-pj tr.pj-row:hover td {
    background: #000066;
    color: #33FFFF;
}

.tabla-pj td img {
	vertical-align: bottom;
	height: 12px;
}
.pj-btn-pag {
    font-family: Verdana, Arial, sans-serif;
    font-size: 11px;
    background-color: #000066;
    color: #fff;
    padding: 0.38em 0.9em;
    border: 1px solid #000099;
    border-radius: 0;
    margin: 0 2px;
    transition: background 0.14s, color 0.14s;
    vertical-align: middle;
}
.pj-btn-pag:hover,
.pj-btn-pag.active {
    background-color: #050b36;
    color: #00CCFF;
    border: 1px solid #000088;
    cursor: pointer;
}
.pj-row-head th:hover {
    color: #66CCFF;
    background: #001055;
}

#paginacion-personajes {
	margin-bottom: 2em;
}

</style>
