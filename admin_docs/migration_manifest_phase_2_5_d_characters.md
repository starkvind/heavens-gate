> **Registro histórico de migración — 2026-09-01.** Este documento conserva decisiones y pasos de la fase 2.5. No describe por sí solo el runtime ni el esquema vigente. Para el estado actual, consulta `admin_docs/TECHNICAL_DOCUMENTATION.md` y `admin_docs/DATABASE_SCHEMA.md`.

# Heaven's Gate — Fase 2.5-D: auditoría de personajes

Fecha: 2026-09-01

## Objetivo

Antes de renombrar, fusionar o reclasificar personajes, la BDD debe producir una lista de **candidatos a colisión**.

Una coincidencia no implica identidad. En Heaven's Gate pueden existir contrapartes entre realidades, reutilizaciones de nombres y registros que solo parecen duplicados desde una búsqueda textual.

## Fuente de verdad

La auditoría trabaja con `fact_characters` y añade contexto cuando existe:

- crónica;
- realidad;
- organización;
- grupo;
- `pretty_id`.

Se comparan de forma normalizada:

- `name`;
- `alias`;
- `garou_name`.

La normalización elimina diferencias de mayúsculas, acentos, puntuación y espacios. No usa distancia de Levenshtein ni otras fusiones difusas: en esta fase preferimos falsos negativos a proponer fusiones falsas.

## Implementación

Pantalla administrativa:

`/talim?s=admin_character_collision_audit`

Archivo:

`app/controllers/admin/admin_character_collision_audit.php`

Es **solo lectura**. No contiene `INSERT`, `UPDATE` ni `DELETE`.

También informa de:

- `pretty_id` repetidos;
- personajes sin crónica resoluble;
- personajes sin realidad resoluble, cuando el esquema de realidades está disponible.

## Criterio de decisión posterior

Cada colisión deberá terminar clasificada como una de estas situaciones:

- duplicado real;
- contraparte multiversal;
- reutilización legítima de nombre;
- alias compartido;
- nombre sobrenatural compartido;
- error de datos;
- caso pendiente de documentación.

No se aplicará ninguna fusión automática por coincidencia textual.
