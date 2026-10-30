# Agente, permisos y seguridad

[Índice de documentación](README.md) · [README del proyecto](../README.md)

El agente sirve para consultar las entregas del comercio seleccionado. Gemini interpreta lo que preguntas; Laravel valida la propuesta y busca los datos. Permisos, cifras y cálculo quedan en el servidor.

Este documento describe las herramientas, los controles de seguridad y los límites del agente. Los criterios adoptados para las consultas ambiguas se explican en [Supuestos y decisiones](DECISIONES-Y-CORRECCIONES.md).

## Las tres consultas

Solo puede proponer `get_merchant_totals`, `get_courier_status_counts` o `search_deliveries`. El comercio lo fija Laravel a partir del acceso del usuario; el modelo no recibe un argumento para cambiarlo.

Se comprueban herramienta y argumentos y se acepta como máximo una llamada por respuesta. No se ejecutan texto libre, SQL ni propuestas inválidas. Aunque entre un operador, el agente no registra eventos ni cambia estados.

Cada pregunta de negocio hace una petición a Gemini. Después se presenta el resultado de la consulta sin otra llamada para reescribir cifras. El historial enviado se limita a seis mensajes anteriores del mismo comercio. Al cambiar de comercio, se limpia la conversación y se descartan respuestas antiguas que todavía estén en camino.

## Malas prácticas detectadas y soluciones

Las sugerencias inseguras del enunciado se resolvieron de la siguiente manera:

| Práctica detectada                                           | Riesgo                                                                          | Solución aplicada y motivo                                                                                                                                                               |
| ------------------------------------------------------------ | ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Concatenar texto libre en SQL.                               | Inyección SQL y consultas fuera del alcance autorizado.                         | Parámetros preparados, filtros `OR` agrupados dentro del comercio y búsqueda literal con validación y paginación. El texto se utiliza como dato, no como consulta ejecutable.            |
| Incorporar notas del comercio como instrucciones del modelo. | Inyección indirecta capaz de alterar la intención de la consulta.               | Las notas se acotan y se tratan como datos externos. Se conserva el ejemplo malicioso de Tienda La 80; Laravel valida herramientas, argumentos y permisos independientemente del prompt. |
| Permitir consultas globales sin controles por comercio.      | Exposición de información ajena mediante nombres, IDs o mensajeros compartidos. | Sesión, roles y pertenencia al comercio comprobados en cada endpoint y herramienta. El servidor fija el comercio; conocer un ID no concede acceso.                                       |
| Incluir claves en el código.                                 | Filtración de credenciales y consumo de cuota por terceros.                     | `GOOGLE_API_KEY` solo en el backend, sin secretos en Git, JavaScript, variables `VITE_`, respuestas o logs. Cada entorno configura su clave.                                             |

También se evitaron estos riesgos en el diseño del agente:

- **Confiar cifras y ejecución al modelo:** Laravel calcula y presenta los resultados; Gemini solo propone una de las tres consultas permitidas. No se ejecutan SQL, código ni herramientas de escritura, y no hay una segunda llamada para reescribir importes.
- **Depender exclusivamente del prompt:** la autorización y la validación se repiten al ejecutar cada consulta. No se asume que el modelo ignore siempre una instrucción maliciosa.
- **Reintentar sin límite o limitar solo el botón:** el backend reserva presupuesto compartido, cuenta intentos fallidos y aplica límites de concurrencia, entrada, salida y tiempo. El reintento es manual.
- **Sobrescribir evidencia para resolver un conflicto:** se conservan eventos y revisiones. Un ID reutilizado con otro contenido devuelve `409`; una contradicción retira el costo actual confirmado y conserva el cálculo anterior en el historial.

## Ayuda y ejemplos que sí consultan

Escribe `/help`, pulsa **Comandos: /help** o pregunta qué puedes hacer con tu rol. La respuesta muestra tus permisos, las tres capacidades y ejemplos que puedes enviar con un clic. Esos ejemplos consultan dentro del chat; los enlaces al panel van aparte.

Puedes pedir costos por período, estados de las entregas de un mensajero o buscar entregas. El ejemplo de mensajero usa uno del comercio activo. La ayuda la resuelve Laravel con `MerchantPolicy`, sin llamar a Gemini ni gastar cuota. Funciona aunque no hayas configurado la clave.

| Comando           | A dónde lleva o qué muestra                     | Quién puede usarlo              |
| ----------------- | ----------------------------------------------- | ------------------------------- |
| `/help`, `/ayuda` | Permisos y capacidades.                         | Usuario con acceso al comercio. |
| `/costos`         | Reporte de costos del período.                  | Lector y operador.              |
| `/estados`        | Estados por mensajero.                          | Lector y operador.              |
| `/entregas`       | Buscador de entregas.                           | Lector y operador.              |
| `/historial`      | Listado para abrir detalle y revisiones.        | Lector y operador.              |
| `/evento`         | Crear entrega; luego se continúa desde su fila. | Operador autorizado.            |

Los comandos son accesos del panel, no herramientas nuevas para el modelo. Las preguntas sugeridas sí necesitan Gemini y cuota. Si una pregunta no se entiende o la propuesta no se puede ejecutar, se presenta una explicación controlada con las capacidades disponibles. No se anuncia entrenamiento activo: la app no entrena durante el uso.

Los enlaces salen de una lista fija del servidor, conservan el comercio y llevan el foco al destino. La ayuda explícita no desplaza las preguntas de negocio del historial enviado al modelo. Escribir una orden no cambia los permisos del usuario.

## Cuota y tiempos de espera

Estos son los valores de `config/agent.php`:

| Control            | Valor en `config/agent.php`                                             |
| ------------------ | ----------------------------------------------------------------------- |
| Usuario            | 5 solicitudes/minuto, 50/día; una en curso durante una ventana de 30 s. |
| Aplicación         | 10 solicitudes/minuto y 400/día, contadas en base compartida.           |
| Día de presupuesto | Calendario `America/Los_Angeles`, independiente de la zona del reporte. |
| HTTP               | 5 s de conexión y 25 s de tiempo total; sin reintentos automáticos.     |
| Contexto           | 16.000 caracteres, pregunta de 2.000 y notas de 1.000 como máximo.      |
| Salida             | 512 tokens como máximo.                                                 |

Se cuentan también los intentos fallidos porque pueden haber consumido cuota en Google. Estos controles no abarcan otras aplicaciones del mismo proyecto, y los límites de caracteres no garantizan un número exacto de tokens por minuto. Por eso todavía puede llegar un `429` del proveedor aunque quede presupuesto local.

Los logs guardan ID de petición, usuario, comercio, modelo, herramienta, resultado, duración y tokens reportados. No guardan la clave ni el texto de la conversación.

Un timeout se muestra como error con opción de reintento manual, nunca como una búsqueda vacía. El panel y las consultas normales siguen disponibles si el proveedor falla o se queda sin cuota.
