# Gaia0 — auditoría editorial inicial

Fecha: 2026-09-01

## Objetivo

Esta auditoría sirve a tres fines concretos del proyecto:

1. detectar qué información fiable falta en la web;
2. localizar en el corpus/Drive únicamente las fuentes necesarias para rellenar esos huecos;
3. preparar cambios SQL pequeños, verificables e idempotentes para producción.

No se utilizarán conversaciones privadas, exportaciones de chat, `Pegado text`, mensajería personal ni archivos ajenos a la documentación de continuidad salvo autorización explícita o necesidad concreta aprobada.

## Fuentes permitidas para Gaia0

### Nivel A — uso directo

- `gaia0/cronologia.md`
- `realidades/registro_de_realidades.md`
- `canon_ledger.md`
- `02_registro_de_fuentes.md`
- manifiestos derivados de BDD

### Nivel B — uso cuando haya un hueco concreto

- `01 - Resumen - Partida Original.md`
- Diario de Ampw
- otros diarios/logs primarios identificados por el registro de fuentes
- documentación específica de un personaje, organización, objeto o evento

### Excluido por defecto

- conversaciones privadas;
- chats de WhatsApp/Telegram/Discord;
- exportaciones de conversaciones;
- archivos `Pegado text` o equivalentes;
- documentos personales no registrados como fuente de continuidad.

## Fotografía disponible

El último inventario derivado accesible todavía corresponde al dump anterior a la incorporación de Gaia1-B. Por tanto, sus cifras sirven para localizar huecos de Gaia0, pero no como conteo definitivo de la BDD actual.

En ese snapshot:

- Gaia0 existe en `dim_realities`;
- solo 1 personaje está asignado a Gaia0;
- ese personaje es Karlos Kabarga;
- no hay vínculos de eventos con Gaia0 en `bridge_timeline_events_realities`;
- `Aullidos en el Norte` sí contiene personajes de Gaia0/Gaia1.

El SQL `sql/audit_gaia0_content.sql` obtiene la fotografía exacta de la BDD actual.

## Huecos Gaia0 ya demostrados documentalmente

### Timeline

El corpus curado contiene suficientes hechos para poblar una primera cronología pública de Gaia0.

Bloques confirmados:

- verano de 1998 — formación inicial de Zarpas de Teluria;
- primavera-verano de 1999 — duelo Felipe/Ampw y muerte de Ampw;
- finales de 2000 — Torre de La Cañada / Proyecto Garou Combat;
- primavera de 2001 — entrada en juego del Tapete;
- finales de 2001 — viaje a La Marca de la Ceniza y regreso;
- 2002 — fase terminal de Gaia0;
- 24 de diciembre de 2002 — caída de Gaia0.

La fecha exacta del tránsito Gaia0 → Gaia1 **no está resuelta** y no debe fijarse todavía.

### Realidad

La descripción pública actual de Gaia0 es correcta como resumen, pero demasiado breve para funcionar como ficha editorial. Puede enriquecerse con información ya confirmada sin resolver el conflicto de fechas.

### Personajes

No se debe reasignar masivamente `fact_characters.reality_id` todavía.

Los Zarpas atraviesan Gaia0 → Gaia1. Antes de tocar ese campo hay que fijar qué semántica queremos que tenga para personajes que recorren varias realidades: versión ontológica, realidad de origen o realidad principal.

### Grupo Zarpas de Teluria

Debe auditarse cómo representa la BDD la manada histórica de Aullidos en el Norte. No crear un duplicado hasta comprobar el registro existente y su `chronicle_id`.

### Tapete

Debe comprobarse si existe ya como `fact_items`. Si no existe, es candidato prioritario: es un objeto estructural de Gaia0 y continúa teniendo relevancia posterior.

## Primer lote de publicación recomendado

Después de ejecutar la auditoría actual y descartar duplicados semánticos:

1. enriquecer la ficha de Gaia0;
2. añadir/enlazar eventos históricos de Gaia0 que falten;
3. enlazar esos eventos con `Aullidos en el Norte`;
4. no enlazar personajes automáticamente hasta resolver identidad multirrealidad;
5. comprobar y, si falta, crear el Tapete como objeto;
6. revisar Zarpas de Teluria dentro de la crónica Aullidos antes de crear un nuevo grupo.

## Criterio de calidad

Un alta en la web debe indicar siempre qué fuente la sostiene. Si el dato es aproximado, se usará `date_precision` / `date_note` en lugar de inventar una fecha exacta.
