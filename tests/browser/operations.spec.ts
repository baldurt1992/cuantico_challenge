import { test, expect, type Page } from '@playwright/test'
import type { Delivery } from '../../resources/js/types'

async function expectIdentifiedFields(page: Page) {
  const unidentified = await page.locator('input, select, textarea').evaluateAll(elements =>
    elements.filter(el => !el.id && !el.getAttribute('name')).map(el => el.outerHTML),
  )
  expect(unidentified).toEqual([])
  const duplicateIds = await page.locator('[id]').evaluateAll(elements => {
    const ids = elements.map(el => el.id)
    return ids.filter((id, index) => ids.indexOf(id) !== index)
  })
  expect(duplicateIds).toEqual([])
}


async function setDate(page: Page, label: string, value: string) {
  const field = page.getByLabel(label, { exact: true })
  const [year, month, day] = value.split('-')
  for (const [segment, text] of [['year', year], ['month', month], ['day', day]]) {
    const part = field.locator(`[data-segment="${segment}"]`)
    await part.click()
    await part.pressSequentially(text)
  }
}

async function choosePlannedRoute(page: Page) {
  for (const [region, query, name] of [['Origen previsto', 'Museo', /Museo del Oro/], ['Destino previsto', 'Carrera 8', /Palacio Liévano/]] as const) {
    const picker = page.getByRole('region', { name: region, exact: true })
    await picker.getByRole('combobox', { name: 'Buscar ubicación de demostración', exact: true }).fill(query)
    await page.getByRole('option', { name }).click()
    await picker.getByRole('button', { name: 'Confirmar ubicación', exact: true }).click()
    await expect(picker.getByText('Ubicación confirmada', { exact: true })).toBeVisible()
  }
}

test.use({ colorScheme: 'dark' })

