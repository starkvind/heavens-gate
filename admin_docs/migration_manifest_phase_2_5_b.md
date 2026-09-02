> **Registro histórico de migración — 2026-09-01.** Este documento conserva decisiones y pasos de la fase 2.5. No describe por sí solo el runtime ni el esquema vigente. Para el estado actual, consulta `admin_docs/TECHNICAL_DOCUMENTATION.md` y `admin_docs/DATABASE_SCHEMA.md`.

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

**Heaven's Gate** es una única crónica aunque sus acontecimientos atraviesen varias realidades. **Gaia2β aparece canónicamente en la Décima Temporada**, pero la relación entre temporada y realidad no se almacena de forma directa: se deriva de los eventos de sus episodios.

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
  ├── PERSONAJE
  └── EVENTO
```

Ejemplo canónico:

```text
CRO-HEAVENS-GATE
  └── Temporada 10
        └── Episodios
              └── Eventos
                    └── entre sus realidades: REA-GAIA2-B
```

Una misma crónica, temporada o episodio puede, por tanto, recorrer varias realidades a través de sus eventos.

## Resolución de realidades en capítulos y temporadas

No debe existir una relación canónica directa entre `dim_seasons` y `dim_realities`, ni entre `dim_chapters` y `dim_realities`.

La realidad es una propiedad de los **eventos**. La BDD ya dispone de la relación:

```text
CAPÍTULO
  ↓
bridge_timeline_events_chapters
  ↓
EVENTO
  ↓
bridge_timeline_events_realities
  ↓
REALIDAD
```

Por tanto, las realidades recorridas por un capítulo se obtienen derivando las realidades de sus eventos:

```text
Episodio → Eventos → Realidades
```

Y las de una temporada se obtienen agregando las de todos sus capítulos:

```text
Temporada → Episodios → Eventos → Realidades
```

Esta derivación evita duplicar información y elimina el riesgo de inconsistencias entre una relación temporada↔realidad y la cronología efectiva.

### Criterio técnico

No crear:

- `dim_seasons.reality_id`
- `bridge_seasons_realities`
- `dim_chapters.reality_id`
- `bridge_chapters_realities`

Si la interfaz necesita consultar estas relaciones con frecuencia, pueden resolverse mediante una **vista SQL derivada** o una consulta agregada, pero nunca como una segunda fuente de verdad.

## Invariantes de migración

1. Ningún registro debe migrarse basándose únicamente en su ID numérico.
2. El `pretty_id` es URL/alias, no identidad ontológica.
3. Los nombres de mesa o autoría deben separarse de los nombres editoriales.
4. `Gaia2β` y `Gaia1β` son conceptos distintos; no se fusionan por compartir sufijo beta.
5. Gaia2β es una realidad canónica utilizada por acontecimientos de la Décima Temporada de Heaven's Gate.
6. La realidad de un capítulo se deriva de las realidades asociadas a sus eventos.
7. La realidad de una temporada se deriva de las realidades asociadas a los eventos de sus capítulos.
8. No se almacenará una segunda relación temporada↔realidad o capítulo↔realidad.
9. Los eventos pueden pertenecer a varias realidades mediante bridge cuando el contenido lo exija.
10. Las relaciones entre realidades no deben modelarse como una jerarquía de carpetas.

## Siguiente bloque

Antes de migrar personajes, debe auditarse la cadena capítulo → evento → realidad y comprobar que permite reconstruir correctamente las realidades recorridas por cada episodio y temporada.

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
