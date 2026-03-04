function MostrarOcultar(id) {
	const all = document.querySelectorAll('.ocultable');
	const target = document.getElementById(id);
	const snd = document.getElementById("clickSound");
	const clo = document.getElementById("closeSound");

	// Si ya está abierto, lo cerramos
	if (target.classList.contains('open')) {
		target.classList.remove('open');
		if (clo) {
			clo.currentTime = 0;
			clo.play();
		}
		return;
	} else {
		if (snd) {
			snd.currentTime = 0;
			snd.play();
		}
	}

	// Cierra todos
	all.forEach(div => div.classList.remove('open'));

	// Abre el deseado
	requestAnimationFrame(() => {
		target.classList.add('open');
	});
}

function AbrirMenu(id) {
	const all = document.querySelectorAll('.ocultable');
	const target = document.getElementById(id);
	if (!target) return;

	// Cierra todos y abre el deseado sin toggle ni sonido
	all.forEach(div => div.classList.remove('open'));
	requestAnimationFrame(() => {
		target.classList.add('open');
	});
}

document.addEventListener('DOMContentLoaded', () => {
	const el = document.querySelector('[data-menu-open]');
	if (!el) return;
	const id = el.getAttribute('data-menu-open');
	if (id) AbrirMenu(id);
});


function recargar(tiempo) {
	if (typeof tiempo === 'undefined') {
		location.reload();
	} else {
		setTimeout(() => location.reload(true), tiempo);
	}
}

function Permut(flag, img) {
	if (flag === 1) {
		// Toca sonido
		let snd = document.getElementById("selectSound");
		if (snd) {
			snd.currentTime = 0; // Reinicia si se pulsa rápido varias veces
			snd.play();
		}
	}
	// Código original de imagen
	if (document.images) {
		const image = document.images[img];
		if (image && image.permloaded) {
			image.src = (flag === 1) ? image.perm.src : image.perm.oldsrc;
		}
	}
}

function preloadPermut(img, src) {
	if (document.images) {
		img.onload = null;
		img.perm = new Image();
		img.perm.oldsrc = img.src;
		img.perm.src = src;
		img.permloaded = true;
	}
}

// Reproductor simple para sonidos de hover reutilizable en cualquier vista.
window.__hgHoverAudioCache = window.__hgHoverAudioCache || {};
function HGPlayHoverSound(src) {
	if (!src) return;
	try {
		if (!window.__hgHoverAudioCache[src]) {
			const a = new Audio(src);
			a.preload = 'auto';
			window.__hgHoverAudioCache[src] = a;
		}
		const snd = window.__hgHoverAudioCache[src];
		snd.currentTime = 0;
		snd.play();
	} catch (e) {}
}

function HGBindHoverSound(selector, src) {
	if (!selector || !src) return;
	document.querySelectorAll(selector).forEach(el => {
		if (!el || el.dataset.hgHoverSoundBound === '1') return;
		el.dataset.hgHoverSoundBound = '1';
		el.addEventListener('mouseenter', () => HGPlayHoverSound(src));
	});
}