test('operator can inspect costs, use chat, ingest and retry an event', async ({ page }) => {
  await page.context().grantPermissions(['clipboard-read', 'clipboard-write'])
  const errors: string[] = []
  page.on('pageerror', error => errors.push(error.message))
  await page.goto('/login')
  await expectIdentifiedFields(page)
  await page.getByLabel('Correo', { exact: true }).fill('operador@cuantico.test')
  await expect(page.getByLabel('Correo', { exact: true })).toHaveCSS('color', 'rgb(23, 37, 33)')
  await page.getByLabel('Contraseña', { exact: true }).fill('cuantico-operator')
  await page.getByRole('button', { name: 'Ingresar a operaciones' }).click()
  await expect(page.getByRole('heading', { name: 'Entregas y costos' })).toBeVisible()
  await expect(page.getByText('Calle 80 # 12-20', { exact: true })).toBeVisible()
  await expectIdentifiedFields(page)
  const initialPeriod = (await (await page.request.get('/api/v1/merchants/1/operations?period=last_week')).json()).totals.period
  const inclusiveEnd = new Date(`${initialPeriod.end_date_exclusive}T00:00:00Z`)
  inclusiveEnd.setUTCDate(inclusiveEnd.getUTCDate() - 1)
  const lastWeek = page.getByRole('button', { name: 'Semana pasada', exact: true })
  await expect(lastWeek).toHaveAttribute('aria-pressed', 'true')
  await expect(page.locator('input[name="start_date"]')).toHaveValue(initialPeriod.start_date)
  await expect(page.locator('input[name="end_date"]')).toHaveValue(inclusiveEnd.toISOString().slice(0, 10))
  const repeatedPreset = page.waitForResponse(response => response.url().includes('/api/v1/merchants/1/operations?') && new URL(response.url()).searchParams.get('period') === 'last_week')
  await lastWeek.click()
  expect((await repeatedPreset).ok()).toBe(true)
  await setDate(page, 'Fecha inicial', '2026-01-01')
  const draftRefresh = page.waitForResponse(response => response.url().includes('/api/v1/merchants/1/operations?') && response.ok())
  await page.getByRole('button', { name: 'Actualizar', exact: true }).click()
  await draftRefresh
  await expect(page.locator('input[name="start_date"]')).toHaveValue('2026-01-01')
  await setDate(page, 'Fecha final', '2026-01-02')
  const customPeriod = page.waitForResponse(response => new URL(response.url()).searchParams.get('start_date') === '2026-01-01' && response.url().includes('/api/v1/merchants/1/operations?'))
  await page.getByRole('button', { name: 'Aplicar', exact: true }).click()
  expect((await customPeriod).ok()).toBe(true)
  await expect(lastWeek).toHaveAttribute('aria-pressed', 'false')
  const restoredPreset = page.waitForResponse(response => response.url().includes('/api/v1/merchants/1/operations?') && new URL(response.url()).searchParams.get('period') === 'last_week')
  await lastWeek.click()
  expect((await restoredPreset).ok()).toBe(true)
  await expect(lastWeek).toHaveAttribute('aria-pressed', 'true')
  await expect(page.locator('input[name="start_date"]')).toHaveValue(initialPeriod.start_date)
  await expect(page.locator('input[name="end_date"]')).toHaveValue(inclusiveEnd.toISOString().slice(0, 10))
  const headerTop = (await page.getByRole('banner').boundingBox())!.y
  await page.getByRole('main').evaluate(el => el.parentElement!.scrollTo(0, 10000))
  expect(await page.getByRole('main').evaluate(el => el.parentElement!.scrollTop)).toBeGreaterThan(0)
  expect((await page.getByRole('banner').boundingBox())!.y).toBe(headerTop)
  await page.getByRole('main').evaluate(el => el.parentElement!.scrollTo(0, 0))
  const courierSummary = page.getByRole('region', { name: 'Estados por mensajero' })
  await courierSummary.getByRole('combobox', { name: 'Mensajero', exact: true }).click()
  await page.getByRole('option', { name: 'Lucía Moreno' }).click()
  await expect(courierSummary.getByText(/Lucía Moreno · creadas desde/)).toBeVisible()
  await expect(courierSummary.getByText('Entregada', { exact: true })).toBeVisible()
  await page.getByLabel('Limpiar mensajero', { exact: true }).click()
  await expect(courierSummary.getByText('Elige un mensajero', { exact: false })).toBeVisible()
  await page.getByRole('button', { name: 'Ver detalle' }).first().click()
  const detailDialog = page.getByRole('dialog', { name: 'Detalle y trazabilidad' })
  await expect(detailDialog).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Detalle y trazabilidad' })).toBeVisible()
  await expect(detailDialog.getByText('Tarifa base', { exact: true })).toBeVisible()
  const latestRevisionToggle = detailDialog.getByRole('button', { name: /^Revisión \d+ ·/ }).first()
  const latestRevisionName = await latestRevisionToggle.innerText()
  await latestRevisionToggle.click()
  const latestRevision = detailDialog.getByRole('region', { name: latestRevisionName })
  await expect(latestRevision.getByText('Fallida', { exact: true })).toBeVisible()
  await expect(latestRevision.getByText('Costo registrado · COP', { exact: true })).toBeVisible()
  await expect(latestRevision.getByText(/Entrega fallida: se cobra el 40 %/)).toBeVisible()
  await latestRevision.getByText(/Eventos que respaldan esta revisión/).click()
  await expect(latestRevision.getByRole('list', { name: 'Referencias de eventos' }).getByRole('listitem')).toHaveCount(2)
  const oldestRevisionToggle = detailDialog.getByRole('button', { name: /^Revisión \d+ ·/ }).last()
  const oldestRevisionName = await oldestRevisionToggle.innerText()
  await oldestRevisionToggle.click()
  const oldestRevision = detailDialog.getByRole('region', { name: oldestRevisionName })
  await expect(oldestRevision.getByText('Fallida', { exact: true })).toBeVisible()
  await expect(oldestRevision.getByText('Faltan eventos', { exact: true })).toBeVisible()
  await expect(oldestRevision.getByText('Falta el evento: creada.', { exact: true })).toBeVisible()
  await expect(oldestRevision.getByText('Pendiente', { exact: true })).toBeVisible()
  await expect(oldestRevision.getByText('El costo aún no estaba confirmado en esta revisión.', { exact: true })).toBeVisible()
  await expect(detailDialog.locator('pre')).toHaveCount(0)
  await page.screenshot({ path: 'test-results/delivery-revisions.png', animations: 'disabled' })
  await page.getByRole('button', { name: 'Cerrar detalle' }).click()
  await expect(detailDialog).toBeHidden()
  await expect(page.getByRole('button', { name: 'Ver detalle' }).first()).toBeFocused()
  await page.getByRole('button', { name: 'Ver detalle' }).first().click()
  await expect(detailDialog).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(detailDialog).toBeHidden()
  await expect(page.getByRole('button', { name: 'Ver detalle' }).first()).toBeFocused()

  const operations = await (await page.request.get('/api/v1/merchants/1/operations?period=last_week')).json()
  const delivery = operations.deliveries.data.find((item: { delivery_address: string }) => item.delivery_address === 'Calle 80 # 12-20')
  const courierReport = await (await page.request.get(`/api/v1/merchants/1/operations?period=last_week&courier_id=${delivery.courier_id}`)).json()
  let chatReplies = 0
  let releaseFirstResponse!: () => void
  const firstResponseReady = new Promise<void>(resolve => { releaseFirstResponse = resolve })
  await page.route('**/api/v1/merchants/*/chat', async route => {
    chatReplies++
    const common = { request_id: `browser-fixture-${chatReplies}`, message: 'Datos consultados.' }
    const replies = [
      { tool: 'search_deliveries', data: { ...operations.deliveries, data: [delivery], total: 1, page: 1, last_page: 1 } },
      { message: `Respuesta extensa: ${'Entrega consultada. '.repeat(200)}`, data: null },
      { tool: 'get_merchant_totals', data: operations.totals },
      { tool: 'get_courier_status_counts', data: courierReport.courier_counts },
    ]
    if (chatReplies === 1) await firstResponseReady
    return route.fulfill({ json: { ...common, ...replies[chatReplies - 1] } })
  })
  await expect(page.getByLabel('Pregunta al asistente')).toBeHidden()
  await page.getByRole('button', { name: 'Asistente de operaciones', exact: true }).click()
  const chatDialog = page.getByRole('dialog', { name: 'Asistente de operaciones' })
  await expect(chatDialog).toBeVisible()
  await expectIdentifiedFields(page)
  await expect(page.getByLabel('Pregunta al asistente')).toHaveCSS('color', 'rgb(23, 37, 33)')
  await page.getByRole('button', { name: 'Buscar entregas de Calle 80', exact: true }).click()
  const thinking = chatDialog.getByRole('status', { name: 'Consultando al asistente' })
  try {
    await expect(thinking).toBeVisible()
    await expect(page.getByLabel('Pregunta al asistente')).toBeDisabled()
    await page.screenshot({ path: 'test-results/chat-thinking.png', animations: 'disabled' })
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await expect(thinking.getByText('Consultando…', { exact: true })).toBeVisible()
  } finally {
    releaseFirstResponse()
  }
  await expect(page.getByLabel('Pregunta al asistente')).toHaveValue('')
  await expect(chatDialog.getByText('Encontré 1 entrega.', { exact: true })).toBeVisible()
  await expect(thinking).toBeHidden()
  await page.emulateMedia({ reducedMotion: 'no-preference' })
  await expect(chatDialog.getByText('Calle 80 # 12-20', { exact: true })).toBeVisible()
  await expect(chatDialog.getByText('Lucía Moreno', { exact: true })).toBeVisible()
  await expect(chatDialog.getByText('Entregada', { exact: true })).toBeVisible()
  await expect(chatDialog.getByText(/7\.553,20/)).toBeVisible()
  await expect(chatDialog.getByText('Ver datos consultados')).toHaveCount(0)
  await expect(chatDialog.locator('pre')).toHaveCount(0)
  const userBubble = chatDialog.locator('article[data-role="user"] [data-slot="content"]').first()
  const assistantBubble = chatDialog.locator('article[data-role="assistant"] [data-slot="content"]').first()
  expect((await userBubble.boundingBox())!.x).toBeGreaterThan((await assistantBubble.boundingBox())!.x)
  expect(await userBubble.evaluate(el => getComputedStyle(el).backgroundColor)).not.toBe(await assistantBubble.evaluate(el => getComputedStyle(el).backgroundColor))
  await assistantBubble.hover()
  await chatDialog.getByRole('button', { name: 'Copiar respuesta', exact: true }).click()
  await expect(page.getByText('Respuesta copiada', { exact: true })).toBeVisible()
  const copiedReply = await page.evaluate(() => navigator.clipboard.readText())
  expect(copiedReply).toContain('Calle 80 # 12-20')
  expect(copiedReply).toContain('Lucía Moreno')
  expect(copiedReply).not.toContain('merchant_id')
  const chatHeight = (await chatDialog.boundingBox())!.height
  const documentHeight = await page.evaluate(() => document.documentElement.scrollHeight)
  await page.getByLabel('Pregunta al asistente').fill('Dame más detalles')
  await page.getByRole('button', { name: 'Enviar pregunta' }).click()
  await expect(chatDialog.getByText(/^Respuesta extensa:/)).toBeVisible()
  const conversation = page.getByRole('log', { name: 'Conversación del asistente' })
  await expect.poll(() => conversation.evaluate(el => el.scrollHeight > el.clientHeight)).toBeTruthy()
  expect((await chatDialog.boundingBox())!.height).toBeCloseTo(chatHeight, 0)
  expect(await page.evaluate(() => document.documentElement.scrollHeight)).toBe(documentHeight)
  await expect(page.getByLabel('Pregunta al asistente')).toBeInViewport()
  await page.getByRole('button', { name: 'Cerrar ventana del asistente' }).click()
  await expect(chatDialog).toBeHidden()
  await page.getByRole('button', { name: 'Asistente de operaciones', exact: true }).click()
  await expect(chatDialog.getByText(/^Respuesta extensa:/)).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(chatDialog).toBeHidden()
  await expect(page.getByRole('button', { name: 'Asistente de operaciones', exact: true })).toBeFocused()
  await page.getByRole('button', { name: 'Asistente de operaciones', exact: true }).click()
  await page.getByLabel('Pregunta al asistente').fill('¿Cuánto costaron las entregas la semana pasada?')
  await page.getByRole('button', { name: 'Enviar pregunta' }).click()
  await expect(chatDialog.getByText('Costo total confirmado · COP', { exact: true })).toBeVisible()
  await expect(chatDialog.getByText(/17\.199,55/)).toBeVisible()
  await expect(chatDialog.getByText('Costos de Tienda La 80', { exact: true })).toBeVisible()
  await expect(chatDialog.getByText('Entregadas', { exact: true })).toBeVisible()
  await expect(chatDialog.getByText('Fallidas', { exact: true })).toBeVisible()
  await page.getByLabel('Pregunta al asistente').fill('¿Qué estados tienen las entregas de Lucía Moreno la semana pasada?')
  await page.getByRole('button', { name: 'Enviar pregunta' }).click()
  await expect(chatDialog.getByText('Estados de las entregas de Lucía Moreno', { exact: true })).toBeVisible()
  await expect(chatDialog.getByText('Se muestra el estado actual de las entregas creadas en este período.', { exact: true })).toBeVisible()
  await page.screenshot({ path: 'test-results/chat-results-desktop.png', animations: 'disabled' })
  await page.getByRole('button', { name: 'Cerrar ventana del asistente' }).click()

  await page.locator('#event-entry').click()
  const eventDialog = page.getByRole('dialog', { name: 'Crear entrega' })
  await expect(eventDialog).toBeVisible()
  await expectIdentifiedFields(page)
  await expect.poll(() => eventDialog.evaluate(el => el.contains(document.activeElement))).toBeTruthy()
  await page.keyboard.press('Shift+Tab')
  await expect.poll(() => eventDialog.evaluate(el => el.contains(document.activeElement))).toBeTruthy()
  await page.keyboard.press('Escape')
  await expect(eventDialog).toBeHidden()
  await expect(page.locator('#event-entry')).toBeFocused()
  await page.locator('#event-entry').click()
  await expect(eventDialog).toBeVisible()
  await page.mouse.click(10, 10)
  await expect(eventDialog).toBeHidden()
  await page.locator('#event-entry').click()
  await choosePlannedRoute(page)
  let lostResponse = true
  let originalPayload: unknown
  await page.route('**/api/v1/events', async route => {
    if (lostResponse) {
      originalPayload = route.request().postDataJSON()
      lostResponse = false
      const response = await route.fetch()
      expect(response.ok(), await response.text()).toBe(true)
      await route.abort('failed')
    } else {
      expect(route.request().postDataJSON()).toEqual(originalPayload)
      await route.continue()
    }
  })
  await eventDialog.getByRole('button', { name: 'Crear entrega', exact: true }).click()
  await eventDialog.getByRole('button', { name: 'Reintentar la misma acción' }).click()
  await expect(eventDialog.getByText('Acción reconocida', { exact: true })).toBeVisible()
  await expect(eventDialog.getByText('Este envío ya estaba registrado.', { exact: false })).toBeVisible()
  await page.unroute('**/api/v1/events')
  await page.getByRole('button', { name: 'Cerrar formulario' }).click()
  await expect(eventDialog).toBeHidden()
  await page.getByRole('main').evaluate(el => el.parentElement!.scrollTo(0, 0))
  await page.screenshot({ path: 'test-results/operations-desktop.png', fullPage: true })
  await page.setViewportSize({ width: 390, height: 844 })
  await page.screenshot({ path: 'test-results/operations-mobile.png', fullPage: true })
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy()
  await page.locator('#event-entry').click()
  await expect(eventDialog).toBeVisible()
  const dialogBounds = await eventDialog.boundingBox()
  expect(dialogBounds).not.toBeNull()
  expect(dialogBounds!.width).toBeLessThanOrEqual(390)
  await page.getByRole('button', { name: 'Cerrar formulario' }).click()
  await expect(eventDialog).toBeHidden()
  await page.getByRole('button', { name: 'Ver detalle' }).first().click()
  await expect(detailDialog).toBeVisible()
  expect((await detailDialog.boundingBox())!.width).toBeLessThanOrEqual(390)
  await page.mouse.click(4, 4)
  await expect(detailDialog).toBeHidden()
  await page.getByRole('button', { name: 'Asistente de operaciones', exact: true }).click()
  await expect(chatDialog).toBeVisible()
  const chatBounds = (await chatDialog.boundingBox())!
  expect(chatBounds.x).toBeGreaterThanOrEqual(0)
  expect(chatBounds.x + chatBounds.width).toBeLessThanOrEqual(390)
  await expect(page.getByLabel('Pregunta al asistente')).toBeInViewport()
  await expect.poll(() => conversation.evaluate(el => el.scrollHeight > el.clientHeight)).toBeTruthy()
  await page.screenshot({ path: 'test-results/chat-mobile.png', animations: 'disabled' })
  await page.getByRole('button', { name: 'Cerrar ventana del asistente' }).click()
  expect(errors).toEqual([])
})

