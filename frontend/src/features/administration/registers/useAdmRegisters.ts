import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  admRegistersService,
  type RegisterListParams,
  type RegisterListResult,
} from '@/services/admRegistersService'
import type { AdmReportParams } from '@/types/admRegisters'

export const registerKeys = {
  all: ['administration', 'registers'] as const,
  lookups: ['administration', 'registers', 'lookups'] as const,
  list: (resource: string, params: object) => ['administration', 'registers', 'list', resource, params] as const,
  history: (resource: string, id: number) => ['administration', 'registers', 'history', resource, id] as const,
  report: (params: AdmReportParams) => ['administration', 'staff', 'report', params] as const,
  sla: ['administration', 'staff', 'sla'] as const,
  cctv: (id: string | number) => ['administration', 'staff', 'cctv', String(id)] as const,
}

export function useRegisterLookups() {
  return useQuery({
    queryKey: registerKeys.lookups,
    queryFn: admRegistersService.lookups,
    staleTime: 60_000,
  })
}

/** Paginated register list that keeps the previous page on screen while the next one loads. */
export function useRegisterList<T>(
  resource: string,
  fetcher: (params: RegisterListParams) => Promise<RegisterListResult<T>>,
  params: RegisterListParams,
  enabled = true,
) {
  return useQuery({
    queryKey: registerKeys.list(resource, params),
    queryFn: () => fetcher(params),
    placeholderData: keepPreviousData,
    enabled,
  })
}

/** Runs a write and refreshes every register list, the pick-lists and the reports. */
export function useRegisterMutation<TArgs, TResult>(mutationFn: (args: TArgs) => Promise<TResult>) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn,
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: registerKeys.all }),
        queryClient.invalidateQueries({ queryKey: ['administration', 'staff', 'report'] }),
      ])
    },
  })
}

export function useRegisterHistory(
  resource: 'insurance' | 'fuel-cards',
  id: number | null,
  fetcher: (id: number) => ReturnType<typeof admRegistersService.insurance.history>,
) {
  return useQuery({
    queryKey: registerKeys.history(resource, id ?? 0),
    queryFn: () => fetcher(id ?? 0),
    enabled: id !== null,
  })
}

export function useAdmReport(params: AdmReportParams) {
  return useQuery({
    queryKey: registerKeys.report(params),
    queryFn: () => admRegistersService.report(params),
    placeholderData: keepPreviousData,
  })
}

export function useAdmSla() {
  return useQuery({ queryKey: registerKeys.sla, queryFn: admRegistersService.sla.list })
}
