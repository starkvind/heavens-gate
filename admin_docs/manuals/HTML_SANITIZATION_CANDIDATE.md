# Política candidata de sanitización HTML

Piloto en documentos: guardado administrativo, apertura individual del editor y renderizado público escritorio/móvil. No hay migración de datos ni despliegue.

- Dependencia PHP: ezyang/htmlpurifier 4.19.1, fijada en app/security/composer.json, dentro del árbol app protegido por Apache.
- Generar y revisar composer.lock antes de integrar; ejecutar composer audit y conservar el lock. La CI de candidato resuelve la versión fijada para probarla.
- El helper no debe activarse sin una copia de los contenidos reales en un entorno aislado, comparación estructural/visual y validación de latencia/memoria PHP 8.2 ARMv7.
- Política inicial: HTML de texto, estilos restringidos, tablas, imágenes y enlaces; clases Quill permitidas y atributos de menciones tipados. La resolución y validación semántica de menciones queda pendiente.
- Límite candidato: 2 MiB de HTML por campo; revisar con el corpus. Ante error de instalación, tamaño o configuración, rechazar la escritura, nunca guardar HTML original como fallback.
- No autorizar atributos de eventos, contenido activo ni clases adicionales sin revisión. Revisar recursos externos, formatos históricos, enlaces y tablas.
- Integración posterior: sanitizar entradas y resultado de la conversión de menciones; tratar contenido histórico antes de mostrarlo o abrirlo en editores; validar todas las rutas de escritura.
- Caché opcional: directorio privado y escribible por PHP-FPM, nunca bajo una ruta pública. Medir uso en Raspberry antes de activarla.
- No cambia Quill, base de datos ni producción. No fusionar hasta validar CI, lock y corpus. El listado ya no transfiere cuerpos completos; el editor solicita un documento saneado. La conversión regex de menciones sigue pendiente de refactor; también la comprobación de ida y vuelta de tablas en Quill. El límite y la política de formatos no están aprobados para producción.
