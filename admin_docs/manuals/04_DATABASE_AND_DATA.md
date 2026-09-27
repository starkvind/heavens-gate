# Manual 04 — Base de datos y acceso a datos

## Conexión

app/helpers/db_connection.php es el propietario de la conexión mysqli. config.env aporta MYSQL_HOST, MYSQL_USER, MYSQL_PWD y MYSQL_BDD.

## Modelo

- dim_*: catálogos/entidades maestras.
- fact_*: contenido, hechos y registros.
- bridge_*: relaciones N:M.

Estado documentado: 102 tablas, sin vistas ni procedimientos.

## Acceso desde PHP

Las consultas públicas compartidas viven en app/domains/<dominio>/queries.php.

La introspección de esquema está restringida a propietarios clasificados por CI; no uses information_schema por comodidad.

## Slugs

pretty_id es identidad pública. Las relaciones internas usan id. fact_pretty_id_aliases conserva URLs antiguas.

## Cambios de esquema

No existe un directorio SQL operativo en producción.

Para DDL:

1. diseña y revisa fuera de master;
2. localiza consumidores;
3. revisa FKs;
4. backup;
5. aplica con cuenta de mantenimiento;
6. smoke;
7. snapshot;
8. actualiza DATABASE_SCHEMA.md;
9. archiva SQL y evidencia en continuity.

Nunca metas DDL dentro de un controlador web.
