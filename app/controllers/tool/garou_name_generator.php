<?php setMetaFromPage("Generador de nombres Garou | Heaven's Gate", "Generador rapido de nombres Garou.", null, 'website'); ?>
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
			Estilo «deed name» con guiones: <code>Salta-sobre-el-Abismo</code>, <code>Aullido-Funebre</code>, <code>Lanza-de-Sangre</code>
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
            "Salta", "Camina", "Corre", "Acecha", "Aulla", "Caza", "Muerde", "Rasga",
            "Vigila", "Guarda", "Rompe", "Quiebra", "Hierve", "Desafia", "Persigue",
            "Arranca", "Sostiene", "Invoca", "Enciende", "Apaga", "Talla", "Siembra",
            "Despierta", "Cruza", "Resiste", "Senala", "Alza", "Hunde",
        ];

        const roles = [
            "Custodio", "Guardian", "Centinela", "Azote", "Martillo", "Colmillo", "Garra",
            "Lanza", "Filo", "Eco", "Sombra", "Luz", "Ojo", "Paso", "Sendero", "Voz",
            "Aullido", "Canto", "Murmullo", "Limite", "Ultima", "Primera",
        ];

        const things = [
            "Abismo", "Tormenta", "Sangre", "Hueso", "Ceniza", "Luna", "Rayo", "Trueno",
            "Umbral", "Bosque", "Paramo", "Rio", "Lirio", "Acero", "Hierro", "Cristal",
            "Sal", "Humo", "Nieve", "Fuego", "Sombra", "Viento", "Craneo", "Raiz",
            "Relampago", "Silencio", "Eco", "Puerta",
        ];

        const adjectives = [
            "Inquebrantable", "Funebre", "Lunar", "Sombrio", "Sangriento", "Hambriento",
            "Antiguo", "Frio", "Ciego", "Implacable", "Sereno", "Torcido", "Gris",
            "Rojo", "Negro", "Blanco", "Herrumbroso", "Vacio", "Vigilante",
            "Ultimo", "Primordial",
        ];

        const places = [
            "En-la-Tormenta", "En-el-Umbral", "Bajo-la-Luna", "Entre-las-Ruinas",
            "Sobre-el-Barro", "En-el-Silencio", "Tras-la-Ceniza", "Bajo-el-Hielo",
            "En-el-Fondo", "Encima-de-la-Umbra",
        ];

        function randInt(max) {
            if (max <= 0) throw new Error("max must be > 0");

            if (window.crypto && crypto.getRandomValues) {
                const buf = new Uint32Array(1);
                const limit = Math.floor(0x100000000 / max) * max;
                let x;
                do {
                    crypto.getRandomValues(buf);
                    x = buf[0];
                } while (x >= limit);
                return x % max;
            }

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

            if (connectors.includes(raw)) return raw;
            if (keepAsIs.has(raw)) return raw;
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
                case 1:
                    return glueHyphens([pick(verbs), connector, pick(things)]);
                case 2:
                    return glueHyphens([pick(roles), connector, pick(things)]);
                case 3:
                    return glueHyphens([pick(things), pick(adjectives)]);
                case 4:
                    return glueHyphens([pick(roles), pick(adjectives)]);
                case 5:
                    return glueHyphens([pick(roles), connector, pick(things), pick(adjectives)]);
                default: {
                    const place = pick(places);
                    return glueHyphens([pick(verbs), ...place.split("-")]);
                }
            }
        }

        function setUrlCount(n) {
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
                const li = document.createElement("li");
                li.textContent = name;
                $list.appendChild(li);
            }

            if (names.length < n) {
                $note.style.display = "";
                $note.textContent = `Nota: se generaron ${names.length} nombres unicos (se agotaron combinaciones/intentos).`;
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

        $btn.addEventListener("click", () => {
            render(parseInt($count.value, 10));
        });

        $count.addEventListener("change", () => {
            render(parseInt($count.value, 10));
        });

        const initial = currentCountFromUrl();
        $count.value = String(initial);
        render(initial);
    })();
</script>

