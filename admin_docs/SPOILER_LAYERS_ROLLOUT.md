# Capas de spoilers y revelaciones — despliegue controlado

Issue: #72. Ámbito inicial: personajes de Heaven's Gate. **Estado: implementación en rama de trabajo, no desplegable hasta migración, auditoría y smoke.**

## Objetivo editorial

Cada ficha pública conserva su contenido actual. Un administrador puede crear una o varias capas suplementarias con título, texto plano y relaciones entre personajes, inicialmente ocultas. Solo una publicación explícita desde `/talim?s=admin_spoilers` las hace visibles, de manera global y reversible. No existe opción pública para desvelar contenido oculto.

Esto no debe confundirse con `fact_characters.notes`, que permanece exclusivamente administrativo y no participa en las capas.

## Contrato de persistencia (pendiente de confrontar con esquema vivo)

No introducir DDL en runtime ni guardar SQL operativo en este repositorio. La migración se diseña y ejecuta mediante el proceso de mantenimiento del proyecto, con respaldo previo. Los tamaños y tipos exactos de las FKs se verifican en la instalación antes de crear constraints.

**`fact_spoiler_layers`**:
- `id` PK positiva, autoincremental;
- `entity_kind` texto (la primera entrega admite únicamente `character`);
- `entity_id` entero positivo que identifica al personaje;
- `title` texto no nulo (longitud máxima de aplicación: 190 caracteres);
- `content_text` texto largo no nulo, por defecto vacío;
- `is_published` booleano no nulo, **DEFAULT 0**;
- `published_at` nullable;
- `created_at` y `updated_at` con control temporal de mantenimiento;
- índice compuesto `(entity_kind, entity_id, is_published, id)`.

**`bridge_spoiler_character_relations`**:
- `id` PK autoincremental; `layer_id` FK a la capa (y borrado restringido o cascada revisada);
- `source_id` y `target_id` FKs a `fact_characters.id`;
- `relation_type`, `tag`, `importance`, `description`, `arrows` con semántica equiparable a `bridge_characters_relations`, sujetos a validación y tipos del esquema real;
- índice `layer_id`, índices para `source_id` y `target_id`;
- **invariante:** el propietario de la capa es `(entity_kind = 'character', entity_id = source_id)`; la escritura de Admin lo comprueba y los lectores exigen el mismo vínculo, incluso ante datos anómalos.

No se añade una columna `is_spoiler` a `bridge_characters_relations`: los lectores antiguos seguirían viendo una relación secreta. Las relaciones nuevas se mantienen en su tabla separada; se añaden al read model público exclusivamente mediante JOIN a una capa publicada.

## Fronteras de visibilidad

- `app/domains/spoilers/queries.php` expone exclusivamente capas `is_published = 1` y aristas de capas publicadas.
- `app/domains/characters/detail_queries.php` integra aristas publicadas tanto de entrada como de salida, para biografías, familiares y nebulosa individual.
- `app/domains/relationships/queries.php` añade aristas publicadas al mapa general sin alterar las tablas públicas actuales.
- Las vistas de biografía móvil y escritorio muestran texto adicional solo si se ha publicado la capa.
- La exportación en texto de la biografía añade únicamente el texto de las capas ya publicadas.
- Tooltips, listados y búsqueda existentes no consultan el almacenamiento privado; las capas ocultas no tienen metadatos, títulos, recuentos ni relaciones expuestos por esos canales.
- **Atención editorial:** no introduzcas un spoiler en `info_text`, `notes` o una relación pública esperando que se oculte automáticamente. El secreto debe crearse *dentro* de una capa.

## Procedimiento previo a la publicación

1. Inspeccionar tipos, FKs, índices, charset y collation de `fact_characters` y `bridge_characters_relations` en la base real.
2. Hacer backup recuperable y comprobar restauración en staging; redactar migración y rollback fuera de la aplicación.
3. Instalar tablas en entorno de prueba, sin añadir datos secretos todavía.
4. Probar con personajes ficticios: una capa con texto, otra con relación; ambas privadas por defecto.
5. Verificar en móvil, escritorio, JSON/HTML de red, vista fuente, búsquedas, tooltips, nebulosas y exportación pública que **no aparece** el contenido oculto.
6. Publicar una capa desde Admin y verificar que su texto se incorpora a la biografía de origen y su relación aparece en las fichas de ambos personajes y en el mapa general.
7. Retirar esa capa y verificar que contenido y relaciones desaparecen de todas las superficies; comprobar recarga de cachés.
8. Comprobar CSRF inválido, sesión admin expirada y acceso anónimo a `/talim?s=admin_spoilers`. Probar relaciones con destino inexistente y self-links.
9. Ejecutar Project CI, Architecture Regression Checks y `bash tools/production_smoke.sh` en staging, así como pruebas visuales responsive.
10. Solo entonces autorizar despliegue y registrar fecha/resultado, esquema y evidencias en la documentación correspondiente.

## Límites actuales

- En la primera versión, `entity_kind` solo admite `character`. Las demás entidades requieren integración específica y auditoría por dominio.
- El texto de la capa es **texto plano**, escapado en servidor y con saltos de línea; no acepta HTML arbitrario.
- Las capas publicadas no indexan automáticamente su texto en el buscador general; ampliar el índice público exige integrar el filtro por `is_published`.
- El editor de capas no modifica las relaciones públicas preexistentes; mover una relación ya publicada a una capa secreta exige un proceso editorial explícito y comprobación previa de todas las superficies.
- El registro histórico de publicación (autor y eventos de publicación/retractación) queda pendiente antes de certificarse como sistema auditable de producción.
