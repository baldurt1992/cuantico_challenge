import { describe, expect, it } from 'vitest'
import { periodLabel } from '../../resources/js/lib/format'
import type { Period } from '../../resources/js/types'

function period(start: string, end: string): Period {
  return { start: `${start}T05:00:00Z`, end: `${end}T05:00:00Z`, start_date: start, end_date_exclusive: end, timezone: 'America/Bogota', criterion: 'finalización' }
}

describe('chat report dates', () => {
  it('shows the last included date across a year boundary', () => {
    const label = periodLabel(period('2026-12-31', '2027-01-02'))
    expect(label).toContain('31')
    expect(label).toContain('2026')
    expect(label).toContain('2027')
    expect(label).toContain('1 de enero')
    expect(label).not.toContain('2 de enero')
  })

  it('shows a one-day range as a single date', () => {
    expect(periodLabel(period('2026-10-29', '2026-10-30'))).toBe('29 de octubre de 2026')
  })
})
