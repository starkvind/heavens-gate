# Manual 13 — Mapa de dominios

app/domains es la capa de acceso a datos y lógica compartida. Estos son los propietarios actuales y el tipo de contenido que controlan.

| Dominio | Responsabilidad |
|---|---|
| admin_usage | Telemetría agregada de uso del Admin. |
| bibliography | Bibliografía pública. |
| chapters | Temporadas, capítulos y participación. |
| characters | Personajes, ficha, afiliaciones, rasgos, recursos y clonado Admin. |
| chronicles | Crónicas. |
| configuration | Configuración global de runtime. |
| csp | Posts/tablón CSP. |
| dice | Tiradas y perfiles de dados. |
| documents | Documentos. |
| errors | Datos auxiliares de páginas de error. |
| forum | Integraciones de foro. |
| gallery | Galería. |
| home | Datos de portada. |
| inventory | Objetos e inventario. |
| maps | Mapas, áreas y POI. |
| navigation | Navegación/menú. |
| news | Noticias. |
| organizations | Organizaciones y grupos. |
| parties | Partidas. |
| players | Jugadores. |
| powers | Dones, ritos, disciplinas y tótems. |
| relationships | Relaciones/árboles. |
| rules | Rasgos, acciones, maniobras, arquetipos, méritos/defectos. |
| search | Búsqueda. |
| soundtracks | Banda sonora. |
| systems | Sistemas, formas, recursos y categorías. |
| timeline | Eventos y relaciones temporales. |

## Cómo usar este mapa

Si un controlador necesita datos, busca primero su dominio. No abras una consulta mysqli nueva dentro del controlador si el dominio ya es propietario.

## Controladores por área

app/controllers se organiza principalmente en:

- bio: personajes, organizaciones y relaciones;
- chapters: temporadas/capítulos;
- docs: documentos, inventario y reglas;
- main: home, noticias, timeline, búsqueda, galería, etc.;
- maps: mapas;
- ost: banda sonora;
- playr: jugadores;
- pwrs: poderes;
- systems: sistemas;
- tool: herramientas/APIs;
- admin: backend editorial.

Los nombres históricos de carpetas no cambian la propiedad de datos: para SQL compartido manda app/domains.

## Helpers

app/helpers contiene infraestructura transversal: conexión, autenticación, uploads, pretty IDs, assets, schema introspection, menciones, mapas, tool API y utilidades compartidas.

No conviertas helpers en un segundo app/domains: una consulta propia de personajes pertenece a characters, una de timeline a timeline, etc.
