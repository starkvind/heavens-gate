<?php
/* MODERNO NUEVO */
		include("app/partials/main_nav_bar.php");	// Barra Navegación
		// ================================================================== //
		if (function_exists('hg_page_register_stylesheet')) {
		    hg_page_register_stylesheet('/assets/css/hg-bio.css');
		} else {
		    echo '<link rel=\'stylesheet\' href=\'/assets/css/hg-bio.css\'>';
		}
		if (function_exists('hg_page_register_stylesheet')) {
		    hg_page_register_stylesheet('/assets/css/pages/legacy/controllers-bio-bio_page.css');
		} else {
		    if (function_exists('hg_page_register_stylesheet')) {
		        hg_page_register_stylesheet('/assets/css/pages/legacy/controllers-bio-bio_page.css');
		    } else {
		        echo '<link rel="stylesheet" href="/assets/css/pages/legacy/controllers-bio-bio_page.css">';
		    }
		}

		echo "<div class='bioLayout'>";
		echo "<section class='bioContextHeader'>";
		echo "<div class='power-card power-card--bio'>";
		echo "  <div class='power-card__banner'><span class='power-card__title'>" . h($bioName) . "</span></div>";
		echo "  <div class='power-card__body'>";
		echo "    <div class='power-card__media'>";
		echo "      <div class='power-card__img-wrap'>";
		echo "        <img class='power-card__img' src='" . h($bioPhoto) . "' alt='" . h($bioName) . "'/>";
		echo "      </div>";
		echo "    </div>";
		echo "    <div class='power-card__stats'>";
		include ("app/partials/bio/bio_page_section_03_details.php"); // Detalles básicos (contexto fijo)
		echo "    </div>";
		echo "  </div>";
		echo "</div>";
		echo "</section>";

		// Config de iconos de tabs BIO (16x16).
		// Cambia solo las rutas de este bloque cuando tengas los iconos definitivos.
		$bioTabIconDefault = '/img/ui/icons/icon_character_sheet.webp';
		$bioTabIcons = [
			// Keys validas: info, sheet, rel, part, docs, bso, comments
			'default'  => $bioTabIconDefault, // Fallback global
			'info'     => '/img/ui/icons/icon_character_info.webp',
			'sheet'    => '/img/ui/icons/icon_character_sheet.webp',
			'actions'  => '/img/ui/icons/icon_book.webp',
			'rel'      => '/img/ui/icons/icon_character_relationships.webp',
			'part'     => '/img/ui/icons/icon_character_participation.webp',
			'docs'     => '/img/ui/icons/icon_document.webp',
			'bso'      => '/img/ui/icons/icon_character_music.webp',
			'comments' => '/img/ui/icons/icon_character_comments.webp',
			'export'   => '',
		];

		// Fallback por si alguna ruta llega vacia.
		foreach (['info', 'sheet', 'actions', 'rel', 'part', 'docs', 'bso', 'comments'] as $k) {
			if (!isset($bioTabIcons[$k]) || trim((string)$bioTabIcons[$k]) === '') {
				$bioTabIcons[$k] = $bioTabIconDefault;
			}
		}

		$renderBioTab = function (string $tabKey, string $labelHtml) use ($bioTabIcons) {
			$iconHtml = '';
			if ($tabKey === 'export') {
				$iconHtml = "&#128203;";
			} else {
				$icon = trim((string)($bioTabIcons[$tabKey] ?? $bioTabIcons['default'] ?? ''));
				if ($icon !== '') {
				$iconHtml = "<img class='hgTabIcon' src='" . h($icon) . "' alt='' width='16' height='16' loading='lazy' decoding='async'>";
				}
			}
			echo "<button class='hgTabBtn' data-tab='" . h($tabKey) . "'><span class='hgTabEmoji' aria-hidden='true'>" . $iconHtml . "</span><span class='hgTabLabel'>" . $labelHtml . "</span></button>";
		};

		echo "<div class='hg-tabs'>";
		if ($hasInfo) $renderBioTab('info', 'Informaci&oacute;n');
		if ($hasSheet) $renderBioTab('sheet', 'Hoja de personaje');
		if ($bioHasActions) $renderBioTab('actions', 'Acciones');
		if ($hasRel) $renderBioTab('rel', 'Relaciones');
		if ($hasPart) $renderBioTab('part', 'Participaci&oacute;n');
		if ($hasDocsLinks) $renderBioTab('docs', 'Documentaci&oacute;n');
		if ($hasBso) $renderBioTab('bso', 'Banda sonora');

		if ($hasComments) $renderBioTab('comments', 'Comentarios');
		if ($hasSheet) $renderBioTab('export', 'Exportar');
		echo "</div>";

	echo "<div class='bioBody'>"; // CUERPO PRINCIPAL DE LA FICHA DE INFORMACION
		// ================================================================== //
		echo "<section id='sec-info' class='bio-tab-panel' data-tab='info'>";
		// ================================================================== //
		if ($bioText != "") { // Empezamos colocando la información de Texto
			echo "<div class='bioTextData'>"; 
				echo "<fieldset class='bioSeccion'><legend>$titleInfo</legend>$bioText</fieldset>";
			echo "</div>";
		} // Finalizamos de poner el Texto
		if ($bioIsAdminFlag && trim($bioNotes) !== '') {
			echo "<div class='bioTextData'>";
				echo "<fieldset class='bioSeccion'><legend>&nbsp;Notas internas (admin)&nbsp;</legend><div class='bioAdminNotes'>" . nl2br(h($bioNotes)) . "</div></fieldset>";
			echo "</div>";
		}

		echo "</section>";
		// ================================================================== //
		// BANDA SONORA
		if ($hasBso) {
			echo "<section id='sec-bso' class='bio-tab-panel' data-tab='bso'>";
			include("app/partials/snippet_bso_card.php");
			mostrarTarjetaBSO($link, 'personaje', $characterId);
			echo "</section>";
		}
		// ================================================================== //
		if ($bioHasSheet) { // Comprobamos si el personaje dispone de Hoja
			// ----
			echo "<section id='sec-sheet' class='bio-tab-panel' data-tab='sheet'>";
			echo "<div class='bioSheetData'>"; // Parte Superior de la Hoja ~~ #SEC04
			echo "<fieldset class='bioSeccion'><legend>$titleId</legend>";
				// ----------------------------------------- //
				include ("app/partials/bio/bio_page_section_04_sheetup.php"); // Utilizamos "include" para no sobrecargar la página con código
				// ----------------------------------------- //
			echo "</fieldset>";
			echo "</div>"; // Cerramos Parte Superior ~~
			// ================================================================== //
			echo "<div class='bioSheetData'>"; // Atributos de la Hoja ~~ #SEC05
			echo "<fieldset class='bioSeccion'><legend>$titleAttr</legend>";
				// ----------------------------------------- //
				include ("app/partials/bio/bio_page_section_05_attributes.php"); // Utilizamos "include" para no sobrecargar la página con código
				// ----------------------------------------- //
			echo "</fieldset>";
			echo "</div>"; // Cerramos Atributos ~~
			if (!empty($bioForms)) {
				echo "<div class='bioSheetData bioFormsData'>";
				echo "<fieldset class='bioSeccion'><legend>$titleForms</legend>";
				include ("app/partials/bio/bio_page_section_05_forms.php");
				echo "</fieldset>";
				echo "</div>";
			}
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_06_skills.php"); // Utilizamos "include" para no sobrecargar la página con código
			// ================================================================== //
		if (!$bioIsMonster) {
			echo "<div class='bioSheetBackgrounds'>"; // Trasfondos de la Hoja ~~ #SEC07
				echo "<fieldset class='bioSeccion'><legend>$titleBackg</legend>";
					if (!empty($bioBackgrounds)) {
						foreach ($bioBackgrounds as $idx => $bg) {
							$tid = (int)($bg['id'] ?? 0);
							$nm = (string)($bg['name'] ?? '');
							$val = (int)($bg['value'] ?? 0);
							if ($nm === '' || $val <= 0) continue;
							$nameHtml = h($nm);
							if ($tid > 0 && function_exists('pretty_url')) {
								$hrefT = pretty_url($link, 'dim_traits', '/rules/traits', $tid);
								$nameHtml = "<a href='" . h($hrefT) . "' target='_blank' class='hg-tooltip' data-tip='trait' data-id='" . $tid . "'>" . h($nm) . "</a>";
							}
							echo"<div class='bioSheetBackgroundLeft'>" . $nameHtml . ":</div>";
							$img = $bioBackImgs[$idx] ?? '';
							echo"<div class='bioSheetBackgroundRight'>" . $img . "</div>";
						}
					}
				echo "</fieldset>";
			echo "</div>"; // Cerramos Trasfondos ~~
			// ================================================================== //
			// MÉRITOS Y DEFECTOS
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_08_merits.php"); // Utilizamos "include" para no sobrecargar la página con código
		}
			// ================================================================== //
			// RECURSOS DEL PERSONAJE
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_07_resources.php"); // Utilizamos "include" para no sobrecargar la página con código
			// ================================================================== //
			// CONDICIONES DEL PERSONAJE
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_09_conditions.php"); // Condiciones del personaje, estilo inventario
			// ================================================================== //
			// PODERES, DONES, RITUALES Y DISCIPLINAS
			// ================================================================== //
						
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_11_power.php"); // Utilizamos "include" para no sobrecargar la página con código
			// ================================================================== //
			// INVENTARIO Y OBJETOS
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_13_items.php"); // Utilizamos "include" para no sobrecargar la página con código
			// ================================================================== //
			echo "</section>";
		} // Finalizamos la Hoja de Personaje
		if ($bioHasActions) {
			include ('app/partials/bio/bio_page_section_12_actions.php');
		}
		?>
		
		<?php if ($hasRel): ?>
			<section id="sec-rel" class="bio-tab-panel" data-tab="rel">
			<div class="bioTextData">
				<fieldset class='bioSeccion'>
					<legend><?= ($titleSameBio) ?></legend>
					<button id="toggleRelaciones" class="boton2 bio-rel-toggle" type="button">Cambiar vista</button>
					<div id="seccion2">
						<?php include("app/partials/bio/bio_page_section_17_rel_graph.php"); ?>
					</div>
					<?php include("app/partials/bio/bio_page_section_20_kills.php"); ?>
					<div id="seccion1" class="bio-hidden">
						<?php include("app/partials/bio/bio_page_section_14_family.php"); ?>
					</div>
				</fieldset>
			</div>
			</section>

		<?php endif; ?>
		
		<?php if ($hasPart): ?>
			<section id="sec-part" class="bio-tab-panel" data-tab="part">
			<div class="bioTextData">
				<fieldset class='bioSeccion'>
					<legend>&nbsp;<?= ($titleParticp) ?>&nbsp;</legend>
					<?php include("app/partials/bio/bio_page_section_18_chapters.php"); ?>
					<?php include("app/partials/bio/bio_page_section_19_participation.php"); ?>
				</fieldset>
			</div>
			</section>
		<?php endif; ?>
		<?php if ($hasDocsLinks): ?>
			<section id="sec-docs" class="bio-tab-panel" data-tab="docs">
			<div class="bioTextData">
				<fieldset class='bioSeccion'>
					<legend>&nbsp;Documentaci&oacute;n y enlaces&nbsp;</legend>
					<?php include("app/partials/bio/bio_page_section_21_docs_links.php"); ?>
				</fieldset>
			</div>
			</section>
		<?php endif; ?>
		<?php if ($hasComments): ?>
			<section id="sec-comments" class="bio-tab-panel" data-tab="comments">
				<?php include("app/partials/bio/bio_page_section_15_comments.php"); ?>
			</section>
		<?php endif; ?>
		<?php
	echo "</div>"; // FIN DE CUERPO PRINCIPAL DE LA FICHA DE INFORMACION
		echo "<div class='bio-export-modal' id='bioExportModal' aria-hidden='true'>";
		echo "  <div class='bio-export-modal__panel' role='dialog' aria-modal='true' aria-labelledby='bioExportTitle'>";
		echo "    <div class='bio-export-modal__head'><div class='bio-export-modal__title' id='bioExportTitle'>Exportación en texto plano</div><button type='button' class='bio-export-btn' id='bioExportCloseTop'>Cerrar</button></div>";
		echo "    <div class='bio-export-modal__body'><textarea readonly class='bio-export-modal__ta' id='bioExportTextarea'>" . h($bioPlainExportText) . "</textarea></div>";
		echo "    <div class='bio-export-modal__foot'><div class='bio-export-copy-state' id='bioExportCopyState'></div><div class='bio-export-modal__actions'><button type='button' class='bio-export-btn' id='bioExportCopy'>Copiar todo</button><button type='button' class='bio-export-btn' id='bioExportCloseBottom'>Cerrar</button></div></div>";
		echo "  </div>";
		echo "</div>";
		echo "</div>"; // bioLayout
