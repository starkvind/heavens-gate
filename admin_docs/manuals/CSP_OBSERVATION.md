# CSP — observación y transición

La política existente de administración (frame-ancestors 'none' y X-Frame-Options DENY) se mantiene. La propuesta no toca .htaccess, PHP ni producción. El fichero de ejemplo no se carga automáticamente. No instalar como configuración global.

## Activación manual exclusivamente en preproducción

1. Verificar primero las cabeceras de la instancia real con curl -sS -D - -o /dev/null URL y revisar el VirtualHost HTTPS, mod_headers, posibles proxies y políticas duplicadas.
2. Copiar las directivas de admin_docs/security/csp-report-only.conf.example dentro del VirtualHost aislado de pruebas. Ejecutar sudo apache2ctl configtest y, únicamente si es correcto, sudo systemctl reload apache2. Confirmar que la cabecera Report-Only se recibe y no se ha sustituido la política obligatoria existente.
3. Abrir DevTools, conservar el log y navegar por inicio, biografías, episodios, documentos, poderes, mapas, foro, PWA y Talim autenticado. Probar login/logout, edición, subida de imágenes, menciones y AJAX. Revisar escritorio y móvil. Registrar directiva, recurso y componente sin copiar cookies, tokens, URLs sensibles ni contenido privado.
4. Resolver scripts inline y atributos de eventos mediante listeners externos o nonces/hashes adecuados. Revisar conexiones, fuentes, embeds y workers reales. No agregar unsafe-inline a script-src para silenciar errores.
5. Repetir hasta que no haya infracciones legítimas sin resolver. Pasar a política obligatoria mediante PR y aprobación de despliegue separados. Mantener intacta la restricción de framing del admin. No imponer frame-ancestors global sin comprobar embeds.

El ejemplo es conservador para scripts y conexiones y deliberadamente permite estilos inline e imágenes HTTPS: no es la política final. No incluye colector remoto; se observa la consola local. La recepción de informes automáticos exigiría un endpoint separado con límites de tamaño y frecuencia, privacidad y retención. No exponer un colector en esta fase. Report-Only no bloquea ataques.

Rollback: retirar únicamente la directiva de observación del VirtualHost de pruebas, validar configuración y recargar. No eliminar la CSP obligatoria existente. No hay DDL ni cambios de datos.

Referencia: https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html .
