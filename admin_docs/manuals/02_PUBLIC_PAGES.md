# Manual 02 — Páginas públicas

## Patrón

Una página pública normal separa:

- ruta;
- controlador;
- consultas de dominio;
- preparación/presentación;
- CSS/JS propios.

Los controladores están bajo app/controllers/. Las consultas reutilizables deben vivir bajo app/domains/<dominio>/.

## Regla de datos

Los controladores públicos y móviles no ejecutan SQL directamente. Llama a funciones del dominio correspondiente. Si falta una consulta, añádela al queries.php del dominio o a un helper especializado con propietario claro.

## Assets de página

Registra CSS con los helpers de app/helpers/page_assets.php. No inyectes links de stylesheet en mitad del body.

Para JavaScript, usa un asset de dominio y hooks data-* cuando sea posible.

## Respuestas bare

APIs, embeds, crop y algunos endpoints no usan el shell HTML normal. La condición bare pertenece a app/http/dispatch_policy.php.

## Alta segura

1. ruta;
2. route key;
3. controlador;
4. dominio de datos;
5. CSS/JS si corresponde;
6. menú si corresponde;
7. móvil solo si necesita implementación específica;
8. ROUTE_DICTIONARY.md;
9. CI + smoke.

Véase PUBLIC_SECTION_GUIDE.md para el procedimiento detallado.