for (const role of ['reader', 'operator'] as const) {
  test(`help shows ${role} capabilities and working panel links`, async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel('Correo', { exact: true }).fill(role === 'reader' ? 'lector@cuantico.test' : 'operador@cuantico.test')
    await page.getByLabel('Contraseña', { exact: true }).fill(role === 'reader' ? 'cuantico-demo' : 'cuantico-operator')
    await page.getByRole('button', { name: 'Ingresar a operaciones' }).click()
    await page.getByRole('combobox', { name: 'Comercio activo' }).click()
    await page.getByRole('option', { name: 'Casa Botánica' }).click()
    await page.getByRole('button', { name: 'Asistente de operaciones', exact: true }).click()
    const chat = page.getByRole('dialog', { name: 'Asistente de operaciones' })
    const helpResponse = page.waitForResponse(response => response.url().endsWith('/chat'))
    await page.getByRole('button', { name: 'Ver comandos y permisos con /help' }).click()
    const help = await (await helpResponse).json()
    await expect(chat.getByRole('list', { name: 'Comandos y accesos disponibles' })).toBeVisible()
    await expect(chat.getByRole('link', { name: 'Buscar entregas', exact: true })).toHaveAttribute('href', '/operations#delivery-search')
    await expect(chat.getByRole('link', { name: 'Crear entrega', exact: true })).toHaveCount(role === 'operator' ? 1 : 0)
    await chat.getByRole('link', { name: 'Buscar entregas', exact: true }).click()
    await expect(chat).toBeHidden()
    await expect(page.getByLabel('Buscar entregas')).toBeFocused()
    await expect(page.getByRole('combobox', { name: 'Comercio activo' })).toHaveValue('Casa Botánica')
    await page.getByRole('button', { name: 'Asistente de operaciones', exact: true }).click()
    if (role === 'reader') {
      await page.route('**/api/v1/merchants/*/chat', route => route.fulfill({ json: {
        ...help, request_id: 'help-fallback-fixture', kind: 'clarification',
        message: 'No pude resolver esa consulta. Mi capacidad de respuesta todavía es limitada. Ahora puedo ayudarte con:',
      } }))
      await page.getByLabel('Pregunta al asistente').fill('Una pregunta que no se comprende')
      await page.getByRole('button', { name: 'Enviar pregunta' }).click()
      await expect(chat.getByText('No pude resolver esa consulta.', { exact: false })).toBeVisible()
      await chat.getByRole('link', { name: 'Consultar costos', exact: true }).last().click()
      await expect(chat).toBeHidden()
      await expect(page.locator('#costs-report')).toBeFocused()
    } else {
      await chat.getByRole('link', { name: 'Crear entrega', exact: true }).click()
      await expect(chat).toBeHidden()
      const event = page.getByRole('dialog', { name: 'Crear entrega' })
      await expect(event).toBeVisible()
      await page.getByRole('button', { name: 'Cerrar formulario' }).click()
      await expect(event).toBeHidden()
    }
  })
}


