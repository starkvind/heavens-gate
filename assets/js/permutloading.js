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