?>

<script>
	document.addEventListener('DOMContentLoaded', () => {
		const tabs = Array.from(document.querySelectorAll('.hgTabBtn, .bioTabBtn'));
		const panels = Array.from(document.querySelectorAll('.bio-tab-panel'));
		if (typeof HGBindHoverSound === 'function') {
			HGBindHoverSound('.hgTabBtn, .bioTabBtn', '/sounds/ui/hover.ogg');
		}
		function activate(tabKey){
			panels.forEach(p => {
				p.classList.toggle('active', p.dataset.tab === tabKey);
			});
			tabs.forEach(b => {
				b.classList.toggle('active', b.dataset.tab === tabKey);
			});
			if (tabKey === 'rel') {
				setTimeout(() => {
					try {
						if (typeof window.__bioRelNetworkRefresh === 'function') {
							window.__bioRelNetworkRefresh();
						} else {
							if (window.__bioRelNetwork && typeof window.__bioRelNetwork.fit === 'function') {
								window.__bioRelNetwork.fit({ animation: { duration: 200, easingFunction: 'easeInOutQuad' } });
							}
							if (window.__bioRelNetwork && typeof window.__bioRelNetwork.redraw === 'function') {
								window.__bioRelNetwork.redraw();
							}
						}
					} catch (e) {}
				}, 60);
			}
		}
		if (tabs.length) activate(tabs[0].dataset.tab);

		tabs.forEach(b => {
			b.addEventListener('click', () => {
				if (b.dataset.tab === 'export') return;
				activate(b.dataset.tab);
			});
		});

		document.querySelectorAll('.bioSideNav a[data-tab]').forEach(a => {
			a.addEventListener('click', (e) => {
				const tab = a.dataset.tab;
				if (tab) activate(tab);
			});
		});
	});

	document.addEventListener('DOMContentLoaded', () => {
		const btnToggle = document.getElementById('toggleRelaciones');
		const seccion1 = document.getElementById('seccion1');
		const seccion2 = document.getElementById('seccion2');
		const seccion3 = document.getElementById('seccion3');
		const seccion4 = document.getElementById('seccion4');

		// Si no existe el botón (porque no hay relaciones), no hacemos nada
		if (!btnToggle || !seccion1 || !seccion2) return;

		btnToggle.addEventListener('click', () => {
			const hidden = window.getComputedStyle(seccion1).display === 'none';
			if (hidden) {
				seccion1.style.display = 'block';
				seccion2.style.display = 'none';
			} else {
				seccion1.style.display = 'none';
				seccion2.style.display = 'block';
			}
			// Recalcular tamaño/redibujar vis-network si existe
			try {
				if (typeof window.__bioRelNetworkRefresh === 'function') {
					window.__bioRelNetworkRefresh();
				} else if (window.__bioRelNetwork && typeof window.__bioRelNetwork.fit === 'function') {
					window.__bioRelNetwork.fit({ animation: { duration: 300, easingFunction: 'easeInOutQuad' } });
				}
			} catch (e) {}
		});
	});

	document.addEventListener('DOMContentLoaded', () => {
		const modal = document.getElementById('bioExportModal');
		const openBtn = document.querySelector('.hgTabBtn[data-tab="export"], .bioTabBtn[data-tab="export"]');
		const closeTop = document.getElementById('bioExportCloseTop');
		const closeBottom = document.getElementById('bioExportCloseBottom');
		const copyBtn = document.getElementById('bioExportCopy');
		const textarea = document.getElementById('bioExportTextarea');
		const state = document.getElementById('bioExportCopyState');
		if (!modal || !openBtn || !textarea) return;

		const setState = (text) => {
			if (state) state.textContent = text || '';
		};
		const openModal = () => {
			modal.classList.add('is-open');
			modal.setAttribute('aria-hidden', 'false');
			setTimeout(() => {
				textarea.focus();
				textarea.select();
			}, 30);
		};
		const closeModal = () => {
			modal.classList.remove('is-open');
			modal.setAttribute('aria-hidden', 'true');
			setState('');
		};

		openBtn.addEventListener('click', openModal);
		if (closeTop) closeTop.addEventListener('click', closeModal);
		if (closeBottom) closeBottom.addEventListener('click', closeModal);
		modal.addEventListener('click', (e) => {
			if (e.target === modal) closeModal();
		});
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
		});
		if (copyBtn) {
			copyBtn.addEventListener('click', async () => {
				const text = String(textarea.value || '');
				if (!text) return;
				try {
					if (navigator.clipboard && navigator.clipboard.writeText) {
						await navigator.clipboard.writeText(text);
					} else {
						textarea.focus();
						textarea.select();
						document.execCommand('copy');
					}
					setState('Texto copiado.');
				} catch (err) {
					textarea.focus();
					textarea.select();
					setState('No se pudo copiar. Usa Ctrl+C.');
				}
			});
		}
	});