test('help questions send immediately and show query results inside the chat', async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('Correo', { exact: true }).fill('lector@cuantico.test')
  await page.getByLabel('Contraseña', { exact: true }).fill('cuantico-demo')
  await page.getByRole('button', { name: 'Ingresar a operaciones' }).click()
  await expect(page.getByRole('heading', { name: 'Entregas y costos' })).toBeVisible()
  const operationsResponse = await page.request.get('/api/v1/merchants/1/operations?period=last_week')
  expect(operationsResponse.ok()).toBe(true)
  const operations = await operationsResponse.json()
  const courier = operations.couriers[0]
  const counts = await (await page.request.get(`/api/v1/merchants/1/operations?period=last_week&courier_id=${courier.id}`)).json()
  await page.getByRole('button', { name: 'Asistente de operaciones', exact: true }).click()
  const chat = page.getByRole('dialog', { name: 'Asistente de operaciones' })
  const helpResponse = page.waitForResponse(response => response.url().endsWith('/chat'))
  await page.getByRole('button', { name: 'Ver comandos y permisos con /help' }).click()
  const help = await (await helpResponse).json()
  const queries = chat.getByRole('list', { name: 'Consultas disponibles en el chat' })
  await expect(queries.getByRole('button')).toHaveCount(3)
  const results = [
    { tool: 'get_merchant_totals', data: operations.totals, text: 'Costo total confirmado · COP' },
    { tool: 'get_courier_status_counts', data: counts.courier_counts, text: `Estados de las entregas de ${courier.name}` },
    { tool: 'search_deliveries', data: operations.deliveries, text: operations.deliveries.data[0].delivery_address },
  ]
  let sent = 0
  await page.route('**/api/v1/merchants/1/chat', async route => {
    expect(route.request().postDataJSON()).toEqual({ message: help.help.queries[sent].prompt })
    const result = results[sent++]
    await route.fulfill({ json: { request_id: `help-query-${sent}`, message: 'Datos consultados.', tool: result.tool, data: result.data } })
  })
  for (let i = 0; i < results.length; i++) {
    await queries.getByRole('button', { name: help.help.queries[i].prompt, exact: true }).click()
    await expect(chat.getByText(results[i].text, { exact: true })).toBeVisible()
    await expect(chat).toBeVisible()
    await expect(page.getByLabel('Pregunta al asistente')).toHaveValue('')
  }
  expect(sent).toBe(3)
})


