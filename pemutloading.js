function MostrarOcultar(id) {
	const el = document.getElementById(id);
	let snd = document.getElementById("clickSound");
	if (snd) {
			snd.currentTime = 0; // Reinicia si se pulsa rápido varias veces
			snd.play();
	}
	if (el) el.style.display = (el.style.display === "block") ? "none" : "block";
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
/*
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.renglonMenu').forEach(function(div) {
        div.addEventListener('click', function(event) {
            event.preventDefault(); // Bloquea la navegación instantánea

            // Obtener el href del <a> padre
            var enlace = div.parentNode.getAttribute('href');

            // Reproducir el sonido
            var audio = document.getElementById("confirmSound");
            if (audio) {
                audio.currentTime = 0;
                audio.play();
            }

            // Redirigir después de 1 segundo (1000 ms)
            setTimeout(function() {
                window.location.href = enlace;
            }, 1000);
        });
    });
})
*/