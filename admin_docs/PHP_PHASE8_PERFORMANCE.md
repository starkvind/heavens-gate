# PHP Phase 8 — Performance pass

Última revisión: 2026-09-25.

## Alcance

Fase de rendimiento conservadora sobre `php-refactor`.

No introduce rediseño visual, caché de aplicación, cambios de seguridad, cambios de esquema ni retirada del frontend móvil. La optimización se limita a costes demostrables en código que afectan a superficies públicas activas.

El entorno de ejecución del asistente no puede resolver el dominio DuckDNS de producción, por lo que no se registran tiempos de pared inventados. Las métricas de esta fase son unidades de trabajo objetivas del runtime y red, verificables en producción durante el smoke.

## Cambios

### Home

`hg_home_query_counts()` realizaba 13 consultas independientes de conteo.

Ahora obtiene los mismos contadores mediante una sola consulta SQL con subconsultas escalares.

Métrica:

- round-trips de contadores: **13 -> 1**.

La noticia más reciente y el feed reciente siguen siendo consultas separadas porque tienen semántica y ciclos de cambio distintos.

### Reglamento

`hg_rules_fetch_home_counts()` realizaba una consulta por catálogo visible.

Ahora obtiene los seis contadores en una sola consulta.

Métrica:

- round-trips de contadores: **6 -> 1**.

### Contenido reciente

El feed de portada construía un `UNION ALL` de diez tablas completas y después lo cruzaba con `fact_content_updates`.

Ahora parte de `fact_content_updates` y hace joins condicionados por `entity_type` a la entidad concreta.

Métrica estructural:

- ramas de catálogo materializadas mediante `UNION ALL`: **10 -> 0**.

Se conserva el orden y el descarte de referencias a entidades inexistentes.

### Gallery

La Gallery descargaba/preparaba todas las imágenes completas de una carpeta al cargar la página, aunque el usuario no abriese el lightbox.

Ahora:

- la carga inicial solicita miniaturas;
- las imágenes completas se solicitan al abrir el lightbox;
- después se precargan únicamente anterior/siguiente;
- las primeras miniaturas/cubiertas visibles son eager y el resto lazy;
- se fijan dimensiones de miniatura/cubierta para preservar geometría.

Métrica:

- precargas de imágenes completas en carga inicial: **N -> 0**.

### Characters

DataTables marcaba todos los avatares como `loading="lazy"`, incluidos los 25 de la primera página visible.

Ahora:

- primeros 25: eager;
- resto: lazy;
- decodificación: async.

### Rules artwork

Las primeras tarjetas del Reglamento, visibles al entrar, dejan de estar forzadas a lazy. El resto conserva lazy loading.

## Guardia CI

`.github/ci/php-phase8-performance-audit.py`

Protege:

- contadores Home en una consulta;
- contadores Rules en una consulta;
- ausencia del UNION completo del feed reciente;
- ausencia de precarga total de Gallery;
- prioridad inicial de imágenes visibles.

## Fuera de alcance deliberado

- índices MariaDB sin EXPLAIN/medición de producción;
- caché de resultados;
- Redis/Memcached;
- cambios en OPcache/Apache/PHP-FPM;
- optimización del renderer móvil legacy;
- retirada de `?view=mobile`;
- compresión/redimensionado masivo de imágenes.

Esos cambios requieren evidencia específica o pertenecen a otra fase.

## Cierre

La fase se considera implementada cuando:

1. PHP Refactor Characterization pasa;
2. Project CI pasa;
3. el guard de Fase 8 pasa;
4. Raspberry smoke confirma contenido, Gallery/lightbox, imágenes y navegación sin regresión.