test('a timed out chat question can be retried without duplicating the question', async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('Correo', { exact: true }).fill('lector@cuantico.test')
  await page.getByLabel('Contraseña', { exact: true }).fill('cuantico-demo')
  await page.getByRole('button', { name: 'Ingresar a operaciones' }).click()
  await expect(page.getByRole('heading', { name: 'Entregas y costos' })).toBeVisible()
  let attempts = 0
  const question = 'Busca entregas de Calle 80'
  await page.route('**/api/v1/merchants/1/chat', async route => {
    expect(route.request().postDataJSON()).toEqual({ message: question })
    attempts++
    if (attempts === 1) {
      await route.fulfill({ status: 504, json: { message: 'Gemini tardó demasiado en responder. Puedes volver a intentarlo; no hicimos reintentos automáticos.' } })
    } else {
      await route.fulfill({ json: { request_id: 'timeout-retry-success', message: 'Consulta recuperada correctamente.', data: null } })
    }
  })
  await page.getByRole('button', { name: 'Asistente de operaciones', exact: true }).click()
  const chat = page.getByRole('dialog', { name: 'Asistente de operaciones' })
  await page.getByLabel('Pregunta al asistente').fill(question)
  await page.getByRole('button', { name: 'Enviar pregunta' }).click()
  await expect(chat.getByText('Gemini tardó demasiado en responder.', { exact: false })).toBeVisible()
  expect(attempts).toBe(1)
  await chat.getByRole('button', { name: 'Reintentar', exact: true }).click()
  await expect(chat.getByText('Consulta recuperada correctamente.', { exact: true })).toBeVisible()
  await expect(chat.getByText(question, { exact: true })).toHaveCount(1)
  await expect(chat.getByRole('button', { name: 'Reintentar', exact: true })).toHaveCount(0)
  expect(attempts).toBe(2)
})


for (const touch of [false, true]) {
  test.describe(`chat launcher with ${touch ? 'touch' : 'mouse'}`, () => {
    test.use({ hasTouch: touch, viewport: touch ? { width: 390, height: 844 } : { width: 1280, height: 720 } })
    test('toggles closed without reopening', async ({ page }) => {
      await page.goto('/login')
      await page.getByLabel('Correo', { exact: true }).fill('lector@cuantico.test')
      await page.getByLabel('Contraseña', { exact: true }).fill('cuantico-demo')
      await page.getByRole('button', { name: 'Ingresar a operaciones' }).click()
      const launcher = page.getByRole('button', { name: 'Asistente de operaciones', exact: true })
      const chat = page.getByRole('dialog', { name: 'Asistente de operaciones' })
      for (let i = 0; i < 2; i++) {
        if (touch) await launcher.tap()
        else await launcher.click()
        await expect(chat).toBeVisible()
        if (touch) await launcher.locator('.assistant-close').tap()
        else await launcher.locator('.assistant-close').click()
        await expect(chat).toBeHidden()
        await expect(launcher).toHaveAttribute('aria-expanded', 'false')
      }
      if (!touch) {
        await launcher.press('Enter')
        await expect(chat).toBeVisible()
        await launcher.press('Enter')
        await expect(chat).toBeHidden()
        await expect(launcher).toBeFocused()
      }
    })
  })
}

