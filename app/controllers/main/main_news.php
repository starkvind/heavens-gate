<?php setMetaFromPage("Noticias | Heaven's Gate", "Últimas novedades de la campaña Heaven's Gate.", null, 'website'); ?>
<?php include("app/partials/main_nav_bar.php"); // Barra Navegacion ?>
<link rel="stylesheet" href="/assets/css/hg-main.css">
<h2> Noticias </h2>


<table class="notix">
	<?php
		global $link;

		$tamano_pagina = 5;

		$pagina = filter_input(INPUT_GET, 'pag', FILTER_VALIDATE_INT);
		if (!$pagina || $pagina < 1) {
			$pagina = 1;
		}

		$consulta = "SELECT COUNT(*) as total FROM fact_admin_posts";
		$result = mysqli_query($link, $consulta);
		$row = mysqli_fetch_assoc($result);
		$num_total_registros = (int)($row['total'] ?? 0);
		$total_paginas = (int)ceil($num_total_registros / $tamano_pagina);
		if ($total_paginas < 1) {
			$total_paginas = 1;
		}
		if ($pagina > $total_paginas) {
			$pagina = $total_paginas;
		}
		$inicio = ($pagina - 1) * $tamano_pagina;

		$consulta = "SELECT author, title, message, posted_at FROM fact_admin_posts ORDER BY id DESC LIMIT ?, ?";
		$stmt = mysqli_prepare($link, $consulta);
		mysqli_stmt_bind_param($stmt, "ii", $inicio, $tamano_pagina);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);

		while ($ResultQuery = mysqli_fetch_assoc($result)) {
			echo "<tr><td><fieldset class='notf'><legend class='notf'>" . htmlspecialchars($ResultQuery["title"]) . "</legend><p>" . (($ResultQuery["message"])) . "</p>\n</fieldset></td></tr>";
			echo "<tr><td align='right'>por <b>" . htmlspecialchars($ResultQuery["author"]) . "</b> el " . htmlspecialchars($ResultQuery["posted_at"]) . "</td></tr>";
		}

		mysqli_stmt_close($stmt);
	?>
</table>

<?php if ($total_paginas > 1): ?>
	<div class="news-pagination-wrap">
		<nav class="news-pagination" aria-label="Paginacion de noticias">
			<?php if ($pagina > 1): ?>
				<a class="paginate_button previous" href="/news?pag=<?= ($pagina - 1) ?>">&#9664;</a>
			<?php endif; ?>

			<?php
				$ini = max(1, $pagina - 2);
				$fin = min($total_paginas, $pagina + 2);
				for ($ix = $ini; $ix <= $fin; $ix++):
			?>
				<?php if ($pagina === $ix): ?>
					<span class="paginate_button current"><?= $ix ?></span>
				<?php else: ?>
					<a class="paginate_button" href="/news?pag=<?= $ix ?>"><?= $ix ?></a>
				<?php endif; ?>
			<?php endfor; ?>

			<?php if ($pagina < $total_paginas): ?>
				<a class="paginate_button next" href="/news?pag=<?= ($pagina + 1) ?>">&#9654;</a>
			<?php endif; ?>
		</nav>
	</div>
	<p class="news-pagination-info">P&aacute;gina <?= $pagina ?> de <?= $total_paginas ?></p>
<?php endif; ?>
