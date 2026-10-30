# Ubicaciones y mapa

[Índice de documentación](README.md) · [README del proyecto](../README.md)

Para crear una entrega, busca **Museo del Oro**, **Palacio Liévano** o **Parque Simón Bolívar**. También puedes escribir parte de la dirección que aparece en el catálogo. Selecciona y confirma un punto para el origen y otro para el destino.

Al registrar recogida o entrega aparece el punto previsto. Si pasó allí, lo confirmas; si cambió, pulsa **Cambiar ubicación** o **Ajustar en el mapa**. Cambiar la búsqueda descarta la selección anterior para no guardar un punto que ya no coincide con lo que estás viendo.

## Por qué un catálogo pequeño

Se incorporó un catálogo local para permitir un recorrido inmediato sin introducir coordenadas a mano ni configurar otro servicio de búsqueda. El catálogo viene con el frontend y no necesita una clave adicional. Si no encuentra lo que escribes, lo dice; no inventa coordenadas.

Son tres lugares públicos de Bogotá con puntos aproximados para datos sintéticos. No representan entradas exactas de edificios ni ubicaciones certificadas de mensajeros. Los nombres y coordenadas vienen de Wikidata: [Museo del Oro](https://www.wikidata.org/wiki/Q1109031), [Palacio Liévano](https://www.wikidata.org/wiki/Q3360886) y [Parque Simón Bolívar](https://www.wikidata.org/wiki/Q3376660).

Estos datos de referencia son comunes a la demo. Las entregas que se crean con ellos sí quedan bajo los permisos de cada comercio.

## El mapa es opcional

**Ajustar en el mapa** carga Leaflet 1.9.4 cerca del lugar elegido, con zoom de barrio. No tienes que buscarte en un mapa del mundo. Puedes pulsar otro punto o arrastrar el marcador. Con teclado, mueve el mapa con las flechas y pulsa **Seleccionar centro del mapa**.

Después de mover el punto hay que confirmarlo otra vez. La app envía las coordenadas confirmadas y Laravel comprueba datos y permisos como en cualquier evento.

El fondo usa imágenes de OpenStreetMap y necesita Internet. Se muestra la atribución y se respeta su [política de uso](https://operations.osmfoundation.org/policies/tiles/): HTTPS, caché normal del navegador y sin descargas masivas ni modo offline. Solo se carga al abrir el mapa.

La búsqueda del catálogo no envía nombres o direcciones a un servicio externo. El proveedor del mapa sí recibe las peticiones de imágenes del área que estás mirando. Puedes cambiar el endpoint con `VITE_MAP_TILE_URL` antes de compilar, revisando las condiciones y atribución del proveedor elegido.

## Si el mapa no carga

Verás el aviso y podrás seguir usando el punto del catálogo. El mapa no es necesario para completar el recorrido. El servicio público no garantiza disponibilidad o uso ilimitado.

Esta demo no busca cualquier dirección del mundo, no calcula rutas por carretera y no hace seguimiento GPS. Las pruebas de navegador simulan o bloquean las imágenes para no generar tráfico automático a OpenStreetMap.