test('courier counts keep their layout with skeletons while refreshing', async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('Correo', { exact: true }).fill('lector@cuantico.test')
  await page.getByLabel('Contraseña', { exact: true }).fill('cuantico-demo')
  await page.getByRole('button', { name: 'Ingresar a operaciones' }).click()
  await expect(page.getByText('Calle 80 # 12-20', { exact: true })).toBeVisible()
  const summary = page.locator('#courier-summary')
  let release!: () => void
  let waiting = new Promise<void>(resolve => { release = resolve })
  await page.route('**/api/v1/merchants/1/operations?*', async route => {
    if (new URL(route.request().url()).searchParams.has('courier_id')) await waiting
    await route.continue()
  })
  await summary.getByRole('combobox', { name: 'Mensajero', exact: true }).click()
  await page.getByRole('option', { name: 'Lucía Moreno', exact: true }).click()
  const status = summary.getByRole('status', { name: 'Consultando estados por mensajero' })
  await expect(status).toBeVisible()
  await expect(summary.locator('[aria-busy][aria-atomic="true"]')).toHaveAttribute('aria-busy', 'true')
  const loadingHeight = (await summary.boundingBox())!.height
  release()
  await expect(status).toBeHidden()
  await expect(summary.getByText(/Lucía Moreno · creadas desde/)).toBeVisible()
  const loadedHeight = (await summary.boundingBox())!.height
  expect(Math.abs(loadingHeight - loadedHeight)).toBeLessThanOrEqual(8)
  waiting = new Promise<void>(resolve => { release = resolve })
  await page.getByRole('button', { name: 'Actualizar', exact: true }).click()
  await expect(status).toBeVisible()
  expect(Math.abs((await summary.boundingBox())!.height - loadedHeight)).toBeLessThanOrEqual(8)
  release()
  await expect(status).toBeHidden()
  await expect(summary.getByText(/Lucía Moreno · creadas desde/)).toBeVisible()
})


async function setOccurrence(page: Page, date: string, minute: number) {
  await setDate(page, 'Fecha y hora de ocurrencia', date)
  const field = page.getByLabel('Fecha y hora de ocurrencia', { exact: true })
  for (const [segment, text] of [['hour', '20'], ['minute', String(minute).padStart(2, '0')], ['second', '00']]) {
    const part = field.locator(`[data-segment="${segment}"]`)
    await part.click()
    await part.pressSequentially(text)
  }
}

for (const outcome of ['delivered', 'failed'] as const) {
  test(`operator completes a ${outcome} delivery using contextual row actions`, async ({ page }) => {
    test.setTimeout(60000)
    await page.goto('/login')
    await page.getByLabel('Correo', { exact: true }).fill('operador@cuantico.test')
    await page.getByLabel('Contraseña', { exact: true }).fill('cuantico-operator')
    await page.getByRole('button', { name: 'Ingresar a operaciones' }).click()
    await expect(page.getByRole('heading', { name: 'Entregas y costos' })).toBeVisible()
    const operations = await (await page.request.get('/api/v1/merchants/1/operations?period=last_week')).json()
    const date = operations.totals.period.start_date
    const received: { request: { event_id: string; delivery_id: string; occurred_at: string; data: Record<string, unknown> }; result: Delivery }[] = []
    async function save(label: string) {
      const response = page.waitForResponse(response => response.url().endsWith('/api/v1/events') && response.request().method() === 'POST')
      await page.getByRole('dialog', { name: label, exact: true }).getByRole('button', { name: label, exact: true }).click()
      const reply = await response
      expect(reply.ok()).toBe(true)
      received.push({ request: reply.request().postDataJSON(), result: (await reply.json()).delivery })
      await page.getByRole('button', { name: 'Volver a entregas', exact: true }).click()
    }
    await page.locator('#event-entry').click()
    await expect(page.getByLabel('ID de la entrega', { exact: true })).toHaveCount(0)
    await expect(page.getByLabel('ID del evento', { exact: true })).toHaveCount(0)
    await choosePlannedRoute(page)
    await expectIdentifiedFields(page)
    await setOccurrence(page, date, 10)
    const dateField = page.getByLabel('Fecha y hora de ocurrencia', { exact: true })
    const lastSegment = (await dateField.locator('[data-segment="second"]').boundingBox())!
    const calendarButton = dateField.getByRole('button', { name: 'Abrir calendario: Fecha y hora de ocurrencia' })
    const calendarBounds = (await calendarButton.boundingBox())!
    expect(calendarBounds.x - (lastSegment.x + lastSegment.width)).toBeLessThan(48)
    await calendarButton.click()
    await expect(page.getByRole('gridcell', { selected: true })).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(page.getByRole('dialog', { name: 'Crear entrega', exact: true })).toBeVisible()
    await page.screenshot({ path: `test-results/create-delivery-${outcome}.png`, animations: 'disabled' })
    if (outcome === 'delivered') {
      await page.setViewportSize({ width: 390, height: 844 })
      await page.screenshot({ path: 'test-results/create-delivery-mobile.png', animations: 'disabled' })
      await page.setViewportSize({ width: 1280, height: 720 })
    }
    await save('Crear entrega')
    await page.reload()
    const row = page.getByRole('row').filter({ hasText: received[0].result.id.slice(0, 8) })
    await expect(row.getByText('Museo del Oro · Carrera 6 # 15-88, Bogotá', { exact: true })).toBeVisible()
    await expect(row.getByText('Palacio Liévano · Carrera 8 # 10-65, Bogotá', { exact: true })).toBeVisible()
    await expect(row.getByText('Creada', { exact: true })).toBeVisible()
    await expect(row.getByText('Pendiente de confirmar', { exact: true })).toBeVisible()
    const steps = outcome === 'delivered' ? ['Asignar mensajero', 'Registrar recogida', 'Marcar en ruta', 'Registrar entrega'] : ['Registrar falla']
    for (let i = 0; i < steps.length; i++) {
      const action = steps[i]
      if (action === 'Registrar falla') {
        await row.getByRole('button', { name: /^Más acciones para/ }).click()
        await page.getByRole('menuitem', { name: 'Registrar falla', exact: true }).click()
      } else await row.getByRole('button', { name: action, exact: true }).click()
      const dialog = page.getByRole('dialog', { name: action, exact: true })
      await expect(dialog).toBeVisible()
      await expectIdentifiedFields(page)
      await setOccurrence(page, date, i + 11)
      if (action === 'Asignar mensajero') {
        await dialog.getByRole('combobox', { name: /^Mensajero/ }).click()
        await page.getByRole('option', { name: 'Lucía Moreno', exact: true }).click()
      }
      if (action === 'Registrar recogida' || action === 'Registrar entrega') {
        await expect(dialog.getByRole('combobox', { name: 'Buscar ubicación de demostración', exact: true })).toHaveCount(0)
        const picker = dialog.getByRole('region', { name: action === 'Registrar recogida' ? 'Lugar de recogida' : 'Lugar de entrega', exact: true })
        await expect(picker.getByText(action === 'Registrar recogida' ? 'Museo del Oro · Carrera 6 # 15-88, Bogotá' : 'Palacio Liévano · Carrera 8 # 10-65, Bogotá', { exact: true })).toBeVisible()
        await expect(picker.getByText('Ubicación confirmada', { exact: true })).toHaveCount(0)
        await picker.getByRole('button', { name: 'Confirmar ubicación', exact: true }).click()
        await expect(picker.getByText('Ubicación confirmada', { exact: true })).toBeVisible()
      }
      await save(action)
    }
    const final = received.at(-1)!.result
    expect(final.status).toBe(outcome)
    expect(final.quality).toBe('consistent')
    expect(final.cost_minor).toBeGreaterThan(0)
    expect(final.calculation.timezone).toBe('America/Bogota')
    expect(final.calculation.night).toBe(outcome === 'delivered')
    expect(final.calculation.failed_percent).toBe(outcome === 'failed' ? 40 : null)
    expect(new Set(received.map(item => item.request.delivery_id)).size).toBe(1)
    expect(new Set(received.map(item => item.request.event_id)).size).toBe(received.length)
    expect(received[0].request.occurred_at).toBe(new Date(`${date}T20:10:00-05:00`).toISOString())
    await expect(row.getByText(outcome === 'delivered' ? 'Entregada' : 'Fallida', { exact: true })).toBeVisible()
    await row.getByRole('button', { name: /^Ver detalle/ }).click()
    const detail = page.getByRole('dialog', { name: 'Detalle y trazabilidad' })
    await expect(detail.getByText('Tarifa base', { exact: true })).toBeVisible()
    await expect(detail.getByRole('heading', { name: `Revisiones conservadas (${received.length})`, exact: true })).toBeVisible()
  })
}


