function MostrarOcultar(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = (el.style.display === "block") ? "none" : "block";
}

function Permut(flag, img) {
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