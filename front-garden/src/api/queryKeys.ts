/** Central registry of TanStack Query keys, so invalidation stays consistent. */
export const qk = {
  letters: {
    all: ['letters'] as const,
    list: (status?: string) => ['letters', 'list', status ?? 'all'] as const,
    one: (id: string) => ['letters', 'one', id] as const,
    styles: ['letters', 'styles'] as const,
  },
  deliveries: {
    all: ['deliveries'] as const,
    list: (status?: string) => ['deliveries', 'list', status ?? 'all'] as const,
    one: (id: string) => ['deliveries', 'one', id] as const,
    tracking: (id: string) => ['deliveries', 'tracking', id] as const,
  },
  mailbox: {
    all: ['mailbox'] as const,
    list: (status?: string) => ['mailbox', 'list', status ?? 'all'] as const,
    one: (id: string) => ['mailbox', 'one', id] as const,
    unreadCount: ['mailbox', 'unread-count'] as const,
  },
  notifications: {
    all: ['notifications'] as const,
    list: ['notifications', 'list'] as const,
    unreadCount: ['notifications', 'unread-count'] as const,
  },
  support: {
    resources: (country?: string | null, topic?: string) =>
      ['support', 'resources', country ?? 'global', topic ?? 'all'] as const,
  },
  schedules: {
    all: ['schedules'] as const,
    list: ['schedules', 'list'] as const,
    one: (id: string) => ['schedules', 'one', id] as const,
    occurrences: (id: string) => ['schedules', 'occurrences', id] as const,
  },
  random: {
    quota: ['random', 'quota'] as const,
  },
  features: ['features'] as const,
} as const
