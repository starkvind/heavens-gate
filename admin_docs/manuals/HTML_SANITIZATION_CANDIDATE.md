# Política candidata de sanitización HTML

Piloto en documentos: guardado administrativo, apertura individual del editor y renderizado público escritorio/móvil. No hay migración de datos ni despliegue.

- Dependencia PHP: ezyang/htmlpurifier 4.19.1, fijada en app/security/composer.json, dentro del árbol app protegido por Apache.
- composer.lock generado por Composer en GitHub Actions y versionado; la CI instala exactamente el lock y ejecuta composer audit. Para instalar en el checkout de preproducción: `composer --working-dir=app/security install --no-dev --no-interaction --no-plugins --no-scripts --prefer-dist`. No ejecutar composer update en producción.
- El helper no debe activarse sin una copia de los contenidos reales en un entorno aislado, comparación estructural/visual y validación de latencia/memoria PHP 8.2 ARMv7.
- Política inicial: HTML de texto, estilos restringidos, tablas, imágenes y enlaces; clases Quill permitidas y atributos de menciones tipados. La resolución y validación semántica de menciones queda pendiente.
- Límite candidato: 2 MiB de HTML por campo; revisar con el corpus. Ante error de instalación, tamaño o configuración, rechazar la escritura, nunca guardar HTML original como fallback.
- No autorizar atributos de eventos, contenido activo ni clases adicionales sin revisión. Revisar recursos externos, formatos históricos, enlaces y tablas.
- Integración posterior: sanitizar entradas y resultado de la conversión de menciones; tratar contenido histórico antes de mostrarlo o abrirlo en editores; validar todas las rutas de escritura.
- Caché opcional: directorio privado y escribible por PHP-FPM, nunca bajo una ruta pública. Medir uso en Raspberry antes de activarla.
- No cambia Quill, base de datos ni producción. No fusionar hasta validar CI, corpus y despliegue de la dependencia. El listado ya no transfiere cuerpos completos; el editor solicita un documento saneado. La conversión regex de menciones sigue pendiente de refactor; también la comprobación de ida y vuelta de tablas en Quill. El límite y la política de formatos no están aprobados para producción.

- Piloto ampliado a Noticias: escritura saneada, lectura individual autenticada y salida pública. Los listados no transportan cuerpos. En Documentos y Noticias las nuevas menciones se convierten únicamente en nodos de texto mediante DOM (requiere php-xml); no en enlaces existentes, atributos ni bloques de código. La integración se ha ampliado a los demás módulos identificados.

## Inventario histórico sin escritura

Ejecutar únicamente sobre una copia aislada de MariaDB, con cuenta SELECT y fichero de credenciales privado independiente. Instalar primero la dependencia fijada. El script no reutiliza config.env automáticamente, abre una transacción READ ONLY y termina con rollback. No realiza cambios ni genera copias de texto en el informe.

    umask 077
    php tools/audit_rich_html.php --config=/ruta/privada/copia-readonly.env --only=fact_docs.content > /ruta/privada/html-audit.jsonl

Omitir --only para recorrer el inventario provisional de campos; --batch admite 1–25 (por defecto 10). El informe registra IDs, diferencias de bytes/texto/estructura, errores, campos ausentes, tiempos y memoria pico; no contiene el HTML ni credenciales. Las métricas son señales de revisión, no prueba de equivalencia visual ni autorización de migración. Salidas: 0 recorrido sin errores ni columnas ausentes, 1 error global, 2 opciones/config inválidas, 3 filas no evaluadas, 4 columnas ausentes. Revisar columnas opcionales o versiones de esquema antes de considerar completa la auditoría. No publicar resultados ni datos históricos en el repositorio de aplicación.

- Capítulos: dos rutas de escritura saneadas; el editor pide el contenido de un único capítulo por AJAX autenticado y con CSRF, en lugar de transportar todas las sinopsis. Salidas públicas de escritorio y móvil saneadas y JSON inicial protegido con flags HEX. Las relaciones e imágenes mantienen sus rutas existentes.

- Inventario, Sistemas y Formas: entradas enriquecidas, carga del editor bajo demanda y salidas públicas saneadas. Los listados ya no incluyen los cuerpos HTML. En Inventario, si falla la sanitización no se inicia la subida ni el borrado de imágenes. Las descripciones de recursos se filtran al mostrarse y al guardar.

- Temporadas, Poderes y Detalles de sistemas: guardado y carga individual de campos enriquecidos, y protección de salidas públicas identificadas. Temporadas conserva una descripción para el filtro del listado en servidor, pero no envía cuerpos a JavaScript. La rama incorpora el commit del PR #91 (CSRF de temporadas) y su regresión, sin fusionar en master.

- Biografías, rasgos, méritos/defectos y recursos: saneamiento de escrituras y de salidas públicas identificadas. No se modifica el tipo de editor textarea; el HTML no se inserta como DOM al cargar dichos formularios. Los niveles de rasgos se sanean antes de su transformación visual en gemas. Pendiente revisar otros consumidores, el corpus real y la preservación de formatos.
