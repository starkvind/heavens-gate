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

- Piloto ampliado a Noticias: escritura saneada, lectura individual autenticada y salida pública. Los listados no transportan cuerpos. En Documentos y Noticias las nuevas menciones se convierten únicamente en nodos de texto mediante DOM (requiere php-xml); no en enlaces existentes, atributos ni bloques de código. Las demás rutas conservan temporalmente el conversor antiguo.