</script>
	<script>
		document.addEventListener('DOMContentLoaded', () => {
			if (window.__hgTooltipBound) return;
			const tooltip = document.createElement('div');
			tooltip.id = 'hg-tooltip';
			document.body.appendChild(tooltip);

			const cache = new Map();
			let timer = null;
			let currentKey = '';
			let lastX = 0, lastY = 0;

			function moveTip(x, y){
				const pad = 14;
				const tw = tooltip.offsetWidth || 320;
				const th = tooltip.offsetHeight || 120;
				let left = x + pad;
				let top = y + pad;
				if (left + tw > window.innerWidth) left = x - tw - pad;
				if (top + th > window.innerHeight) top = y - th - pad;
				tooltip.style.left = left + 'px';
				tooltip.style.top = top + 'px';
			}

			function hideTip(){
				tooltip.style.display = 'none';
				tooltip.innerHTML = '';
				currentKey = '';
			}

			document.querySelectorAll('.hg-tooltip').forEach(el => {
				el.addEventListener('mousemove', (e) => {
					lastX = e.clientX;
					lastY = e.clientY;
					if (tooltip.style.display === 'block') moveTip(lastX, lastY);
				});

				el.addEventListener('mouseenter', (e) => {
					lastX = e.clientX;
					lastY = e.clientY;
					const type = el.getAttribute('data-tip') || '';
					const id = el.getAttribute('data-id') || '';
					if (!type || !id) return;
					const key = type + ':' + id;
					currentKey = key;
					if (cache.has(key)) {
						tooltip.innerHTML = cache.get(key);
						tooltip.style.display = 'block';
						moveTip(lastX, lastY);
						return;
					}
					timer = setTimeout(async () => {
						if (currentKey !== key) return;
						try {
							const res = await fetch(`/ajax/tooltip?type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`);
							const html = await res.text();
							if (currentKey !== key) return;
							cache.set(key, html);
							tooltip.innerHTML = html;
							tooltip.style.display = 'block';
							moveTip(lastX, lastY);
						} catch (err) {
							// silencioso
						}
					}, 900);
				});

				el.addEventListener('mouseleave', () => {
					if (timer) clearTimeout(timer);
					timer = null;
					hideTip();
				});
			});
		});
	</script>
	<script>
		document.addEventListener('click', async (event) => {
			const btn = event.target.closest('.js-copy-roll');
			if (!btn) return;
			const text = String(btn.getAttribute('data-copy') || '');
			if (!text) return;
			const old = btn.innerHTML;
			try {
				if (navigator.clipboard && navigator.clipboard.writeText) {
					await navigator.clipboard.writeText(text);
				} else {
					const ta = document.createElement('textarea');
					ta.value = text;
					document.body.appendChild(ta);
					ta.select();
					document.execCommand('copy');
					ta.remove();
				}
				btn.innerHTML = '&#9989;';
			} catch (e) {
				btn.innerHTML = '&#10060;';
			}
			setTimeout(() => { btn.innerHTML = old; }, 1400);
		});
	</script>
