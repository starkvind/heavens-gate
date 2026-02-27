<?php setMetaFromPage("Generador de nombres Garou | Heaven's Gate", "Generador rÃ¡pido de nombres Garou.", null, 'website'); ?>
<link rel="stylesheet" href="/assets/css/hg-tools.css">

<div id="garou-name-gen">
	<div class="hg-garou-card">
		<h2>Generador de Nombres Garou</h2>

		<div class="hg-garou-row">
			<label for="garouCount"><strong>Cantidad:</strong></label>
			<select id="garouCount" data-role="count">
				<option value="10">10</option>
				<option value="20">20</option>
				<option value="50">50</option>
			</select>
			<button type="button" data-role="generate" class="boton2">Generar</button>
		</div>

		<p class="hg-garou-hint">
			Estilo Â«deed nameÂ» con guiones: <code>Salta-sobre-el-Abismo</code>, <code>Aullido-FÃºnebre</code>, <code>Lanza-de-Sangre</code>
		</p>

		<p class="hg-garou-small hg-garou-note-hidden" data-role="note"></p>

		<ul data-role="list" class="hg-garou-list"></ul>
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
            "Salta", "Camina", "Corre", "Acecha", "AÃºlla", "Caza", "Muerde", "Rasga",
            "Vigila", "Guarda", "Rompe", "Quiebra", "Hierve", "DesafÃ­a", "Persigue",
            "Arranca", "Sostiene", "Invoca", "Enciende", "Apaga", "Talla", "Siembra",
            "Despierta", "Cruza", "Resiste", "SeÃ±ala", "Alza", "Hunde",
        ];

        const roles = [
            "Custodio", "GuardiÃ¡n", "Centinela", "Azote", "Martillo", "Colmillo", "Garra",
            "Lanza", "Filo", "Eco", "Sombra", "Luz", "Ojo", "Paso", "Sendero", "Voz",
            "Aullido", "Canto", "Murmullo", "LÃ­mite", "Ãšltima", "Primera",
        ];

        const things = [
            "Abismo", "Tormenta", "Sangre", "Hueso", "Ceniza", "Luna", "Rayo", "Trueno",
            "Umbral", "Bosque", "PÃ¡ramo", "RÃ­o", "Lirio", "Acero", "Hierro", "Cristal",
            "Sal", "Humo", "Nieve", "Fuego", "Sombra", "Viento", "CrÃ¡neo", "RaÃ­z",
            "RelÃ¡mpago", "Silencio", "Eco", "Puerta",
        ];

        const adjectives = [
            "Inquebrantable", "FÃºnebre", "Lunar", "SombrÃ­o", "Sangriento", "Hambriento",
            "Antiguo", "FrÃ­o", "Ciego", "Implacable", "Sereno", "Torcido", "Gris",
            "Rojo", "Negro", "Blanco", "Herrumbroso", "VacÃ­o", "Vigilante",
            "Ãšltimo", "Primordial",
        ];

        const places = [
            "En-la-Tormenta", "En-el-Umbral", "Bajo-la-Luna", "Entre-las-Ruinas",
            "Sobre-el-Barro", "En-el-Silencio", "Tras-la-Ceniza", "Bajo-el-Hielo",
            "En-el-Fondo", "Encima-de-la-Umbra",
        ];

        // ---- Random robusto con Crypto.getRandomValues (rejection sampling) ----
        // (Mejor que Math.random; aquÃ­ el sesgo queda controlado.) :contentReference[oaicite:2]{index=2}
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

            // conectores se quedan en minÃºscula
            if (connectors.includes(raw)) return raw;

            // excepciones se respetan
            if (keepAsIs.has(raw)) return raw;

            // normal: capitalizar, resto en minÃºscula
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
                $note.textContent = `Nota: se generaron ${names.length} nombres Ãºnicos (se agotaron combinaciones/intentos).`;
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
