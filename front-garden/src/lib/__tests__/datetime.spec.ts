import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { relativeTime, toIsoZ } from '../datetime'

describe('toIsoZ', () => {
  it('turns a datetime-local value into an ISO-8601 Z string without milliseconds', () => {
    // The offset depends on the runner's TZ, so just assert the shape.
    const out = toIsoZ('2027-04-12T09:00')
    expect(out).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/)
  })

  it('returns null for an empty or invalid value', () => {
    expect(toIsoZ('')).toBeNull()
    expect(toIsoZ('not-a-date')).toBeNull()
  })
})

describe('relativeTime', () => {
  beforeEach(() => vi.setSystemTime(new Date('2027-01-01T12:00:00Z')))
  afterEach(() => vi.useRealTimers())

  it('formats a past instant', () => {
    expect(relativeTime('2027-01-01T09:00:00Z')).toBeTruthy()
  })

  it('is empty for a nullish input', () => {
    expect(relativeTime(null)).toBe('')
    expect(relativeTime(undefined)).toBe('')
  })
})
