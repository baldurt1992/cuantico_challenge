# Contrato HTTP

[Índice de documentación](README.md) · [README del proyecto](../README.md)

## Antes de llamar a la API

Los endpoints `/api/v1/` usan la sesión y el CSRF de Laravel. Primero hay que iniciar sesión. Esta demo no tiene tokens de integración aparte, y conocer el ID de un comercio o una entrega no da permiso para consultarlos.

| Método y ruta                                            | Función                                                   |
| -------------------------------------------------------- | --------------------------------------------------------- |
| `POST /api/v1/events`                                    | Recibir un evento; requiere operador y comercio asignado. |
| `GET /api/v1/merchants/{merchant}/operations`            | Listado, costos, incidencias y estados por mensajero.     |
| `GET /api/v1/merchants/{merchant}/deliveries/{delivery}` | Eventos, cálculo y revisiones de una entrega.             |
| `POST /api/v1/merchants/{merchant}/chat`                 | Pregunta en el campo `message`, máximo 2.000 caracteres.  |

## Enviar un evento

Este es un ejemplo de creación. Usa un comercio al que tengas acceso; para asignar mensajero, también debe estar vinculado a ese comercio:

```json
{
    "event_id": "6b8e0180-9712-4b27-9b56-c7ebc7317c1d",
    "delivery_id": "039f0e31-b69c-4b53-95d5-0d4b9d70d492",
    "merchant_id": 1,
    "type": "created",
    "occurred_at": "2026-10-29T20:00:00-05:00",
    "data": {
        "planned_pickup": {
            "label": "Museo del Oro · Carrera 6 # 15-88, Bogotá",
            "latitude": 4.601919,
            "longitude": -74.072
        },
        "planned_destination": {
            "label": "Palacio Liévano · Carrera 8 # 10-65, Bogotá",
            "latitude": 4.598414,
            "longitude": -74.076539
        }
    }
}
```

`event_id` y `delivery_id` son UUID y deben conservarse al reintentar. Envía `occurred_at` con `Z` o un offset como `-05:00`; se almacena en UTC. El servidor pone `received_at` y obtiene el emisor de la sesión. Si mandas campos desconocidos, la petición se rechaza.

| Tipo                     | Contenido de `data`                                                                                         |
| ------------------------ | ----------------------------------------------------------------------------------------------------------- |
| `created`                | `planned_pickup` y `planned_destination`: objetos con `label` (1–255 caracteres), `latitude` y `longitude`. |
| `assigned`               | `courier_id`, asignado al comercio.                                                                         |
| `picked_up`, `delivered` | `latitude` entre -90 y 90; `longitude` entre -180 y 180.                                                    |
| `in_transit`, `failed`   | Objeto vacío.                                                                                               |

La primera recepción válida devuelve `201`. Si reenvías con el mismo usuario, ID y contenido normalizado, devuelve `200` y `duplicate=true`, sin volver a calcular. Si cambias el contenido usando ese mismo ID, devuelve `409`. `201` confirma que se guardó el evento; la entrega puede seguir pendiente o quedar en conflicto.

## Consultar operaciones

Para operaciones, usa `period=last_week` o `start_date` y `end_date` de calendario, con fin exclusivo. Se admiten `text`, `page`, `per_page` y `courier_id`; `courier_id` cambia el reporte de estados por mensajero. El listado de entregas mantiene su propia búsqueda. La búsqueda está paginada por 20 registros, hasta 50 por página. Un texto no vacío debe tener entre 2 y 100 caracteres. El máximo de reporte es 366 días.

## Respuestas y errores

La API devuelve JSON con dinero en unidades menores enteras; la interfaz lo muestra en COP. Los errores distinguen autenticación (`401` para solicitudes JSON), rol insuficiente (`403`), recurso no accesible (`404`), CSRF inválido (`419`), validación (`422`), límites (`429`), proveedor no disponible (`503`) y timeout (`504`).
