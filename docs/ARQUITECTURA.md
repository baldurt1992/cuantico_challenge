# Arquitectura y alcance

[Índice de documentación](README.md) · [README del proyecto](../README.md)

Se eligió una sola aplicación para simplificar el arranque y la revisión. Laravel maneja sesión, permisos, eventos, consultas y agente. Vue muestra las pantallas, Inertia los conecta y Vite compila los archivos del frontend.

Se utiliza SQLite para no pedir MySQL, Redis o Docker en una demo pequeña. Las escrituras van en transacciones `IMMEDIATE`, con cinco segundos de espera por bloqueo y restricciones de unicidad. SQLite sigue teniendo un solo escritor a la vez; esta elección simplifica el arranque, pero tiene ese límite.

## Dónde está cada cosa

| Qué hace                                          | Dónde está                                      |
| ------------------------------------------------- | ----------------------------------------------- |
| Valida el evento y comprueba el permiso           | `StoreDeliveryEventRequest` y `MerchantPolicy`. |
| Normaliza el contenido y obtiene su huella        | `EventNormalizer`.                              |
| Guarda el evento y coordina la transacción        | `EventIngestor`.                                |
| Reconstruye la entrega por fecha de ocurrencia    | `DeliveryProjector`.                            |
| Calcula distancia, componentes y dinero           | `CostCalculator`.                               |
| Resuelve períodos y consulta datos autorizados    | `ReportPeriod` y `DeliveryQueries`.             |
| Reserva la cuota compartida del agente            | `AgentBudget`.                                  |
| Pide una propuesta a Gemini y valida su ejecución | `GeminiAgent`.                                  |

## Procesamiento de un evento

Primero se comprueban usuario, permiso y formato. Después se normaliza el contenido y se comprueba si el evento ya existe. Si es nuevo, se guarda, se reconstruye la entrega y se busca la tarifa histórica. Cuando están los datos necesarios, se calcula el costo y se guardan la proyección y su revisión antes de confirmar la transacción.

El costo sale del historial completo; no se suman cargos con cada petición. Por eso recibir otra vez el mismo evento no vuelve a cobrar.

Cada revisión guarda el estado, los problemas encontrados, ubicaciones, fechas, costo, desglose y eventos usados. El cálculo conserva la tarifa, sus valores, la zona y la versión de reglas. Así se puede explicar por qué cambió un resultado sin pedirle a Gemini que lo reconstruya.

## Hasta dónde llega esta versión

La demo está pensada para una instancia y datos de prueba. No incluye rutas por carretera, seguimiento GPS, liquidación fiscal, varias monedas, SSO, administración de usuarios o tarifas, edición de eventos ni resolución de conflictos. Tampoco tiene colas, WebSockets o alta disponibilidad.

Se guardan versiones de tarifa, pero no hay una política administrativa que impida modificarlas directamente en la base. El historial explica los cambios que procesa la app; no es almacenamiento protegido frente a un administrador de la base.

La corrección de eventos y la administración de tarifas requieren reglas adicionales del negocio.

La evolución hacia PostgreSQL y colas está en el [ADR de escalabilidad](ADR-001-ESCALABILIDAD.md). Los motivos y consecuencias de los supuestos adoptados están en [Supuestos y decisiones](DECISIONES-Y-CORRECCIONES.md).
