# Reglas de negocio y recorrido

[Índice de documentación](README.md) · [README del proyecto](../README.md)

Este documento describe las reglas aplicadas en la demo. Los supuestos adoptados, sus motivos y sus límites se explican en [Supuestos y decisiones](DECISIONES-Y-CORRECCIONES.md).

## Estados, eventos y costos

| Tema                  | Regla aplicada                                                                                                                                                      |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Recorrido normal      | `created → assigned → picked_up → in_transit → delivered`. Se puede registrar `failed` desde un estado no terminal. No hay reasignaciones.                          |
| Eventos desordenados  | Se usa la fecha de ocurrencia. Si hay empate, se aplica el orden del recorrido. Que uno llegue tarde no lo vuelve incorrecto.                                       |
| Eventos faltantes     | Se guarda lo recibido, se indica qué falta y se deja el costo pendiente. Un terminal por sí solo no completa la historia.                                           |
| Contradicciones       | Dos terminales, tipos repetidos con IDs distintos o una secuencia incompatible quedan en conflicto. Se conservan los hechos y se retira el costo confirmado actual. |
| Tarifa                | Se usa la vigente cuando ocurrió la creación. Si falta, no se reemplaza por la actual. La demo no tiene una pantalla para administrar tarifas.                      |
| Distancia             | Haversine entre recogida y entrega reales, con radio de 6.371.000 m y redondeo al metro. Es distancia aproximada entre puntos, no por carretera.                    |
| Dinero                | COP con dos decimales internos y enteros: 5.000,00 COP son `500000`. Se redondean mitades hacia arriba al cerrar cada componente.                                   |
| Entregada             | Distancia = redondear `metros × tarifa_por_km / 1000`. Subtotal = base + distancia. Si es de noche, se suma el 15 % de ese subtotal.                                |
| Noche                 | Desde las 20:00 inclusive hasta las 06:00 exclusive, mirando la finalización en la zona histórica de la tarifa.                                                     |
| Fallida               | Solo el 40 % de la base, sin distancia ni nocturnidad. Necesita historial consistente y tarifa aplicable.                                                           |
| Reporte de costos     | Suma las entregas finalizadas en el período con costo confirmado. Pendientes y conflictos quedan fuera de ese total.                                                |
| Estados por mensajero | Muestra cómo están ahora sus entregas creadas en el período, dentro del comercio seleccionado.                                                                      |
| Semana pasada         | La semana calendario anterior en la zona del comercio: de lunes incluido a lunes excluido. La pantalla muestra el domingo como último día incluido.                 |
| Correcciones          | Se conservan eventos y revisiones. No se editan o borran desde la app; un conflicto no se arregla reutilizando un ID.                                               |
| Actualización         | Se guarda y reconstruye antes de responder. La pantalla consulta cada cinco segundos mientras está visible, más lo que tarde la red y el servidor.                  |

Por ejemplo: base de 5.000,00 COP, tarifa de 1.000,00 COP/km y 1.234 metros. La distancia cuesta 1.234,00 COP y el subtotal es 6.234,00 COP. De noche se suman 935,10 COP, para un total de 7.169,10 COP. Si falla, cuesta 2.000,00 COP.

## Del plan a lo que ocurrió

Al crear eliges y confirmas origen y destino previstos. El servidor obtiene `delivery_address` del nombre del destino y guarda los dos puntos en la entrega y sus revisiones. Por eso la tabla distingue **Origen previsto** y **Destino previsto**.

Crear una entrega todavía no significa que alguien la recogió o la entregó. Esas acciones registran las coordenadas reales. Puedes confirmar el punto previsto o cambiarlo si el hecho ocurrió en otro lugar. El costo usa las coordenadas reales; el plan queda como referencia.

Por eso el costo de una entrega recién creada aparece pendiente. Si un evento avanzado llega antes que la creación, también queda pendiente el recorrido previsto hasta recibirla.

## Fechas sin escribir timestamps a mano

Los filtros usan `UInputDate` con calendario. Para eventos se incluyen hora y segundos. El operador trabaja en la hora local del comercio y la interfaz la convierte a UTC para enviar. El backend comprueba que venga con zona. La fecha de recepción siempre la fija el servidor.

Se permite que un operador registre la fecha real de un hecho anterior. El reto pide distinguir ocurrencia y llegada, pero no define ese control de la interfaz; se adoptó esta regla para poder ingresar eventos históricos. Esa fecha afecta el orden, la tarifa y el recargo nocturno. Una vez guardado, el evento no se edita desde la app.

## Cómo recorrerlo en pantalla

Las acciones de cada fila guían creación, asignación, recogida, ruta y cierre. La API sigue aceptando eventos desordenados y reintentos: la guía visual no cambia ese contrato.

Una entrega finalizada consistente no ofrece más acciones de avance. Una incompleta permite registrar lo que falta con su fecha real. Una contradictoria se deja para revisar en el historial; no se aplica una resolución automática ni se inventan datos para completar el costo.

El listado muestra entregas de todos los períodos. Los reportes sí conservan las fechas seleccionadas: si completas una entrega hoy, elige un rango que incluya hoy para verla en el costo del período.

Los selectores abren sus opciones al enfocar y permiten limpiar con `clear`. Borrar una ubicación descarta la confirmación; borrar un comercio también retira sus datos y el contexto del chat.

## Qué se refresca

El panel se actualiza después de registrar un evento, al recuperar visibilidad y de forma periódica. El chat conserva cada respuesta con su fecha; no repite consultas a Gemini en segundo plano.

Si Gemini tarda demasiado, **Reintentar** manda otra vez la pregunta sin duplicarla en la conversación y pasa por los límites habituales. Cambiar de comercio descarta ese reintento.
