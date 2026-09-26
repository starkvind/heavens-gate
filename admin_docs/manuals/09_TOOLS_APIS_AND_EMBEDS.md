# Manual 09 — Tools, APIs y embeds

## Regla de superficie

Un fichero físico bajo app/tools o app/controllers/tool no es público por existir. Toda superficie pública debe estar registrada en routing y pasar por el front controller.

## Herramientas públicas principales

- /tools/dice
- /tools/forum-avatar
- /tools/forum-topic-viewer
- /tools/crop

Consulta ROUTE_DICTIONARY.md para route keys exactos y endpoints bare asociados.

## APIs

Los endpoints de dados y avatar usan autenticación compartida de app/helpers/tool_api.php. No reintroduzcas tokens en query string.

## Embeds de foro

Los snippets de mensaje, tirada, objeto y el script forum-avatar-embed.js forman un contrato con el foro. Cambios de markup o resize deben probarse contra el embed real.

## Adaptador externo

app/tools/forum_topic_viewer_tool.php puede inspeccionar el esquema SMF externo. Esa introspección está permitida y clasificada por CI.

## Verificación

    python3 .github/ci/php-domain-query-boundary-audit.py
    bash tools/production_smoke.sh

El smoke incluye contratos de foro y herramientas principales.
