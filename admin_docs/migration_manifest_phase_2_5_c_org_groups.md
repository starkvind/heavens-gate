# Heaven's Gate — Manifest canónico de migración

## Fase 2.5-C — Organizaciones y Grupos

Fecha: 2026-09-01  
Estado: bloque inicial, basado en la instantánea versionada de BDD

## Regla conceptual

En el nuevo modelo, **Organización** y **Grupo** no son sinónimos.

- **Organización (`ORG-`)**: estructura social, política, institucional o territorial con existencia propia. Ejemplos: Peñasco Blanco, Viento de Acero, Justicia Metálica.
- **Grupo (`GRP-`)**: unidad concreta de personajes que actúa de forma conjunta dentro o fuera de una organización. En material Garou suele corresponder a una manada.
- **Party operativa**: agrupación temporal usada por la aplicación para saber qué personajes están jugando juntos en una trama concreta. No debe confundirse automáticamente con un grupo canónico.

La BDD ya refleja parcialmente esta distinción mediante `dim_organizations`, `dim_groups`, `bridge_organizations_groups` y `dim_parties`.

## Organizaciones confirmadas en la instantánea

| Legacy ID | Nombre | Canon ID | Acción | Estado |
|---:|---|---|---|---|
| 1 | Peñasco Blanco | `ORG-PENASCO-BLANCO` | KEEP | CANON |
| 2 | Viento de Acero | `ORG-VIENTO-ACERO` | KEEP | CANON |
| 3 | Justicia Metálica | `ORG-JUSTICIA-METALICA` | KEEP | CANON |
| 4 | Fortaleza Crepuscular | `ORG-FORTALEZA-CREPUSCULAR` | KEEP | CANON |
| 5 | Noche Fría | `ORG-NOCHE-FRIA` | KEEP | CANON |

### Observación de modelado

Los cinco registros están correctamente situados como organizaciones: son clanes, estructuras territoriales o colectivos estables. No deben degradarse a simples grupos/manadas durante la migración.

## Grupos confirmados en la instantánea

| Legacy ID | Nombre | Canon ID | Chronicle legacy | Crónica canónica | Acción | Estado |
|---:|---|---|---:|---|---|---|
| 1 | Zarpas de Teluria | `GRP-ZARPAS-DE-TELURIA` | 1 | `CRO-HEAVENS-GATE` | KEEP + REVIEW | CANON |
| 2 | Manada del Ensueño | `GRP-MANADA-DEL-ENSUENO` | 1 | `CRO-HEAVENS-GATE` | KEEP | CANON |
| 3 | Los Ángeles de Gaia | `GRP-ANGELES-DE-GAIA` | 1 | `CRO-HEAVENS-GATE` | KEEP | CANON |
| 4 | Corazón de Furia | `GRP-CORAZON-DE-FURIA` | 1 | `CRO-HEAVENS-GATE` | KEEP | CANON |
| 5 | Hijas de la Pasión | `GRP-HIJAS-DE-LA-PASION` | 1 | `CRO-HEAVENS-GATE` | KEEP | CANON |

### Zarpas de Teluria

La instantánea define Zarpas de Teluria como una manada de Heaven's Gate y la vincula a Peñasco Blanco mediante `bridge_organizations_groups`.

El nombre también existe en la Partida Original/Gaia1. Por tanto, el registro heredado de Heaven's Gate no debe asumirse automáticamente como identidad multiversal única. En la futura migración de personajes y grupos duplicados habrá que decidir si:

- existe una **entidad conceptual** Zarpas de Teluria con encarnaciones por realidad; o
- existen dos grupos canónicos distintos, uno en Gaia1 y otro en Gaia2, relacionados como contrapartidas.

Hasta resolver esa capa, se marca `KEEP + REVIEW` y no `MERGE`.

## Relaciones organización → grupo confirmadas

La instantánea de `bridge_organizations_groups` confirma:

| Legacy bridge | Organización | Grupo | Relación canónica |
|---:|---|---|---|
| 1 | Peñasco Blanco | Zarpas de Teluria | `ORG-PENASCO-BLANCO → GRP-ZARPAS-DE-TELURIA` |
| 2 | Viento de Acero | Manada del Ensueño | `ORG-VIENTO-ACERO → GRP-MANADA-DEL-ENSUENO` |
| 3 | Justicia Metálica | Los Ángeles de Gaia | `ORG-JUSTICIA-METALICA → GRP-ANGELES-DE-GAIA` |
| 4 | Viento de Acero | Corazón de Furia | `ORG-VIENTO-ACERO → GRP-CORAZON-DE-FURIA` |
| 5 | Viento de Acero | Hijas de la Pasión | `ORG-VIENTO-ACERO → GRP-HIJAS-DE-LA-PASION` |

Estas cinco relaciones son coherentes con el modelo nuevo y deben conservarse.

## Parties operativas

`dim_parties` contiene, entre otras:

| Legacy ID | Nombre | Tratamiento |
|---:|---|---|
| 1 | Manada de Mark (2005) | PARTY operativa; revisar si además corresponde a un GRP canónico |
| 2 | Ícaros en Creta (2005) | PARTY operativa; no convertir automáticamente en organización |
| 3 | Misión de Brisa (2007) | PARTY operativa; agrupación de trama |
| 4 | Grupo de Bruma (2005) | PARTY operativa; agrupación de trama |
| 5 | Zona de pruebas | ARCHIVE / TEST |

**Decisión:** `dim_parties` no se fusiona con `dim_groups`.

Una party responde a «¿quién está jugando/actuando junto ahora?». Un grupo responde a «¿qué unidad existe dentro de la ficción?». A veces coincidirán, pero deben seguir siendo entidades distintas y relacionables.

## Invariantes

1. Una organización puede contener muchos grupos.
2. Un grupo puede cambiar de organización a lo largo del tiempo; el bridge debe poder conservar historia y no solo estado actual.
3. Un personaje puede pertenecer a una organización sin pertenecer a un grupo.
4. Un personaje puede pertenecer a un grupo sin que ese grupo sea su única afiliación institucional.
5. Una party no implica pertenencia canónica.
6. Ninguna party de pruebas debe contaminar el catálogo público.
7. Los grupos con contrapartidas entre Gaia1/Gaia2 no se fusionarán por nombre.
8. El `chronicle_id` heredado de `dim_groups` se valida contra el manifest de crónicas antes de migrar.

## Problema de esquema detectado

`bridge_organizations_groups` solo conserva `is_active` y timestamps. Para una migración verdaderamente histórica puede quedarse corto: no permite expresar de forma explícita **desde cuándo** y **hasta cuándo** perteneció un grupo a una organización, ni el motivo del cambio.

Antes de tocar producción, conviene valorar añadir:

```text
joined_at
left_at
relation_type
notes
source
```

No es imprescindible para mantener la web actual, pero sí para convertirla en el archivo histórico/relacional que estamos diseñando.

## Próximo objetivo

El siguiente bloque debe atacar **Personajes**, pero no migrarlos todavía en masa.

Primero hay que generar una tabla de colisiones por:

- nombre normalizado;
- alias;
- nombre Garou;
- crónica;
- realidad;
- organización;
- grupo.

El objetivo será distinguir `KEEP`, `RENAME`, `MERGE` y, sobre todo, **contrapartidas multiversales que no deben fusionarse**.