async function openPickup(page: Page) {
  await page.goto('/login')
  await page.getByLabel('Correo', { exact: true }).fill('operador@cuantico.test')
  await page.getByLabel('Contraseña', { exact: true }).fill('cuantico-operator')
  await page.getByRole('button', { name: 'Ingresar a operaciones' }).click()
  const row = page.getByRole('row').filter({ hasText: 'Calle 83 # 12-23' })
  await row.getByRole('button', { name: 'Registrar recogida', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: 'Registrar recogida', exact: true })
  await dialog.getByRole('button', { name: 'Cambiar ubicación', exact: true }).click()
  return dialog
}

test('local location search requires confirmation and works when the map is unavailable', async ({ page }) => {
  let tileRequests = 0
  let eventRequests = 0
  page.on('request', request => { if (request.url().endsWith('/api/v1/events')) eventRequests++ })
  await page.route('https://tile.openstreetmap.org/**', route => { tileRequests++; return route.abort() })
  const dialog = await openPickup(page)
  const search = dialog.getByRole('combobox', { name: 'Buscar ubicación de demostración', exact: true })
  await search.fill('Una dirección que no está en el catálogo')
  await expect(page.getByText('No hay coincidencias en el catálogo.', { exact: false })).toBeVisible()
  await dialog.getByRole('button', { name: 'Registrar recogida', exact: true }).click()
  await expect(dialog.getByText('Completa los datos necesarios para registrar esta acción.', { exact: true })).toBeVisible()
  expect(eventRequests).toBe(0)
  await search.fill('Museo')
  await search.press('ArrowDown')
  await search.press('Enter')
  await expect(dialog.getByRole('button', { name: 'Confirmar ubicación', exact: true })).toBeVisible()
  await dialog.getByRole('button', { name: 'Confirmar ubicación', exact: true }).click()
  await expect(dialog.getByText('Ubicación confirmada', { exact: true })).toBeVisible()
  expect(tileRequests).toBe(0)
  await search.fill('Otra dirección que no está')
  await expect(dialog.getByText('Ubicación confirmada', { exact: true })).toHaveCount(0)
  await expect(dialog.getByRole('button', { name: 'Confirmar ubicación', exact: true })).toHaveCount(0)
  await search.fill('Carrera 8')
  await page.getByRole('option', { name: /Palacio Liévano/ }).click()
  await dialog.getByRole('button', { name: 'Ajustar en el mapa', exact: true }).click()
  await expect(dialog.getByText('No se pudo cargar el mapa completo.', { exact: false })).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Seleccionar centro del mapa', exact: true })).toBeDisabled()
  await dialog.getByRole('button', { name: 'Confirmar ubicación', exact: true }).click()
  await expect(dialog.getByText('Ubicación confirmada', { exact: true })).toBeVisible()
  expect(tileRequests).toBeGreaterThan(0)
  expect(eventRequests).toBe(0)
  await page.setViewportSize({ width: 390, height: 844 })
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
  await page.screenshot({ path: 'test-results/location-catalog-offline-mobile.png', animations: 'disabled' })
})

