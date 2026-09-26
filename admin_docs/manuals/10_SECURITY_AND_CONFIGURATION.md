# Manual 10 — Seguridad y configuración

## config.env

No se versiona. Debe contener credenciales de BDD y vivir preferentemente fuera de la raíz pública.

## .htaccess

Es frontera crítica. Bloquea:

- .git/.github y metadatos;
- config.env;
- app/;
- admin_docs/;
- ficheros reales bajo tools/;
- árboles internos;
- scripts ejecutables dentro de uploads/assets de imagen.

No muevas el bypass de ficheros existentes por encima de los guards privados.

## Admin

Sesión y CSRF usan helpers comunes. Nunca autentiques por is_admin enviado por el cliente.

## Tool APIs

Usa app/helpers/tool_api.php y Bearer/shared request token. No aceptes tokens sensibles por URL.

## Output

app/http/output.php controla cabeceras globales. La respuesta HTML dinámica usa revalidación obligatoria; no cambies a no-store global sin revisar impacto.

## Verificación

Project CI contiene guards de seguridad adicionales. Después de tocar .htaccess, auth, headers o tool APIs:

    bash tools/production_smoke.sh

Y revisa específicamente que /app, /admin_docs, /.github y /tools/<fichero-real> no sean accesibles.
