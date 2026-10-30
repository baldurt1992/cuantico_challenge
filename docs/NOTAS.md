# Notas sobre uso de IA y verificación

[Índice de documentación](README.md) · [README del proyecto](../README.md)

## Herramientas utilizadas

El desarrollo contó con asistencia de Codex para analizar requisitos, implementar código, preparar pruebas y revisar documentación. Las decisiones técnicas y sus consecuencias se documentan en [Supuestos y decisiones](DECISIONES-Y-CORRECCIONES.md).

| Herramienta                  | Uso                                                                                             |
| ---------------------------- | ----------------------------------------------------------------------------------------------- |
| Codex                        | Apoyo en implementación, pruebas y revisión técnica.                                            |
| CodeGraph                    | Consulta de símbolos, responsabilidades y relaciones del código.                                |
| MCP de Nuxt UI               | Comprobación de props, slots y comportamiento de componentes.                                   |
| Chrome DevTools y Playwright | Revisión de interfaz, foco, estados de carga y recorridos de navegador.                         |
| Gemini 3.1 Flash-Lite        | Proveedor del agente de la aplicación: interpretación de preguntas y propuesta de herramientas. |

El uso de Gemini dentro del producto es independiente de la asistencia para desarrollar el código. El modelo no calcula costos ni concede permisos; esas operaciones corresponden a Laravel.

## Correcciones realizadas

La revisión del código y las pruebas detectaron problemas que requirieron corrección:

- Se corrigió la configuración de componentes de chat y modales, el contraste de campos y los identificadores necesarios para los formularios.
- Se sustituyó la presentación de JSON crudo por resultados y revisiones con etiquetas, estados y desgloses legibles.
- Se reemplazó el token CSRF estático por la cookie actualizada de Laravel y se comprobó un reintento tras perder la respuesta de un evento recibido.
- Se corrigieron entradas duplicadas de configuración SQLite y referencias a APIs no disponibles directamente en PHP 8.3.
- Se ajustaron pruebas que asumían un orden de recepción incompatible con los eventos desordenados del escenario.
- Se normalizó la búsqueda para ignorar tildes, mayúsculas y espacios repetidos, manteniendo el alcance por comercio.

Los supuestos aplicados a los requisitos ambiguos se explican en [Supuestos y decisiones](DECISIONES-Y-CORRECCIONES.md).

## Verificación realizada

Las últimas ejecuciones registradas finalizaron con 96 pruebas PHP y 437 aserciones, 11 pruebas de frontend y 13 recorridos de navegador. También se comprobaron tipos y compilación, se revisaron estados concretos de accesibilidad y se realizaron consultas manuales con Gemini real.

Las suites automatizadas simulan las respuestas del proveedor para evitar consumo de cuota y dependencia de su disponibilidad. Las consultas reales se realizaron por separado; no sustituyen las pruebas de permisos y validación.