test('the optional map adjusts a catalog point before recording its coordinates', async ({ page }) => {
  await page.route('https://tile.openstreetmap.org/**', route => route.fulfill({ contentType: 'image/png', body: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6iS8AAAAASUVORK5CYII=', 'base64') }))
  const errors: string[] = []
  page.on('pageerror', error => errors.push(error.message))
  const dialog = await openPickup(page)
  const search = dialog.getByRole('combobox', { name: 'Buscar ubicación de demostración', exact: true })
  await search.fill('Palacio')
  await page.getByRole('option', { name: /Palacio Liévano/ }).click()
  await dialog.getByRole('button', { name: 'Ajustar en el mapa', exact: true }).click()
  await expect(dialog.getByRole('button', { name: 'Seleccionar centro del mapa', exact: true })).toBeEnabled()
  const map = dialog.getByRole('region', { name: 'Mapa para ajustar la ubicación', exact: true })
  await map.click({ position: { x: 190, y: 60 } })
  await expect(dialog.getByText('Punto ajustado cerca de', { exact: false })).toBeVisible()
  await dialog.getByRole('button', { name: 'Confirmar ubicación', exact: true }).click()
  const response = page.waitForResponse(response => response.url().endsWith('/api/v1/events') && response.request().method() === 'POST')
  await dialog.getByRole('button', { name: 'Registrar recogida', exact: true }).click()
  const reply = await response
  expect(reply.ok()).toBe(true)
  const data = reply.request().postDataJSON().data
  expect(data.latitude).toBeGreaterThan(4.5)
  expect(data.latitude).toBeLessThan(4.7)
  expect(data.longitude).toBeGreaterThan(-74.2)
  expect(data.longitude).toBeLessThan(-74)
  expect(data.latitude === 4.598414 && data.longitude === -74.076539).toBe(false)
  expect((await reply.json()).delivery.status).toBe('picked_up')
  expect(errors).toEqual([])
})


test('selection fields open on focus and expose native clear buttons', async ({ page }) => {
  test.setTimeout(60000)
  await page.goto('/login')
  await page.getByLabel('Correo', { exact: true }).fill('operador@cuantico.test')
  await page.getByLabel('Contraseña', { exact: true }).fill('cuantico-operator')
  await page.getByRole('button', { name: 'Ingresar a operaciones' }).click()
  await expect(page.getByRole('heading', { name: 'Entregas y costos' })).toBeVisible()
  const merchant = page.getByRole('combobox', { name: 'Comercio activo', exact: true })
  await merchant.focus()
  await expect(page.getByRole('option', { name: 'Casa Botánica', exact: true })).toBeVisible()
  await page.getByRole('option', { name: 'Casa Botánica', exact: true }).click()
  await expect(merchant).toHaveValue('Casa Botánica')
  await page.getByLabel('Limpiar comercio', { exact: true }).click()
  await expect(merchant).toHaveValue('')
  await expect(page.getByLabel('Buscar entregas', { exact: true })).toHaveCount(0)
  await merchant.blur()
  await merchant.focus()
  await page.getByRole('option', { name: 'Tienda La 80', exact: true }).click()
  const summary = page.locator('#courier-summary')
  const courier = summary.getByRole('combobox', { name: 'Mensajero', exact: true })
  await courier.focus()
  await page.getByRole('option', { name: 'Lucía Moreno', exact: true }).click()
  await expect(courier).toHaveValue('Lucía Moreno')
  await summary.getByLabel('Limpiar mensajero', { exact: true }).click()
  await expect(courier).toHaveValue('')
  await expect(summary.getByText('Elige un mensajero', { exact: false })).toBeVisible()
  await page.locator('#event-entry').click()
  const creation = page.getByRole('dialog', { name: 'Crear entrega', exact: true })
  const origin = creation.getByRole('region', { name: 'Origen previsto', exact: true })
  const location = origin.getByRole('combobox', { name: 'Buscar ubicación de demostración', exact: true })
  await location.focus()
  await expect(page.getByRole('option', { name: /Museo del Oro/ })).toBeVisible()
  await page.getByRole('option', { name: /Museo del Oro/ }).click()
  await expect(origin.getByLabel('Limpiar origen previsto', { exact: true })).toBeVisible()
  await origin.getByRole('button', { name: 'Confirmar ubicación', exact: true }).click()
  await origin.getByLabel('Limpiar origen previsto', { exact: true }).click()
  await expect(location).toHaveValue('')
  await expect(origin.getByText('Ubicación confirmada', { exact: true })).toHaveCount(0)
  await expect(origin.getByRole('button', { name: 'Confirmar ubicación', exact: true })).toHaveCount(0)
  await choosePlannedRoute(page)
  await creation.getByRole('button', { name: 'Crear entrega', exact: true }).click()
  await creation.getByRole('button', { name: 'Asignar mensajero', exact: true }).click()
  const assignment = page.getByRole('dialog', { name: 'Asignar mensajero', exact: true })
  const assignee = assignment.getByRole('combobox', { name: /^Mensajero/ })
  await assignee.focus()
  await expect(page.getByRole('option', { name: 'Sara López', exact: true })).toBeVisible()
  await page.getByRole('option', { name: 'Sara López', exact: true }).click()
  await expect(assignee).toHaveValue('Sara López')
  await assignment.getByLabel('Limpiar mensajero asignado', { exact: true }).click()
  await expect(assignee).toHaveValue('')
  await expectIdentifiedFields(page)
})
