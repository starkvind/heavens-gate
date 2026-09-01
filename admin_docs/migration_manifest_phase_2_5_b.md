# Heaven's Gate — Manifest canónico de migración

## Fase 2.5-B — Realidades y Crónicas

Fecha: 2026-09-01  
Estado: corregido tras revisión de continuidad  
Repositorio: `starkvind/heavens-gate`

## Objetivo

Este documento convierte el inventario de la BDD heredada en un mapa explícito de migración hacia un modelo canónico estable.

La BDD actual distingue `dim_realities` y `dim_chronicles`. Esas dos dimensiones no forman una jerarquía rígida: una misma crónica puede atravesar varias realidades a lo largo de sus temporadas.

## Corrección de continuidad

`Gaia2β` **existe como realidad canónica propia** y es la realidad de la **Décima Temporada de Heaven's Gate**.

No es un alias antiguo de Gaia1β ni debe renombrarse o fusionarse con ella.

El helper `app/helpers/pretty.php` que conserva `gaia2β => gaia-2b` refleja una entidad legítima.

## Convenciones

Acciones permitidas:

- `KEEP`: conservar entidad y significado.
- `CREATE`: entidad canónica que todavía no consta en la instantánea heredada.
- `RENAME`: misma entidad, nombre o identificador canónico distinto.
- `MERGE`: varios registros heredados representan una única entidad.
- `SPLIT`: un registro heredado contiene más de una entidad.
- `ARCHIVE`: conservar como histórico, sin uso canónico activo.
- `REVIEW`: no aplicar migración destructiva hasta resolver continuidad.

Los IDs numéricos heredados nunca se consideran identidad canónica. Son referencias de origen.

## Realidades

| Canon ID | Nombre editorial | Acción | Estado | Nota |
|---|---|---|---|---|
| `REA-GAIA0` | Gaia0 | KEEP | CANON | Realidad original previa al colapso/fractura. |
| `REA-GAIA1` | Gaia1 | KEEP | CANON | Continuidad principal de la Partida Original. |
| `REA-GAIA2` | Gaia2 | KEEP | CANON | Continuidad de Heaven's Gate hasta la Novena Temporada. |
| `REA-GAIA2-B` | Gaia2β | KEEP | CANON | Realidad de la Décima Temporada de Heaven's Gate. |
| `REA-GAIA1-B` | Gaia1β | CREATE / REVIEW | PENDIENTE | Rama propuesta de Gaia1; no confundir con Gaia2β. |

### Slugs heredados

El helper `app/helpers/pretty.php` conserva:

```php
'dim_realities' => [
    'gaia2'  => 'gaia-2a',
    'gaia2β' => 'gaia-2b',
    'gaia1'  => 'gaia-1',
    'gaia0'  => 'gaia-zero',
],
```

Tratamiento:

| Slug heredado | Entidad |
|---|---|
| `gaia-zero` | `REA-GAIA0` |
| `gaia-1` | `REA-GAIA1` |
| `gaia-2a` | `REA-GAIA2` |
| `gaia-2b` | `REA-GAIA2-B` |

No debe ejecutarse ninguna sustitución `Gaia2β → Gaia1β`.

## Crónicas heredadas confirmadas

La instantánea `admin_docs/bdd_structure.txt` conserva al menos cinco registros en `dim_chronicles`:

| Legacy ID | Nombre heredado | Descripción heredada |
|---:|---|---|
| 1 | Heaven's Gate | La partida en curso, de Maverick. |
| 2 | Javi | La partida de Javi, la original. |
| 3 | Werewolf GT | Partida derivada de la de Javi ambientada en el Universo de Heaven's Gate... |
| 4 | HG: Tercer Ojo | Pequeña partida que abarca la búsqueda de Mark Harley para localizar un método... |
| 5 | HG: Babylon | Historia de Heaven's Gate ambientada en la Antigua Sumeria. |

## Crónicas canónicas — primer pase

