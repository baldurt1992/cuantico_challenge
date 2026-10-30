# Operaciones de entrega

Esta app recibe los eventos de una entrega, reconstruye lo que pasó y calcula su costo. Puedes revisar todo desde el panel o preguntarle al asistente, que solo consulta. Viene con datos de prueba, usuarios con distintos accesos e historial para explicar los resultados.

La aplicación utiliza Laravel 13, Vue 3, TypeScript, Inertia, Nuxt UI y SQLite. Para la prueba se confirmó libertad de stack. Nuxt UI funciona aquí con Vue y Vite; no hace falta ejecutar un servidor Nuxt.

## Arranque local

Desde la raíz del proyecto, ejecuta:

```bash
./scripts/start.sh
```

Antes de arrancar necesitas:

- Bash en Linux, macOS o Windows mediante WSL.
- PHP 8.3 o superior y Composer 2.
- Extensiones PHP `pdo_sqlite`, `mbstring`, `openssl`, `dom`, `xml`, `ctype`, `fileinfo`, `curl`, `tokenizer` y `session`.
- Node.js 22.12 o superior y npm.
- Acceso a Internet para descargar dependencias; también para usar Gemini.

El script instala las dependencias de los lockfiles, prepara `.env` y `APP_KEY`, crea SQLite si falta, aplica migraciones, carga los ejemplos y compila la interfaz. Si ya tienes claves, contraseñas o datos, los conserva. Para esta demo no necesitas MySQL, Redis, Docker, workers ni dejar Vite abierto.

Si falta la clave de Gemini, te la pide sin mostrarla en la terminal. También puedes pulsar Enter y seguir sin las consultas de IA: entregas, costos, reportes, historial y `/help` funcionan igual. Si falta un requisito del sistema, el script te avisa; no lo instala por su cuenta.

