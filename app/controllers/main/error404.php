<?php
http_response_code(404);

$headline = "Has entrado demasiado profundo en el espacio liminal.";
$body = "Si cre&iacute;as que hab&iacute;a algo aqu&iacute;, ahora no hay nada.";

$links = [
    ['/news', 'Noticias'],
    ['/search', 'Buscar'],
    ['/maps', 'Mapas'],
];
?>

<div class="bioBody">
	<h2>404</h2>
	<fieldset id="renglonArchivosTop">
		<legend id="archivosLegend"><?= $headline ?></legend>
		<p><?= $body ?></p>
		<p>Quiz&aacute; quieras volver por aqu&iacute;:</p>
		<div style="text-align:center; margin-top:10px;">
			<?php foreach ($links as $l): ?>
				<a class="boton2" style="margin:0 6px; display:inline-block;" href="<?= htmlspecialchars($l[0], ENT_QUOTES, 'UTF-8') ?>">
					<?= htmlspecialchars($l[1], ENT_QUOTES, 'UTF-8') ?>
				</a>
			<?php endforeach; ?>
		</div>
	</fieldset>
</div>
