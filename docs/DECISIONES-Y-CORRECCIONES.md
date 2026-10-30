# Supuestos y decisiones del reto

[Índice de documentación](README.md) · [README del proyecto](../README.md)

Se registran los supuestos adoptados donde el enunciado admitía distintas interpretaciones, siguiendo la indicación de documentarlos y continuar. Cada punto explica la decisión, su motivo y su consecuencia en esta implementación.

## Eventos e historial

### Identidad y reintentos

Se definió la identidad del evento como `(usuario emisor, event_id)`, con UUID estables para evento y entrega. El emisor se obtiene de la sesión. Se compara el contenido normalizado: un reenvío equivalente no inserta ni recalcula; el mismo ID con otro contenido devuelve `409`. Esto distingue un reintento de un hecho nuevo y evita duplicar efectos ante una respuesta perdida. Quien reintenta debe conservar usuario, ID y contenido.

### Orden y transiciones

Se reconstruye por fecha de ocurrencia; los empates siguen el ciclo `created → assigned → picked_up → in_transit → delivered`. Se exige esa cadena para confirmar una completada. Una falla puede cerrar desde un estado no terminal con el prefijo consistente correspondiente, incluido `created → failed`. No se admiten reasignaciones. Así el mismo conjunto de eventos produce el mismo resultado sin depender del orden de recepción.

### Faltantes y contradicciones

Un evento adelantado se guarda y queda pendiente hasta recibir sus antecedentes. Tipos repetidos con IDs distintos, secuencias incompatibles, hechos posteriores al terminal o terminales opuestos producen conflicto; el desempate no vuelve compatibles una entrega y una falla. En ese caso se retira el costo confirmado actual, conservando el cálculo anterior en las revisiones. Se evita elegir un cobro arbitrario o descartar hechos que pueden llegar tarde.

### Corrección de hechos aceptados

Los eventos no se editan, borran o sustituyen desde la app. Un antecedente tardío puede completar una secuencia, pero un hecho incorrecto aceptado no se reemplaza reutilizando su ID. Una corrección administrativa requeriría permiso, motivo y referencia al original; queda fuera de esta versión para no alterar cobros sin una regla definida. Las revisiones conservan la evidencia procesada por la aplicación.

## Cálculo

### Tarifa histórica

Se usa la tarifa vigente al ocurrir `created`, conservando su referencia y los valores aplicados. Se eligió fijar el precio al crear para que una recepción tardía o un cambio posterior de tarifa no modifique la elección. Si falta creación o tarifa aplicable, el costo queda pendiente; no se sustituye por el precio actual. La administración de tarifas queda fuera de la demo.

### Distancia y dinero

Se usa Haversine entre recogida y entrega reales, con radio de 6.371.000 m y redondeo único al metro. El costo prorratea el kilómetro; la distancia mostrada en pantalla no se usa para recalcularlo. Se eligió esta aproximación porque el escenario aporta dos puntos, sin definir rutas por carretera ni exigir otro proveedor.

La moneda es COP con dos decimales internos: 5.000,00 COP son `500000` unidades menores enteras. Se redondean mitades hacia arriba por componente, en orden distancia, subtotal y nocturnidad. Los reportes suman los costos finales sin reaplicar porcentajes. Esto mantiene iguales el desglose, el panel y el chat.

### Base y límites del recargo nocturno

El 15 % se aplica a base más distancia, frente a la alternativa de aplicarlo solo a la base. Se evalúa la hora de entrega en la zona histórica de la tarifa, desde las 20:00 inclusive hasta las 06:00 exclusive. Quedan definidos tanto el subtotal afectado como los instantes límite; la hora del servidor y la recepción no deciden el recargo.

### Fallida y costo pendiente

Una fallida consistente cuesta solo el 40 % de la base, incluso si ya estaba en ruta, sin distancia ni nocturnidad. Antes de un terminal consistente, o si faltan datos o tarifa, el costo es `null`. Se distingue de cero y no se presenta como estimación confirmada, para no interpretar un importe desconocido como entrega gratuita.

## Consultas y fechas

### Criterio temporal de los reportes

Los costos se filtran por finalización. Los estados por mensajero muestran el estado actual de las entregas creadas en el período, no cómo estaban en una fecha pasada. Se separaron ambos criterios porque una consulta es monetaria y la otra incluye operación en curso. Pendientes y conflictos se muestran aparte como situación actual del comercio.

Semana pasada es la semana calendario anterior, de lunes a lunes en la zona del comercio. La API usa inicio incluido y fin excluido; la pantalla muestra el último día incluido. Un evento tardío puede actualizar un período anterior: estos reportes no representan cierres contables.

### Alcance de las consultas

Cada consulta usa un comercio seleccionado y autorizado, también para mensajeros compartidos. No se genera un total global implícito. Si faltan período o mensajero, o hay nombres ambiguos, se solicita precisión en lugar de elegir arbitrariamente. El agente resuelve una consulta por mensaje para mantener una respuesta comprobable con los mismos servicios del panel.

### Ocurrencia y recepción

Se permite al operador registrar la fecha real de un hecho anterior en la zona del comercio; se convierte a UTC para enviarla y almacenarla. Se adoptó este control para ingresar eventos históricos sin confundir ocurrencia y llegada. La recepción y el emisor los fija el servidor; los eventos guardados no se editan. La fecha elegida afecta orden, tarifa y nocturnidad.

## Recorrido y actualización

### Ubicaciones previstas y reales

La creación exige origen y destino previstos, y la dirección del listado se deriva del destino. Recogida y entrega confirman o ajustan el lugar real; solo esas coordenadas entran al cálculo. Así se puede preparar el recorrido sin dar por ocurrido un hecho ni confirmar un costo al crear.

Se utiliza un catálogo local de lugares públicos de Bogotá y un mapa opcional centrado en el punto elegido. Se evita pedir coordenadas manuales o depender de geocodificación para completar la prueba. La búsqueda desconocida no inventa puntos y el catálogo permite continuar si falla el mapa.

### Casi tiempo real

La ingesta guarda y reconstruye en una transacción antes de responder. La pantalla refresca tras acciones y cada cinco segundos mientras está visible, sin superponer consultas ni repetir llamadas a Gemini. Se eligió este mecanismo para reflejar cambios externos sin añadir WebSockets o procesos permanentes. La demora también depende de red y servidor; el chat conserva cada respuesta con su fecha.

Los contratos, controles de seguridad y detalles de implementación se consultan en [API](API.md), [Reglas de negocio](REGLAS-DE-NEGOCIO.md), [Agente y seguridad](AGENTE-Y-SEGURIDAD.md) y [Ubicaciones](UBICACIONES.md). Las correcciones vinculadas al uso de IA están en [NOTAS.md](NOTAS.md), y la evolución de capacidad está en el [ADR](ADR-001-ESCALABILIDAD.md).
