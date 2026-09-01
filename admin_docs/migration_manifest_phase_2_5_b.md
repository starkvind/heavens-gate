# Heaven's Gate — Manifest canónico de migración

## Fase 2.5-B — Realidades y Crónicas

Fecha: 2026-09-01  
Estado: primer bloque operativo  
Repositorio: `starkvind/heavens-gate`

## Objetivo

Este documento convierte el inventario de la BDD heredada en un mapa explícito de migración hacia un modelo canónico estable.

La BDD actual ya distingue `dim_realities` y `dim_chronicles`, por lo que esta fase no crea una capa conceptual nueva: normaliza nombres, IDs editoriales y pertenencias, y documenta los casos que no deben resolverse automáticamente.

## Convenciones

Acciones permitidas:

- `KEEP`: conservar entidad y significado.
- `RENAME`: misma entidad, nombre o identificador canónico distinto.
- `MERGE`: varios registros heredados representan una única entidad.
- `SPLIT`: un registro heredado contiene más de una entidad.
- `ARCHIVE`: conservar como histórico, sin uso canónico activo.
- `REVIEW`: no aplicar migración destructiva hasta resolver continuidad.

Los IDs numéricos heredados nunca se consideran identidad canónica. Son referencias de origen.

## Realidades canónicas

| Canon ID | Nombre editorial | Estado | Nota |
|---|---|---|---|
| `REA-GAIA0` | Gaia0 | CANON | Realidad original previa al colapso/fractura. |
| `REA-GAIA1` | Gaia1 | CANON | Continuidad de la Partida Original. |
| `REA-GAIA1-B` | Gaia1β | CANON | Identificador técnico estable; nombre editorial Gaia1β. |
| `REA-GAIA2` | Gaia2 | CANON | Continuidad principal de Heaven's Gate. |

### Colisión heredada detectada

El helper `app/helpers/pretty.php` conserva actualmente esta política:

```php
'dim_realities' => [
    'gaia2'  => 'gaia-2a',
    'gaia2β' => 'gaia-2b',
    'gaia1'  => 'gaia-1',
    'gaia0'  => 'gaia-zero',
],
```

La etiqueta heredada `Gaia2β` entra en conflicto con el modelo canónico actual, que utiliza `Gaia1β` / `REA-GAIA1-B`.

**Decisión:** no modificar todavía registros en producción. La correspondencia se marca como `RENAME + REVIEW` hasta confirmar qué entidades dependen actualmente de la realidad heredada y comprobar que no existe contenido legítimo que deba permanecer como una Gaia2β separada.

## Mapa de migración de realidades

| Origen heredado | Destino canónico | Acción | Confianza | Observación |
|---|---|---|---|---|
| Gaia0 / `gaia-zero` | `REA-GAIA0` / Gaia0 | KEEP | Alta | Normalización de identificador. |
| Gaia1 / `gaia-1` | `REA-GAIA1` / Gaia1 | KEEP | Alta | Partida Original. |
| Gaia2β / `gaia-2b` | `REA-GAIA1-B` / Gaia1β | RENAME + REVIEW | Media | Colisión nominal detectada; no ejecutar UPDATE global todavía. |
| Gaia2 / `gaia-2a` | `REA-GAIA2` / Gaia2 | KEEP | Alta | Heaven's Gate. |

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

| Legacy ID | Nombre heredado | Canon ID | Nombre canónico | Realidad | Acción | Confianza |
|---:|---|---|---|---|---|---|
| 1 | Heaven's Gate | `CRO-HEAVENS-GATE` | Heaven's Gate | `REA-GAIA2` | KEEP | Alta |
| 2 | Javi | `CRO-PARTIDA-ORIGINAL` | Partida Original | `REA-GAIA1` | RENAME | Alta |
| 3 | Werewolf GT | `CRO-WEREWOLF-GT` | Werewolf GT | — | REVIEW | Baja |
| 4 | HG: Tercer Ojo | `CRO-HG-TERCER-OJO` | Tercer Ojo | — | REVIEW | Media |
| 5 | HG: Babylon | `CRO-HG-BABYLON` | Babylon | `REA-GAIA2` | RENAME | Alta |

### Decisiones editoriales

**Heaven's Gate** es la crónica principal de Gaia2.

**Javi** no debe sobrevivir como nombre editorial. Es una etiqueta de mesa/autoría, no una entidad de ficción o catálogo. Se migra a **Partida Original**, manteniendo el legacy ID para trazabilidad.

**Babylon** pertenece al universo narrativo de Heaven's Gate y describe la historia antigua de Apae, los Caelesti y las Estigmas, por lo que se vincula a Gaia2.

**Werewolf GT** necesita revisión documental antes de asignarle realidad. La descripción heredada la define como derivada de la Partida Original pero ambientada en el universo de Heaven's Gate; esa frase no basta para decidir si comparte Gaia2, es una rama propia o debe quedar archivada.

**Tercer Ojo** necesita revisión de continuidad. Su relación con Mark Harley la vincula al material de Heaven's Gate, pero la pertenencia ontológica debe confirmarse antes de fijar `reality_id`.

## Regla estructural

La relación correcta es:

```text
REALIDAD
  └── CRÓNICA
        └── TEMPORADA / HISTORIA PERSONAL / ESPECIAL
```

Una crónica pertenece a una realidad canónica. Las temporadas pertenecen a una crónica. Los personajes pueden estar asociados a crónica y realidad por conveniencia de consulta, pero esa duplicidad debe validarse para impedir combinaciones incompatibles.

## Invariantes de migración

1. Ningún registro debe migrarse basándose únicamente en su ID numérico.
2. El `pretty_id` es una URL/alias, no la identidad ontológica.
3. Los nombres de mesa o autoría deben separarse de los nombres editoriales.
4. `REA-GAIA1-B` es el identificador técnico estable de Gaia1β.
5. No se ejecutarán fusiones o renombrados destructivos sobre `Gaia2β` hasta auditar dependencias.
6. Una temporada no puede quedar sin crónica.
7. Una crónica canónica no puede quedar sin realidad salvo mientras figure explícitamente como `REVIEW`.
8. Las relaciones entre realidades no deben modelarse como jerarquía padre/hijo; son continuidades relacionadas, no carpetas.

## Siguiente bloque

Una vez auditados `Werewolf GT`, `Tercer Ojo` y las dependencias de `Gaia2β`, la Fase 2.5-B continúa con:

```text
Realidades
  ↓
Crónicas
  ↓
Organizaciones
  ↓
Grupos
  ↓
Personajes
```

El siguiente manifest debe inventariar `dim_organizations`, `dim_groups` y `bridge_organizations_groups`, preservando de forma estricta la diferencia entre organización y grupo.
