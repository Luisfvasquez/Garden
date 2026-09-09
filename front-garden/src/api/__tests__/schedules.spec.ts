import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import type { InternalAxiosRequestConfig } from 'axios'
import { api } from '../client'
import { schedulesApi } from '../schedules'

let seen: InternalAxiosRequestConfig

beforeEach(() => {
  document.cookie = 'XSRF-TOKEN=test'
  api.defaults.adapter = async (config) => {
    seen = config
    return {
      data: { data: { id: 's1' } },
      status: 200,
      statusText: 'OK',
      headers: {},
      config,
    }
  }
})

afterEach(() => {
  api.defaults.adapter = undefined as never
})

describe('schedulesApi', () => {
  it('creates a schedule at the collection endpoint', async () => {
    await schedulesApi.create({
      name: 'Ann',
      recipient: { postal_handle: 'ann-1234' },
      recurrence_type: 'yearly',
      anchor_date: '2027-04-12',
      local_time: '09:00',
      timezone: 'UTC',
      occurrences_total: 10,
    })

    expect(seen.method).toBe('post')
    expect(seen.url).toBe('/schedules')
  })

  it('assigns a letter to one occurrence date via PUT', async () => {
    await schedulesApi.assignLetter('s1', '2028-04-12', 'letter-9')

    expect(seen.method).toBe('put')
    expect(seen.url).toBe('/schedules/s1/occurrences/2028-04-12/letter')
    expect(seen.data).toBe(JSON.stringify({ letter_id: 'letter-9' }))
  })

  it('clears an occurrence letter with null', async () => {
    await schedulesApi.assignLetter('s1', '2028-04-12', null)

    expect(seen.data).toBe(JSON.stringify({ letter_id: null }))
  })
})
