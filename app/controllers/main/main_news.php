<?php setMetaFromPage("Noticias | Heaven's Gate", "Últimas novedades de la campaña Heaven's Gate.", null, 'website'); ?>
<?php require_once(__DIR__ . '/../../domains/news/queries.php'); ?>
<?php include("app/partials/main_nav_bar.php"); // Barra Navegacion ?>
<?php
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-news.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-news.css">';
}
?>
<h2> Noticias </h2>

<table class="hg-news-table">
	<?php
		$tamano_pagina = 5;

		$pagina = filter_var(hg_request_query_param($hgRequest, 'pag'), FILTER_VALIDATE_INT);
		if (!$pagina || $pagina < 1) {
			$pagina = 1;
		}

		$num_total_registros = hg_news_count_posts($link) ?? 0;
		$total_paginas = (int)ceil($num_total_registros / $tamano_pagina);
		if ($total_paginas < 1) {
			$total_paginas = 1;
		}
		if ($pagina > $total_paginas) {
			$pagina = $total_paginas;
		}
		$inicio = ($pagina - 1) * $tamano_pagina;

		$posts = hg_news_fetch_posts($link, $inicio, $tamano_pagina) ?? [];
		foreach ($posts as $post) {
			echo "<tr><td><fieldset class='hg-news-entry'><legend>" . htmlspecialchars($post["title"]) . "</legend><p>" . (($post["message"])) . "</p>\n</fieldset></td></tr>";
			echo "<tr><td align='right'>por <b>" . htmlspecialchars($post["author"]) . "</b> el " . htmlspecialchars($post["posted_at"]) . "</td></tr>";
		}
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