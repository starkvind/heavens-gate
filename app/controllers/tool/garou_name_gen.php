<?php setMetaFromPage("Generador de nombres Garou | Heaven's Gate", "Generador rapido de nombres Garou.", null, 'website'); ?>
<style>
	.wrap{max-width:900px;margin:0 auto}
	.card{border-radius:12px;padding:16px 18px}
	.row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
	select,button{border-radius:10px;padding:1em;}
	button{cursor:pointer;padding:1em!important;}
	ul{margin:14px 0 0 0;padding-left:18px}
	li{padding:4px 0}
	.hint{font-size:14px;margin-top:10px;margin-bottom:10px;padding:1em;background-color:#000066;border:1px solid #000088;border-radius:1em;display:none;}
	.small{color:#666;font-size:13px}
	code{background:#f6f6f6;color:#333;padding:2px 6px;border-radius:6px}
    .list{
        -moz-column-count: 2;
        -moz-column-gap: 20px;
        -webkit-column-count: 2;
        -webkit-column-gap: 20px;
        column-count: 2;
        column-gap: 20px;
        list-style: none;
        padding: 1em;
        margin-left: 0;

        background-color:#000066;border:1px solid #000088;border-radius:1em;
    }

    /* Evita que un <li> se divida entre columnas */
    .list li{
        break-inside: avoid-column;
        -webkit-column-break-inside: avoid;
        page-break-inside: avoid;
    }
</style>

<div id="garou-name-gen" class="wrap">
	<div class="card">
		<h2>Generador de Nombres Garou</h2>

		<div class="row">
			<label for="garouCount"><strong>Cantidad:</strong></label>
			<select id="garouCount" data-role="count">
				<option value="10">10</option>
				<option value="20">20</option>
				<option value="50">50</option>
			</select>
			<button type="button" data-role="generate" class="boton2">Generar</button>
		</div>

		<p class="hint">
			Estilo «deed name» con guiones: <code>Salta-sobre-el-Abismo</code>, <code>Aullido-Fúnebre</code>, <code>Lanza-de-Sangre</code>
		</p>

		<p class="small" data-role="note" style="display:none;"></p>

		<ul data-role="list" class="list"></ul>
	</div>
</div>

<script>
    (() => {
        "use strict";

        const root = document.getElementById("garou-name-gen");
        if (!root) return;

        const allowedCounts = [10, 20, 50];

        const $count = root.querySelector('[data-role="count"]');
        const $btn   = root.querySelector('[data-role="generate"]');
        const $list  = root.querySelector('[data-role="list"]');
        const $note  = root.querySelector('[data-role="note"]');

        // ---- Pools (mismo contenido que en PHP) ----
        const connectors = [
            "de", "del", "de-la", "de-los", "de-las",
            "en", "en-el", "en-la",
            "sobre-el", "sobre-la",
            "bajo-el", "bajo-la",
            "entre", "entre-el", "entre-la", "entre-las",
            "tras", "tras-el", "tras-la",
            "contra-el", "contra-la",
            "ante-el", "ante-la",
            "sin",
        ];

        const keepAsIs = new Set(["Gaia", "Wyrm", "Kaos", "Umbra", "Helios", "Selene"]);

        const verbs = [
            "Salta", "Camina", "Corre", "Acecha", "Aúlla", "Caza", "Muerde", "Rasga",
            "Vigila", "Guarda", "Rompe", "Quiebra", "Hierve", "Desafía", "Persigue",
            "Arranca", "Sostiene", "Invoca", "Enciende", "Apaga", "Talla", "Siembra",
            "Despierta", "Cruza", "Resiste", "Señala", "Alza", "Hunde",
        ];

        const roles = [
            "Custodio", "Guardián", "Centinela", "Azote", "Martillo", "Colmillo", "Garra",
            "Lanza", "Filo", "Eco", "Sombra", "Luz", "Ojo", "Paso", "Sendero", "Voz",
            "Aullido", "Canto", "Murmullo", "Límite", "Última", "Primera",
        ];

        const things = [
            "Abismo", "Tormenta", "Sangre", "Hueso", "Ceniza", "Luna", "Rayo", "Trueno",
            "Umbral", "Bosque", "Páramo", "Río", "Lirio", "Acero", "Hierro", "Cristal",
            "Sal", "Humo", "Nieve", "Fuego", "Sombra", "Viento", "Cráneo", "Raíz",
            "Relámpago", "Silencio", "Eco", "Puerta",
        ];

        const adjectives = [
            "Inquebrantable", "Fúnebre", "Lunar", "Sombrío", "Sangriento", "Hambriento",
            "Antiguo", "Frío", "Ciego", "Implacable", "Sereno", "Torcido", "Gris",
            "Rojo", "Negro", "Blanco", "Herrumbroso", "Vacío", "Vigilante",
            "Último", "Primordial",
        ];

        const places = [
            "En-la-Tormenta", "En-el-Umbral", "Bajo-la-Luna", "Entre-las-Ruinas",
            "Sobre-el-Barro", "En-el-Silencio", "Tras-la-Ceniza", "Bajo-el-Hielo",
            "En-el-Fondo", "Encima-de-la-Umbra",
        ];

        // ---- Random robusto con Crypto.getRandomValues (rejection sampling) ----
        // (Mejor que Math.random; aquí el sesgo queda controlado.) :contentReference[oaicite:2]{index=2}
        function randInt(max) {
            if (max <= 0) throw new Error("max must be > 0");

            if (window.crypto && crypto.getRandomValues) {
                const buf = new Uint32Array(1);
                const limit = Math.floor(0x100000000 / max) * max; // 2^32
                let x;
                do {
                    crypto.getRandomValues(buf);
                    x = buf[0];
                } while (x >= limit);
                return x % max;
            }

            // Fallback (por si acaso)
            return Math.floor(Math.random() * max);
        }

        function pick(arr) {
            return arr[randInt(arr.length)];
        }

        function ucFirst(str) {
            if (!str) return str;
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        function titleSegment(seg) {
            const raw = String(seg).trim();
            if (!raw) return "";

            // conectores se quedan en minúscula
            if (connectors.includes(raw)) return raw;

            // excepciones se respetan
            if (keepAsIs.has(raw)) return raw;

            // normal: capitalizar, resto en minúscula
            return ucFirst(raw.toLowerCase());
        }

        function glueHyphens(segments) {
            const clean = segments
                .map(s => String(s).trim())
                .filter(Boolean)
                .map(s => titleSegment(s));
            return clean.join("-");
        }

        function generateName() {
            const which = randInt(6) + 1;
            const connector = pick(connectors);

            switch (which) {
                case 1: { // Verbo + conector + cosa
                    return glueHyphens([pick(verbs), connector, pick(things)]);
                }
                case 2: { // Rol + conector + cosa
                    return glueHyphens([pick(roles), connector, pick(things)]);
                }
                case 3: { // Cosa + Adjetivo
                    return glueHyphens([pick(things), pick(adjectives)]);
                }
                case 4: { // Rol + Adjetivo
                    return glueHyphens([pick(roles), pick(adjectives)]);
                }
                case 5: { // Rol + conector + cosa + adjetivo
                    return glueHyphens([pick(roles), connector, pick(things), pick(adjectives)]);
                }
                default: { // Verbo + lugar prefijado
                    const place = pick(places); // ya viene con guiones
                    return glueHyphens([pick(verbs), ...place.split("-")]);
                }
            }
        }

        function setUrlCount(n) {
            // Mantiene actualiza n sin recargar. :contentReference[oaicite:3]{index=3}
            const url = new URL(window.location.href);
            url.searchParams.set("n", String(n));
            history.replaceState(null, "", url.toString());
        }

        function render(n) {
            if (!allowedCounts.includes(n)) n = 10;

            const names = [];
            const seen = new Set();
            const maxAttempts = n * 30;
            let attempts = 0;

            while (names.length < n && attempts < maxAttempts) {
                attempts++;
                const name = generateName();
                const key = name.toLowerCase();
                if (seen.has(key)) continue;
                seen.add(key);
                names.push(name);
            }

            $list.innerHTML = "";
            for (const name of names) {
                const li = document.createElement("li"); // :contentReference[oaicite:4]{index=4}
                li.textContent = name;
                $list.appendChild(li);
            }

            if (names.length < n) {
                $note.style.display = "";
                $note.textContent = `Nota: se generaron ${names.length} nombres únicos (se agotaron combinaciones/intentos).`;
            } else {
                $note.style.display = "none";
                $note.textContent = "";
            }

            setUrlCount(n);
        }

        function currentCountFromUrl() {
            const params = new URLSearchParams(window.location.search);
            const n = parseInt(params.get("n") || "10", 10);
            return allowedCounts.includes(n) ? n : 10;
        }

        // Eventos :contentReference[oaicite:5]{index=5}
        $btn.addEventListener("click", () => {
            render(parseInt($count.value, 10));
        });

        $count.addEventListener("change", () => {
            render(parseInt($count.value, 10));
        });

        // Init: respeta ?n=...
        const initial = currentCountFromUrl();
        $count.value = String(initial);
        render(initial);
    })();
</script>