Abre [localhost:8000](http://localhost:8000) o [127.0.0.1:8000](http://127.0.0.1:8000). Mantén abierta la terminal; `Ctrl+C` detiene el servidor. Escucha en `0.0.0.0` para permitir acceso desde Windows/WSL. Opciones:

```bash
HOST=127.0.0.1 ./scripts/start.sh
PORT=8001 ./scripts/start.sh
PREPARE_ONLY=1 NON_INTERACTIVE=1 ./scripts/start.sh
```

La última opción solo prepara el entorno: no pide clave ni deja un servidor abierto. Este script es para local; rechaza otro `APP_ENV` o una conexión distinta de SQLite. El primer arranque depende del equipo y las descargas.

## Acceso y recorrido de evaluación

| Rol                     | Correo                   | Contraseña local    | Alcance inicial                                        |
| ----------------------- | ------------------------ | ------------------- | ------------------------------------------------------ |
| Lector                  | `lector@cuantico.test`   | `cuantico-demo`     | Tienda La 80 y Casa Botánica; consulta y chat.         |
| Operador                | `operador@cuantico.test` | `cuantico-operator` | Los tres comercios; consulta, chat e ingesta.          |
| Lector de otro comercio | `cliente@cuantico.test`  | `cuantico-demo`     | Solo Panadería del Sur; permite comprobar aislamiento. |

Estos accesos son para la demo local. Si quieres otras contraseñas, configura `DEMO_READER_PASSWORD` y `DEMO_OPERATOR_PASSWORD` antes de cargar los datos por primera vez. Cambiar esas variables después no cambia las cuentas existentes. En una base nueva de producción el seeder exige contraseñas configuradas; no toma las de esta tabla. Si la base ya existe, conserva sus cuentas.

1. Ingresa como lector, selecciona un comercio y revisa los reportes de la semana pasada. Las fechas visibles incluyen ambos días.
2. Selecciona un mensajero en **Estados por mensajero**. Ese reporte cuenta entregas creadas en el período; los costos cuentan entregas finalizadas.
3. Busca por origen, destino, comercio o mensajero. La búsqueda parcial ignora tildes, mayúsculas y espacios repetidos; no corrige errores ortográficos. El listado abarca todos los períodos y está limitado al comercio activo.
4. Abre una entrega para revisar eventos, cálculo y revisiones anteriores. Los costos pendientes y los conflictos se distinguen de un costo confirmado de cero.
5. Abre el botón del asistente y pregunta, por ejemplo, «¿Cuánto costaron las entregas la semana pasada?», «Busca entregas de Calle 80» o «¿Qué estados tienen las entregas de Lucía Moreno la semana pasada?».
6. Con el operador, pulsa **Crear entrega** y selecciona **Origen previsto** y **Destino previsto** con el buscador local. Confirma ambos puntos. La dirección del listado se obtiene del destino; los identificadores se generan internamente y el costo queda pendiente hasta finalizar el recorrido.
7. En la fila de esa entrega, usa **Asignar mensajero → Registrar recogida → Marcar en ruta → Registrar entrega**. Recogida y entrega muestran automáticamente el punto previsto correspondiente: confirma que ocurrió allí o usa **Cambiar ubicación** / **Ajustar en el mapa** para registrar el punto real. No necesitas volver a buscar los mismos lugares ni escribir coordenadas. Cada acción crea un evento y el resultado final muestra el desglose del costo.
8. Para una entrega fallida, usa **Más acciones → Registrar falla** antes de entregar. Se cobra el 40 % de la tarifa base, sin recargos. Las entregas finalizadas no ofrecen acciones de avance; las incompletas permiten registrar eventos faltantes y los conflictos requieren revisar su historial.
9. Si una petición falla sin confirmar el resultado, **Reintentar la misma acción** conserva el identificador y el contenido del envío para evitar duplicados. No necesitas copiar ni editar IDs.

La primera carga deja tres comercios, tres mensajeros y dieciocho entregas: hay completadas, fallidas, incompletas y con contradicciones. Los eventos entran en orden inverso para probar la reconstrucción. Tienda La 80 conserva la nota maliciosa del enunciado como dato de prueba.

Los ejemplos usan la semana anterior al momento de crear la base. Se cargan una sola vez; arrancar de nuevo no mueve sus fechas. Si vuelves varias semanas después, busca el período original para ver esos costos.

## Configuración de Gemini

Obtén una clave de tu proyecto siguiendo la [documentación de Google AI Studio](https://ai.google.dev/gemini-api/docs/api-key). Puedes introducirla en el arranque o editar `.env`:

```dotenv
GOOGLE_API_KEY=tu_clave
GEMINI_MODEL=gemini-3.1-flash-lite
```

La clave solo la usa Laravel. No le pongas prefijo `VITE_`, no la metas en JavaScript y no la subas a Git. `.env.example` tiene los nombres de las variables sin secretos. Modelo, cuota y cobros dependen de tu proyecto de Google; el repo no trae una clave compartida ni garantiza que tu cuenta tenga uso gratuito.

Si cambias la configuración, reinicia el servidor. Si tenías configuración en caché, ejecuta también `php artisan config:clear`.

## Pruebas

Con el entorno preparado puedes ejecutar:

```bash
php artisan test --compact
npm run test
npm run typecheck
npm run build
```

Para las pruebas de navegador, instala Chromium una vez:

```bash
npx playwright install chromium
npm run test:e2e
```

Si en Linux faltan bibliotecas de Chromium, usa `npx playwright install-deps chromium`. Ese paso puede pedir permisos de administrador. Chromium se necesita para las pruebas de navegador; para usar la app no hace falta.

- **Pest:** reconstrucción, idempotencia, normalización, tarifas históricas, dinero, distancia, límites nocturnos, fallidas, conflictos, aislamiento, búsqueda y agente.
- **Vitest:** descarte de respuestas de un comercio anterior, recuperación ante errores y presentación de límites de fechas.
- **Playwright:** acceso, períodos, reportes por mensajero, detalle, revisiones históricas, sugerencias del chat, carga, resultados legibles, copia, reintentos, modales, campos identificados y vista móvil.

PHP prueba con una base en memoria. Playwright usa su propia base, `storage/framework/e2e.sqlite`, que reemplaza al preparar los escenarios, y levanta un servidor en el puerto `8765`; déjalo libre. Las suites simulan Gemini para no gastar cuota ni depender de la red. Capturas y trazas quedan en `test-results/`, fuera de Git.

En las últimas ejecuciones pasaron 96 pruebas PHP con 437 aserciones, 11 de frontend y 13 recorridos de navegador. También se realizaron consultas reales a Gemini y revisiones de accesibilidad en pantallas concretas.

## Documentación

La documentación de entrega se organiza en el [índice de docs](docs/README.md):

- [Notas sobre uso de IA y verificación](docs/NOTAS.md).
- [ADR de escalabilidad](docs/ADR-001-ESCALABILIDAD.md).
- [Supuestos y decisiones](docs/DECISIONES-Y-CORRECCIONES.md).

El índice también enlaza la referencia de arquitectura, API, reglas de negocio, agente y ubicaciones.

## Desarrollo y problemas habituales

Si vas a editar el frontend y quieres ver los cambios al guardar, prepara primero el proyecto, detén el servidor anterior y abre estas dos terminales:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

```bash
npm run dev
```

| Situación                        | Revisión                                                                                                                      |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| No abre el puerto 8000           | Verifica que Laravel siga ejecutándose. Vite en 5173 no reemplaza al servidor PHP. Usa `PORT=8001` si el puerto está ocupado. |
| No se ve una edición de frontend | El arranque normal sirve assets compilados. Vuelve a compilar y recarga, o usa el modo de desarrollo anterior.                |
| Consultas con IA deshabilitadas  | Configura la clave en `.env` y reinicia el servidor.                                                                          |
| Respuesta de cuota o proveedor   | Respeta la espera indicada o reintenta más tarde; los reportes siguen disponibles. No equivale a una consulta sin resultados. |
| Semana pasada sin costos         | Comprueba las fechas de las entregas conservadas y si están pendientes o en conflicto. Prueba un rango explícito.             |
| Base SQLite sin escritura        | Comprueba permisos del archivo y de su directorio, además de `storage/` y `bootstrap/cache/`.                                 |
