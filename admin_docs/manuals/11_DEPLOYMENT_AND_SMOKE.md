# Manual 11 — Despliegue y smoke

## Despliegue normal en Raspberry

Desde el checkout de producción:

    git switch master
    git fetch origin
    git reset --hard origin/master

Usa reset --hard solo cuando sepas que la Raspberry no contiene cambios locales que quieras conservar.

## Antes

    git status
    git rev-parse HEAD

Comprueba que config.env siga fuera del repo y que el servicio web/BBDD estén operativos.

## Después

    bash tools/production_smoke.sh

El smoke debe terminar con Failures: 0.

## Comprobación manual mínima

Abre:

- /home;
- una ficha de personaje;
- una temporada/capítulo;
- /timeline;
- /talim;
- cualquier área tocada por el despliegue.

Si cambiaste móvil, prueba ?view=mobile. Si cambiaste foro, prueba un embed real.

## BDD

Un despliegue de código normal no ejecuta migraciones. Si un cambio requiere DDL, trátalo como operación separada con backup, verificación y archivo en continuity.

## Rollback de código

Identifica el commit bueno y revierte mediante Git; no copies ficheros sueltos a mano salvo emergencia documentada.