| Legacy ID | Nombre heredado | Canon ID | Nombre canónico | Acción | Confianza |
|---:|---|---|---|---|---|
| 1 | Heaven's Gate | `CRO-HEAVENS-GATE` | Heaven's Gate | KEEP | Alta |
| 2 | Javi | `CRO-PARTIDA-ORIGINAL` | Partida Original | RENAME | Alta |
| 3 | Werewolf GT | `CRO-WEREWOLF-GT` | Werewolf GT | REVIEW | Baja |
| 4 | HG: Tercer Ojo | `CRO-HG-TERCER-OJO` | Tercer Ojo | REVIEW | Media |
| 5 | HG: Babylon | `CRO-HG-BABYLON` | Babylon | RENAME | Alta |

### Decisiones editoriales

**Heaven's Gate** es una única crónica aunque atraviese más de una realidad. Sus temporadas 1–9 pertenecen a Gaia2 y la Décima Temporada pertenece a Gaia2β.

**Partida Original** sustituye el nombre editorial heredado `Javi`. El legacy ID se conserva solo como trazabilidad.

**Babylon** forma parte del corpus de Heaven's Gate, pero su realidad debe fijarse a nivel de temporada/obra concreta y no por una supuesta propiedad global de la crónica.

**Werewolf GT** y **Tercer Ojo** continúan en `REVIEW` hasta fijar sus relaciones exactas con crónicas y realidades.

## Modelo estructural corregido

La relación **NO** es:

```text
REALIDAD
  └── CRÓNICA
        └── TEMPORADA
```

El modelo correcto trata Crónica y Realidad como dimensiones ortogonales:

```text
CRÓNICA
  └── TEMPORADA
        └── CAPÍTULO

REALIDAD
  ├── TEMPORADAS (N:M)
  ├── PERSONAJE
  └── EVENTO
```

Ejemplo canónico:

```text
CRO-HEAVENS-GATE
  ├── Temporadas 1–9  → REA-GAIA2
  └── Temporada 10    → REA-GAIA2-B
```

Una misma crónica puede, por tanto, atravesar varias realidades.

## Gap de esquema detectado

La BDD actual ya contiene:

- `dim_seasons.chronicle_id`
- `fact_characters.reality_id`
- `bridge_timeline_events_realities.reality_id`

No se ha encontrado una relación explícita entre temporadas y realidades.

La relación correcta **no puede resolverse con `dim_seasons.reality_id`**, porque una misma temporada puede mostrarse o vincularse a varias realidades.

### Cambio de esquema recomendado

Añadir una tabla puente:

```sql
bridge_seasons_realities
```

con, como mínimo:

```text
season_id
reality_id
```

y una restricción única sobre `(season_id, reality_id)`.

Así, una temporada puede vincularse a una o varias realidades sin duplicar la temporada ni imponer una realidad única.

Ejemplo:

```text
Temporada 10
  ├── Gaia2β
  └── [otras realidades que correspondan]
```

No se recomienda añadir `reality_id` directamente a `dim_chronicles` ni a `dim_seasons`: ambas decisiones introducirían una cardinalidad 1:N que el canon y la interfaz no garantizan.

## Invariantes de migración

1. Ningún registro debe migrarse basándose únicamente en su ID numérico.
2. El `pretty_id` es URL/alias, no identidad ontológica.
3. Los nombres de mesa o autoría deben separarse de los nombres editoriales.
4. `Gaia2β` y `Gaia1β` son conceptos distintos; no se fusionan por compartir sufijo beta.
5. La Décima Temporada de Heaven's Gate debe estar vinculada a `REA-GAIA2-B`.
6. Una temporada puede estar vinculada a varias realidades mediante bridge.
7. Una temporada no puede quedar sin crónica.
8. La relación temporada ↔ realidad debe ser N:M.
9. Los eventos pueden pertenecer a varias realidades mediante bridge cuando el contenido lo exija.
10. Las relaciones entre realidades no deben modelarse como una jerarquía de carpetas.

## Siguiente bloque

Antes de migrar personajes, debe crearse/auditarse la relación N:M entre temporadas y realidades y comprobar qué realidades muestra cada temporada.

Después:

```text
Crónicas + Realidades
        ↓
    Temporadas
        ↓
Organizaciones / Grupos
        ↓
    Personajes
```
