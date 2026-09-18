import { api } from './client'
import type { CreateReportInput, Report } from '@/types/api'

/**
 * Reportes. Contrato: docs/api/comunidad-notificaciones.md.
 *
 * `self_harm` y `minor_safety` se escalan como críticos en el backend y se
 * salen de la cola normal — por eso el diálogo los ofrece con su propio texto
 * y no como "otro motivo más".
 */
export const reportsApi = {
  create: (input: CreateReportInput) =>
    api.post<{ data: Report }>('/reports', input).then((r) => r.data.data),
}
