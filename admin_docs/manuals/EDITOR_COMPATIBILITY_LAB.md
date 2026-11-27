# Laboratorio de editores HTML

Experimento aislado sobre la rama de sanitización. No modifica los editores de la aplicación, no incorpora dependencias npm al despliegue y no usa datos históricos.

Compara Quill 2.0.3 (módulo de tablas, importación y exportación oficiales) y Jodit 4.21.0 (MIT, edición HTML y tablas). La prueba usa el mismo corpus sintético saneado con HTML Purifier que la regresión de Quill 1.3.7. Se comparan encabezados, negritas, cursivas, enlaces, metadatos de menciones, tablas, celdas, rowspan, colspan y texto antes/después de una edición. CI registra auditoría npm y tamaño instalado; esto no mide RAM de Raspberry ni garantiza ausencia de vulnerabilidades.

Quill 2.0.3 tiene una advertencia de seguridad conocida (CVE-2025-15056); no se acepta por el mero hecho de superar formatos. Jodit tiene antecedentes de XSS y exige comprobar la versión concreta y entradas del portapapeles. CKEditor 5 queda fuera del laboratorio inicial: el repositorio no declara licencia y su distribución comunitaria es GPL 2+; no se presupone compatibilidad ni se añade una dependencia que requiera decidir licencia.

Referencias: https://quilljs.com/docs/modules ; https://github.com/xdan/jodit/security/advisories ; https://www.npmjs.com/package/jodit ; https://ckeditor.com/docs/ckeditor5/latest/getting-started/licensing/license-and-legal.html .

No se considera candidato apto sin preservación de contenido, revisión de advisories, prueba de pegado, integración de menciones, validación del corpus real y pruebas de despliegue. El laboratorio instala versiones directas exactas pero todavía no tiene lock npm versionado; no es una instalación de producción.

## Resultado inicial — ejecución 46063163243

Quill 2.0.3: formato básico y texto conservados, pero la URL relativa exacta del fixture no se conserva en la exportación, se pierden los atributos de mención y colspan/rowspan. Conserva cuatro celdas. npm audit: 1 alerta leve. No apto tal cual.

Jodit 4.21.0: conserva todas las estructuras y texto de ambos fixtures tras abrir y editar. npm audit: 0 alertas en esta ejecución. Es el candidato preferente para nuevas pruebas, no una decisión definitiva de sustitución. Los tamaños de node_modules son 7396 KiB y 34860 KiB, respectivamente; incluyen archivos ajenos al despliegue y no miden transferencia ni RAM. La segunda prueba registra los bytes de los assets minificados, la edición dentro de una celda combinada y el saneamiento de la exportación en PHP. El contexto file:// del primer laboratorio no reproduce la resolución de enlaces bajo HTTPS; el resultado sobre URL es de igualdad exacta, no prueba de desaparición del enlace.
