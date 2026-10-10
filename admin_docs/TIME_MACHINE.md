# Máquina del Tiempo — las dos webs de enero de 2007

## Principios de conservación

La ruta moderna \`/time-machine\` da acceso a dos copias de lectura, no al canon vigente:

- \`2007\`: primera web (fuente original: \`heavens.zip\`, enero de 2007).
- \`v1.2\`: web del menú lateral y cursor tipo Final Fantasy (fuente: \`heavens v1.2.zip\`, actualización de portada de 30/01/2007).

**El canon reconstruido prevalece siempre** sobre cualquier fecha, nombre o acontecimiento de estos archivos. No trasladar automáticamente su cronología a la base de datos actual.

Los ZIP originales no se alteran. La presentación pública normaliza textos UTF-8/Windows-1252 a UTF-8, excluye cachés Windows y ejecutables de servidor, convierte el único \`clasificados.php\` (que solo contenía HTML) a \`.html\`, neutraliza un bloque PHP antiguo incrustado en un HTML y sustituye JavaScript \`eval()\` por \`window.open()\` sin evaluación dinámica. El CSS desplegable y los GIF de cursor se conservan.

La política en \`public/time-machine/.htaccess\` impide ejecución de PHP/CGI, bloquea acceso a ficheros peligrosos, añade cabeceras \`noindex\` y limita los recursos mediante CSP. La música MIDI incrustada ya no funcionará en navegadores modernos; los DOC se descargan como adjuntos. Los enlaces externos de 2007 se conservan, aunque algunos pueden estar caídos.

## Instalación de copias desde los ZIP

Los ZIP **no están versionados en producción**. Para la primera instalación debes disponer de ambos originales en la Raspberry, fuera del directorio público.

Desde la raíz del repo, tras \`git pull --ff-only\`:

\`\`\`bash
python3 tools/install_time_machine_archives.py \
  --original '/ruta/al/heavens.zip' \
  --v12 '/ruta/al/heavens v1.2.zip'
\`\`\`

Una alternativa es extraer el paquete \`heavens-gate-time-machine-static.zip\` (preparado externamente) en la raíz del repositorio. Contiene únicamente \`public/time-machine/{2007,v1.2,...}\`, las copias estáticas ya revisadas; no incluye los dos ZIP originales.

Los directorios generados se ignoran en Git para que el pull de producción no los borre ni genere cambios pendientes. Para regenerarlos, hay que retirarlos expresamente y ejecutar el instalador.

## Comprobaciones posteriores

\`\`\`bash
php -l app/controllers/main/main_time_machine.php
python3 -m py_compile tools/install_time_machine_archives.py
bash tools/production_smoke.sh
\`\`\`

Verificar por HTTP que \`/time-machine\` enlaza a ambas webs, que se cargan \`/public/time-machine/2007/index.html\` y \`/public/time-machine/v1.2/index.html\`, que el menú lateral y el cursor funcionan, que \`clasificados.html\` sustituye al PHP, que el PHP antiguo devuelve denegación/404 y que las respuestas antiguas llevan \`X-Robots-Tag: noindex\`.

**Limitación del despliegue:** un \`git pull\` despliega el portal y la seguridad, pero no copia los archivos históricos; se necesitan los ZIP de origen o el paquete estático en el servidor. No anunciar las dos versiones como disponibles hasta efectuar esa instalación.
