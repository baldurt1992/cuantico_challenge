# ADR-001 — Escalabilidad y conservación del historial

**Estado:** propuesta. No está implementada ni probada con carga.

## El problema

El escenario futuro pasa de 5 a 50 comercios y llega a 10.000 eventos por segundo. La demo procesa los eventos de forma síncrona en Laravel y SQLite. Eso simplifica la demo, pero SQLite tiene un solo escritor a la vez y no se ha demostrado esa capacidad.

## Alternativas

1. Pasar a PostgreSQL y seguir procesando dentro de la petición. Cambia menos cosas, pero la respuesta sigue esperando reconstrucción y cálculo durante los picos.
2. Recibir en una cola duradera y procesar con consumidores coordinados por comercio y entrega, usando PostgreSQL. Permite absorber picos y trabajar entregas distintas en paralelo, aunque añade demora e infraestructura.
3. Montar una plataforma completa de event sourcing y CQRS con varios almacenes. Da más opciones, pero sería demasiada complejidad sin mediciones que la justifiquen.

## Decisión propuesta

La opción 2. La API autentica, autoriza y valida antes de publicar. Solo confirma aceptación asíncrona cuando el broker haya confirmado el guardado duradero. Aceptado no significa calculado: haría falta una forma de consultar estado y errores.

Particionar por comercio y entrega ayuda a coordinar quién escribe. En PostgreSQL se mantiene la comprobación de emisor e ID de evento. El consumidor guarda el evento, reconstruye, actualiza la proyección y añade la revisión en una transacción; confirma el mensaje después del commit. La cola no corrige el desorden del emisor ni elimina duplicados por sí sola.

Los conflictos de negocio se guardan para revisión. Los fallos técnicos tienen reintentos acotados, backoff, cola de fallos y reprocesamiento autorizado. Si hay que publicar notificaciones después del cambio, se utilizaría outbox transaccional para no perderlas entre base y broker.

Se conservarían eventos, huellas, ocurrencia y recepción, tarifa y valores usados, reglas, coordenadas, componentes del costo y motivo de cada revisión. Los permisos por comercio también aplican a reportes, historial y reprocesamiento.

## Consecuencias y validación

Se acepta un intervalo entre recibir y consultar, y que haya más operación: colas pendientes, retrasos, bloqueos y errores por vigilar. Antes de elegir broker, particiones o índices hay que medir comercios de tamaños distintos, entregas con mucha actividad, duplicados y recuperación tras caídas. Los 10.000 eventos/s son una meta; añadir una cola no demuestra que se alcance.
