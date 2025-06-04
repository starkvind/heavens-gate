<?php
	session_start();

	include '../error_reporting.php';
	include 'config.php';

$selectedId = isset($_GET['id']) ? intval($_GET['id']) : null;
$personaje = null;
$relaciones = [];

$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

// Solo personajes con relaciones
$personajes = $pdo->query("SELECT id, nombre FROM pjs1
                           WHERE cronica NOT IN (2, 7)
                             AND (
                               id IN (SELECT source_id FROM character_relations)
                               OR id IN (SELECT target_id FROM character_relations)
                             )
                           ORDER BY nombre")->fetchAll();

if ($selectedId) {
    $stmt = $pdo->prepare("SELECT p.*, nc.name AS clan_name, nm.name AS manada_name,
                                  r.name AS raza_nombre, t.name AS tribu_nombre, a.name AS auspicio_nombre
                           FROM pjs1 p
                           LEFT JOIN nuevo2_clanes nc ON p.clan = nc.id
                           LEFT JOIN nuevo2_manadas nm ON p.manada = nm.id
                           LEFT JOIN nuevo_razas r ON p.raza = r.id
                           LEFT JOIN nuevo_tribus t ON p.tribu = t.id
                           LEFT JOIN nuevo_auspicios a ON p.auspicio = a.id
                           WHERE p.id = ?");
    $stmt->execute([$selectedId]);
    $personaje = $stmt->fetch();

    // Relaciones salientes
    $stmt1 = $pdo->prepare("SELECT cr.*, p2.nombre, p2.alias, 'outgoing' as direction
                            FROM character_relations cr
                            LEFT JOIN pjs1 p2 ON cr.target_id = p2.id
                            WHERE cr.source_id = ?
							ORDER BY cr.relation_type");
    $stmt1->execute([$selectedId]);
    $relaciones = array_merge($relaciones, $stmt1->fetchAll());

    // Relaciones entrantes
    $stmt2 = $pdo->prepare("SELECT cr.*, p2.nombre, p2.alias, 'incoming' as direction
                            FROM character_relations cr
                            LEFT JOIN pjs1 p2 ON cr.source_id = p2.id
                            WHERE cr.target_id = ?
							ORDER BY cr.relation_type");
    $stmt2->execute([$selectedId]);
    $relaciones = array_merge($relaciones, $stmt2->fetchAll());
	// Ordenar alfabéticamente por 'relation_type'
	usort($relaciones, function($a, $b) {
		return strcasecmp($a['relation_type'], $b['relation_type']);
	});
}
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Relaciones de Personaje</title>
		<link rel="stylesheet" href="style.css">
		<style>
			body {
				font-family: 'Segoe UI', sans-serif;
				background: #f9f7f6;
				color: #333;
				padding: 20px;
				max-width: 900px;
				margin: auto;
			}

			h2, h3, h4 {
				color: #444;
			}

			select, input {
				font-size: 16px;
				padding: 8px;
				border-radius: 6px;
				border: 1px solid #ccc;
				width: 100%;
				max-width: 400px;
				background-color: #fff;
			}

			.bio-box {
				display: flex;
				align-items: center; /* Centra verticalmente */
				gap: 20px;
				margin-top: 20px;
				background: #fff;
				padding: 16px;
				border-radius: 10px;
				box-shadow: 0 2px 8px rgba(0,0,0,0.05);
			}

			.bio-box img {
				width: 125px;
				height: 125px;
				object-fit: cover;
				border-radius: 8px;
				flex-shrink: 0; /* Evita que se deforme al encoger */
			}


			.bio-text {
				flex-grow: 1;
			}

			.info-line {
				margin-bottom: 6px;
				font-size: 15px;
				color: #555;
			}

			.relaciones {
				margin-top: 30px;
				background: #fff;
				padding: 16px;
				border-radius: 10px;
				box-shadow: 0 2px 8px rgba(0,0,0,0.05);
			}

			.relaciones li {
				margin-bottom: 8px;
				font-size: 15px;
				list-style: none;
				position: relative;
				padding-left: 18px;
			}

			.relaciones li::before {
				content: "📎";
				position: absolute;
				left: 0;
				top: 0;
			}

			a {
				color: #4d6bb3;
				text-decoration: none;
			}

			a:hover {
				text-decoration: underline;
				color: #2a4c95;
			}

			.backlink {
				margin-top: 30px;
				display: inline-block;
				font-size: 14px;
				color: #888;
			}

			form label {
				font-weight: bold;
				margin-bottom: 5px;
				display: block;
				color: #666;
			}

			.relaciones-grid {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 20px;
				margin-top: 30px;
			}

			.rel-block {
				border-radius: 12px;
				padding: 16px;
				box-shadow: 0 2px 6px rgba(0,0,0,0.05);
				font-size: 15px;
			}

			.rel-block h4 {
				margin-top: 0;
				font-size: 16px;
				color: #333;
			}

			.rel-block ul {
				list-style: none;
				padding-left: 0;
				margin: 0;
			}

			.rel-block li {
				margin-bottom: 6px;
				padding-left: 18px;
				position: relative;
			}

			.rel-block li::before {
				content: "📎";
				position: absolute;
				left: 0;
				top: 0;
			}
			@media (max-width: 768px) {
				body {
					padding: 10px;
				}

				h2 {
					font-size: 20px;
					text-align: center;
				}

				.bio-box {
					flex-direction: column;
					align-items: flex-start;
					text-align: left;
				}

				.bio-box img {
					width: 100%;
					height: auto;
					max-height: 200px;
					object-fit: cover;
				}

				.bio-text h3 {
					font-size: 18px;
					margin-top: 10px;
				}

				.info-line {
					font-size: 14px;
				}

				select {
					font-size: 16px;
					width: 100%;
				}

				.relaciones-grid {
					display: flex;
					flex-direction: column;
				}

				.rel-block {
					font-size: 14px;
					padding: 12px;
				}

				.rel-block h4 {
					font-size: 15px;
					margin-bottom: 8px;
				}

				.rel-block li {
					font-size: 14px;
				}
			}
		</style>
		<!-- Select2 CSS -->
		<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
		<!-- jQuery (requerido por Select2) -->
		<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
		<!-- Select2 JS -->
		<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
	</head>
	<body>

	<h2>🔎 Visualización de Personajes</h2>

	<form method="get">
		<select name="id" id="id" style="width: 100%;" required>
			<option value="">-- Elige uno --</option>
			<?php foreach ($personajes as $p): ?>
				<option value="<?= $p['id'] ?>" <?= $p['id'] === $selectedId ? 'selected' : '' ?>>
					<?= htmlspecialchars($p['nombre']) ?>
				</option>
			<?php endforeach; ?>
		</select>
	</form>

	<?php if ($personaje): ?>
		<div class="bio-box">
			<?php if (!empty($personaje['img'])): ?>
				<img src="../<?= htmlspecialchars($personaje['img']) ?>" alt="Imagen">
			<?php endif; ?>
			<div class="bio-text">
				<h3>
					<?= htmlspecialchars($personaje['nombre']) ?>
					<?php if (!empty($personaje['alias'])): ?>
						(<?= htmlspecialchars($personaje['alias']) ?>)
					<?php endif; ?>
				</h3>

				<?php if (!empty($personaje['nombregarou'])): ?>
					<div class="info-line"><?= htmlspecialchars($personaje['nombregarou']) ?></div>
				<?php endif; ?>

				<?php if (!empty($personaje['raza_nombre']) || !empty($personaje['auspicio_nombre']) || !empty($personaje['tribu_nombre'])): ?>
					<div class="info-line">
						<?= !empty($personaje['raza_nombre']) ? htmlspecialchars($personaje['raza_nombre']) . ' ' : '' ?>
						<?= !empty($personaje['auspicio_nombre']) ? htmlspecialchars($personaje['auspicio_nombre']) : '' ?>
						<?= (!empty($personaje['tribu_nombre'])) ? ' de los ' . htmlspecialchars($personaje['tribu_nombre']) : '' ?>
						<?= !empty($personaje['rango']) ? ' (' . htmlspecialchars($personaje['rango']). ')' : '' ?>
					</div>
				<?php endif; ?>

				<?php if (!empty($personaje['manada_name']) || !empty($personaje['clan_name'])): ?>
					<div class="info-line">
						<?= !empty($personaje['manada_name']) ? htmlspecialchars($personaje['manada_name']) : '' ?>
						<?= (!empty($personaje['manada_name']) && !empty($personaje['clan_name'])) ? ', ' : '' ?>
						<?= !empty($personaje['clan_name']) ? htmlspecialchars($personaje['clan_name']) : '' ?>
					</div>
				<?php endif; ?>

				<?php if (!empty($personaje['estado']) || !empty($personaje['causamuerte'])): ?>
					<div class="info-line">
						<?= htmlspecialchars($personaje['estado']) ?>
						<?= !empty($personaje['causamuerte']) ? ' – ' . htmlspecialchars($personaje['causamuerte']) : '' ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		
		<div style="margin-top: 20px;">
			<p><?= (($personaje['infotext'])) ?></p>
		</div>
			<?php
				$inversionOut = [
					"Asesino" => "Asesinó a",
					"Traidor" => "Traicionó a",
					"Superior" => "Jefe de",
					"Mentor" => "Mentor de",
					"Amo" => "Dueño de",
					"Padre" => "Padre de",
					"Madre" => "Madre de",
					"Hijo" => "Hijo de",
					"Hermano" => "Hermano de",
					"Primo" => "Primo de",
					"Tío" => "Tío de",
					"Protegido" => "Protegido de",
					"Aliado" => "Aliado de",
					"Amigo" => "Amigo de",
					"Amante" => "Amante de",
					"Extorsionador" => "Extorsionó a",
					"Subordinado" => "Subordinado de",
					"Salvador" => "Salvador de",
					"Pareja" => "Pareja de",
					"Enemigo" => "Enemigo de",
					"Rival" => "Rival de",
					"Abuelo" => "Abuelo de",
					"Creación" => "Creación de"
				];

				$inversionIn = [
					"Asesino" => "Asesinado por",
					"Traidor" => "Traicionado por",
					"Superior" => "Subordinado de",
					"Mentor" => "Alumno de",
					"Amo" => "Esclavo de",
					"Padre" => "Hijo de",
					"Madre" => "Hijo de",
					"Hijo" => "Padre de",
					"Hermano" => "Hermano de",
					"Primo" => "Primo de",
					"Tío" => "Sobrino de",
					"Creación" => "Creó a",
					"Protegido" => "Protector de",
					"Aliado" => "Aliado de",
					"Amigo" => "Amigo de",
					"Amante" => "Amante de",
					"Extorsionador" => "Extorsionado por",
					"Subordinado" => "Jefe de",
					"Salvador" => "Salvado por",
					"Pareja" => "Pareja de",
					"Enemigo" => "Enemigo de",
					"Rival" => "Rival de",
					"Abuelo" => "Nieto de"
				];
			?>
		<div class="relaciones-grid">
			<?php
			$categorias = [
				'amistad' => ['Amigo', 'Amante', 'Pareja', 'Protegido', 'Salvador'],
				'alianza' => ['Superior', 'Subordinado', 'Amo', 'Creación', 'Vinculado a', 'Mentor', 'Aliado'],
				'enemistad' => ['Asesino', 'Traidor', 'Extorsionador', 'Enemigo', 'Rival'],
				'familia' => ['Padre', 'Madre', 'Hijo', 'Hermano', 'Primo', 'Tío', 'Abuelo'],
			];

			$clasificadas = ['amistad' => [], 'alianza' => [], 'enemistad' => [], 'familia' => []];

			foreach ($relaciones as $r) {
				$tipo = $r['relation_type'];
				$id = ($r['direction'] === 'incoming') ? $r['source_id'] : $r['target_id'];

				if ($r['direction'] === 'incoming' && isset($inversionIn[$tipo])) {
					$tipo_legible = $inversionIn[$tipo];
				} elseif ($r['direction'] === 'outgoing' && isset($inversionOut[$tipo])) {
					$tipo_legible = $inversionOut[$tipo];
				} else {
					$tipo_legible = $tipo;
				}

				$entrada = "<li><strong>" . htmlspecialchars($tipo_legible) . ":</strong> <a href=\"?id=$id\">" .
						   htmlspecialchars($r['nombre']) . "</a>" .
						   ($r['alias'] ? " (" . htmlspecialchars($r['alias']) . ")" : "") . "</li>";

				foreach ($categorias as $cat => $tipos) {
					if (in_array($tipo, $tipos)) {
						$clasificadas[$cat][] = $entrada;
						continue 2;
					}
				}
			}

			$nombresCategorias = [
				'amistad' => '💚 Amistades',
				'alianza' => '💙 Alianzas',
				'enemistad' => '❤️ Enemistades',
				'familia' => '💛 Familia',
			];
			$fondos = [
				'amistad' => '#e5f9e7',
				'alianza' => '#e6f0fb',
				'enemistad' => '#fbe6e6',
				'familia' => '#fff9db',
			];
			?>

			<?php foreach ($clasificadas as $cat => $lista): ?>
				<div class="rel-block" style="background: <?= $fondos[$cat] ?>;">
					<h4><?= $nombresCategorias[$cat] ?></h4>
					<ul><?= implode("\n", $lista) ?: "<li><em>Sin relaciones registradas</em></li>" ?></ul>
				</div>
			<?php endforeach; ?>
		</div>
		<?php if ($is_admin): ?>
		<div class="relaciones" style="margin-top: 40px;">
			<button onclick="toggleRelationForm()" style="margin-bottom: 10px;">➕ Añadir nueva relación</button>
			<div id="relationFormContainer" style="display: none; margin-top: 10px;">
				<form id="newRelationForm">
					<input type="hidden" name="source_id" value="<?= $selectedId ?>">
					
					<label>Destino:</label>
					<select name="target_id" required>
						<?php foreach ($personajes as $p): ?>
							<?php if ($p['id'] != $selectedId): ?>
								<option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select><br><br>

					<label>Tipo:</label>
					<select name="relation_type" id="relation_type_select_local" required>
						<option value="Amigo">Amigo</option>
						<option value="Aliado">Aliado</option>
						<option value="Mentor">Mentor</option>
						<option value="Protegido">Protegido</option>
						<option value="Salvador">Salvador</option>
						<option value="Amante">Amante</option>
						<option value="Pareja">Pareja</option>
						<option value="Rival">Rival</option>
						<option value="Traidor">Traidor</option>
						<option value="Extorsionador">Extorsionador</option>
						<option value="Enemigo">Enemigo</option>
						<option value="Asesino">Asesino</option>
						<option value="Padre">Padre</option>
						<option value="Madre">Madre</option>
						<option value="Hijo">Hijo</option>
						<option value="Hermano">Hermano</option>
						<option value="Abuelo">Abuelo</option>
						<option value="Tío">Tío</option>
						<option value="Primo">Primo</option>
						<option value="Superior">Superior</option>
						<option value="Subordinado">Subordinado</option>
						<option value="Amo">Amo</option>
						<option value="Creación">Creación</option>
						<option value="Vínculo">Vínculo</option>
					</select><br><br>

					<label>Etiqueta:</label>
					<input type="text" name="tag" id="tag_input_local" value="amistad"><br><br>

					<label>Flechas:</label>
					<select name="arrows">
						<option value="to">➡️ Origen → Destino</option>
						<option value="from">⬅️ Destino → Origen</option>
						<option value="to,from">🔁 Bidireccional</option>
						<option value="">🚫 Ninguna</option>
					</select><br><br>

					<input type="hidden" name="importance" value="1">
					<!--<label>Importancia (0–10):</label>
					<input type="number" name="importance" min="0" max="10" value="1"><br><br>-->

					<!--<label>Descripción:</label><br>
					<textarea name="description" rows="3" cols="30"></textarea><br><br>-->
					
					<input type="hidden" name="description" value="">

					<button type="submit">💾 Guardar relación</button>
				</form>
		</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php include("footer.php"); ?>

		<script>
		$(document).ready(function() {
			$('#id').select2({
				placeholder: "Escribe un nombre...",
				allowClear: true
			}).on('change', function() {
				this.form.submit();
			});
		});
		</script>

		<script>
		document.getElementById("relation_type_select_local").addEventListener("change", function () {
			const value = this.value.toLowerCase();
			const tagField = document.getElementById("tag_input_local");
			const arrowsField = document.querySelector("select[name='arrows']");

			let tag = "";
			let arrow = "to";

			if (["amigo", "aliado", "mentor", "protegido", "salvador", "pareja", "amante"].includes(value)) {
				tag = "amistad";
			} else if (["enemigo", "traidor", "rival", "asesino", "extorsionador"].includes(value)) {
				tag = "conflicto";
			} else if (["superior", "subordinado", "amo", "creado por", "vinculo"].includes(value)) {
				tag = "alianza";
			} else if (["padre", "madre", "hijo", "abuelo", "tío", "primo", "hermano"].includes(value)) {
				tag = "familia";
			}

			switch (value) {
				case "padre": case "madre": case "abuelo": case "tío": case "primo": case "superior": case "amo": case "creacion": case "salvador":
					arrow = "to"; break;
				case "hijo": case "subordinado": case "protegido":
					arrow = "from"; break;
				case "pareja": case "amante": case "hermano": case "aliado":
					arrow = "to,from"; break;
				default:
					arrow = "to";
			}

			tagField.value = tag;
			arrowsField.value = arrow;
		});

		document.getElementById("newRelationForm").addEventListener("submit", function(e) {
			e.preventDefault();
			fetch('add_relation_ajax.php', {
				method: 'POST',
				body: new FormData(this)
			})
			.then(res => res.text())
			.then(result => {
				alert(result);
				location.reload();
			});
		});
		</script>

		<script>
		function toggleRelationForm() {
			const form = document.getElementById('relationFormContainer');
			form.style.display = form.style.display === 'none' ? 'block' : 'none';
		}
		</script>
	</body>
</html>