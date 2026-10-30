import { effectScope, nextTick, shallowRef } from 'vue'
import { describe, it, expect, vi } from 'vitest'
import { useChat } from '../../resources/js/composables/useChat'
import { ApiError, requestJson } from '../../resources/js/lib/api'
vi.mock('../../resources/js/lib/api', async importOriginal => ({ ...(await importOriginal<typeof import('../../resources/js/lib/api')>()), requestJson: vi.fn() }))

describe('chat context boundaries', () => {
  it('discards a late reply when the merchant changes', async () => {
    let resolve!: (value: unknown) => void
    vi.mocked(requestJson).mockReturnValue(new Promise(r => { resolve = r }))
    const merchant = shallowRef(1)
    const scope = effectScope()
    const chat = scope.run(() => useChat(merchant))!
    const pending = chat.send('Consulta del comercio anterior')
    merchant.value = 2
    await nextTick()
    resolve({ request_id: 'old', message: 'Respuesta vieja', data: null })
    await pending
    expect(chat.messages.value).toEqual([])
    expect(chat.loading.value).toBe(false)
    scope.stop()
  })

  it('retains the question and exposes a recoverable request error', async () => {
    vi.mocked(requestJson).mockRejectedValue(new Error('Cuota agotada'))
    const scope = effectScope()
    const chat = scope.run(() => useChat(shallowRef(1)))!
    await chat.send('Consulta')
    expect(chat.error.value).toBe('Cuota agotada')
    expect(chat.messages.value).toHaveLength(1)
    expect(chat.loading.value).toBe(false)
    scope.stop()
  })
})


it('retries a timed out question once without duplicating it and clears retry on success', async () => {
  vi.mocked(requestJson).mockRejectedValueOnce(new ApiError('Gemini tardó demasiado', 504))
  const scope = effectScope()
  const chat = scope.run(() => useChat(shallowRef(1)))!
  await chat.send('Costos de la semana pasada')
  expect(chat.canRetry.value).toBe(true)
  let resolve!: (value: unknown) => void
  vi.mocked(requestJson).mockReturnValueOnce(new Promise(r => { resolve = r }))
  const pending = chat.retry()
  await chat.retry()
  expect(chat.loading.value).toBe(true)
  expect(chat.messages.value).toHaveLength(1)
  const calls = vi.mocked(requestJson).mock.calls
  expect(calls.at(-1)![1]!.body).toBe(calls.at(-2)![1]!.body)
  resolve({ request_id: 'retry-success', message: 'Datos consultados', data: null })
  await pending
  expect(chat.messages.value.map(item => item.role)).toEqual(['user', 'assistant'])
  expect(chat.error.value).toBe('')
  expect(chat.canRetry.value).toBe(false)
  scope.stop()
})

it('clears the timed out question when the merchant changes', async () => {
  vi.mocked(requestJson).mockRejectedValueOnce(new ApiError('Tiempo agotado', 504))
  const merchant = shallowRef(1)
  const scope = effectScope()
  const chat = scope.run(() => useChat(merchant))!
  await chat.send('Costos anteriores')
  merchant.value = 2
  await nextTick()
  expect(chat.canRetry.value).toBe(false)
  vi.mocked(requestJson).mockClear()
  await chat.retry()
  expect(requestJson).not.toHaveBeenCalled()
  expect(chat.messages.value).toEqual([])
  scope.stop()
})

it.each([401, 403, 419, 422, 429])('does not offer timeout retry for status %s', async status => {
  vi.mocked(requestJson).mockRejectedValueOnce(new ApiError('Solicitud rechazada', status))
  const scope = effectScope()
  const chat = scope.run(() => useChat(shallowRef(1)))!
  await chat.send('Consulta')
  expect(chat.canRetry.value).toBe(false)
  scope.stop()
})
