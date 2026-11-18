import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'

import { PageHeader } from '@/components/common/PageHeader'
import { Alert } from '@/components/ui/Alert'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { Input } from '@/components/ui/Input'
import { LoadingPanel } from '@/components/ui/Spinner'
import { registerKeys, useAdmSla } from '@/features/administration/registers/useAdmRegisters'
import { useToast } from '@/hooks/useToast'
import { admRegistersService } from '@/services/admRegistersService'
import { ApiError } from '@/types/api'
import type { AdmSlaCategory } from '@/types/admRegisters'

function validHours(value: string): number | null {
  const hours = Number(value)

  return Number.isInteger(hours) && hours >= 1 && hours <= 720 ? hours : null
}

export function AdmSettingsPage() {
  const toast = useToast()
  const queryClient = useQueryClient()
  const { data, isPending, isError, error, refetch, isRefetching } = useAdmSla()

  // Draft hours keyed `c-<id>` / `t-<id>`, plus the "apply to all types" choice per category.
  const [hours, setHours] = useState<Record<string, string>>({})
  const [applyToTypes, setApplyToTypes] = useState<Record<number, boolean>>({})

  useEffect(() => {
    if (!data) return

    const next: Record<string, string> = {}
    for (const category of data) {
      next[`c-${category.id}`] = String(category.target_hours ?? category.default_sla_hours ?? 48)
      for (const type of category.types) {
        next[`t-${type.id}`] = String(type.target_hours ?? type.sla_hours ?? category.target_hours ?? 48)
      }
    }
    setHours(next)
  }, [data])

  const onSuccess = (catalogue: AdmSlaCategory[], message: string) => {
    queryClient.setQueryData(registerKeys.sla, catalogue)
    toast.success('SLA updated', message)
  }
  const onError = (cause: unknown) => {
    toast.error('Could not save SLA', cause instanceof ApiError ? cause.message : 'Please try again.')
  }

  const saveCategory = useMutation({
    mutationFn: ({ id, value, apply }: { id: number; value: number; apply: boolean }) =>
      admRegistersService.sla.updateCategory(id, value, apply),
    onSuccess: (catalogue) => onSuccess(catalogue, 'Category target time was saved.'),
    onError,
  })
  const saveType = useMutation({
    mutationFn: ({ id, value }: { id: number; value: number }) => admRegistersService.sla.updateType(id, value),
    onSuccess: (catalogue) => onSuccess(catalogue, 'Request type target time was saved.'),
    onError,
  })
  const saving = saveCategory.isPending || saveType.isPending

  if (isPending && !data) {
    return <LoadingPanel label="Loading SLA settings..." />
  }

  if (isError || !data) {
    return (
      <div className="space-y-6">
        <PageHeader title="Settings" description="SLA targets for Administration request categories." />
        <Alert tone="danger" title="Unable to load SLA settings">
          {error instanceof ApiError ? error.message : 'Please try again.'}
          <div className="mt-3">
            <Button variant="outline" isLoading={isRefetching} onClick={() => void refetch()}>
              Retry
            </Button>
          </div>
        </Alert>
      </div>
    )
  }

  function saveCategoryHours(category: AdmSlaCategory) {
    const value = validHours(hours[`c-${category.id}`] ?? '')
    if (value === null) {
      toast.error('Invalid hours', 'Enter a whole number between 1 and 720.')
      return
    }

    saveCategory.mutate({ id: category.id, value, apply: Boolean(applyToTypes[category.id]) })
  }

  function saveTypeHours(typeId: number) {
    const value = validHours(hours[`t-${typeId}`] ?? '')
    if (value === null) {
      toast.error('Invalid hours', 'Enter a whole number between 1 and 720.')
      return
    }

    saveType.mutate({ id: typeId, value })
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Settings"
        description="Target resolution time (SLA) for each Administration category. New requests use the target of their request type, falling back to the category."
      />

      <div className="space-y-4">
        {data.map((category) => (
          <Card key={category.id}>
            <CardHeader
              title={category.name}
              description={category.is_active ? 'Applies to new requests in this category.' : 'This category is currently inactive.'}
              action={!category.is_active ? <Badge tone="neutral">Inactive</Badge> : undefined}
            />
            <CardContent className="space-y-5">
              <div className="flex flex-col gap-3 rounded-xl border border-surface-200 bg-surface-50 px-4 py-3 sm:flex-row sm:items-end sm:justify-between">
                <div className="grid flex-1 gap-3 sm:grid-cols-2">
                  <label className="block text-xs font-medium text-surface-600">
                    Category target (hours)
                    <Input
                      className="mt-1.5"
                      type="number"
                      min={1}
                      max={720}
                      value={hours[`c-${category.id}`] ?? ''}
                      onChange={(event) => setHours((current) => ({ ...current, [`c-${category.id}`]: event.target.value }))}
                    />
                  </label>
                  {category.types.length > 0 && (
                    <label className="flex items-center gap-2 pt-6 text-sm text-surface-700">
                      <input
                        type="checkbox"
                        checked={Boolean(applyToTypes[category.id])}
                        onChange={(event) => setApplyToTypes((current) => ({ ...current, [category.id]: event.target.checked }))}
                      />
                      Also set every request type below
                    </label>
                  )}
                </div>
                <Button variant="secondary" disabled={saving} onClick={() => saveCategoryHours(category)}>
                  Save category
                </Button>
              </div>

              {category.types.length > 0 && (
                <ul className="divide-y divide-surface-100 rounded-xl border border-surface-200">
                  {category.types.map((type) => (
                    <li key={type.id} className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-end sm:justify-between">
                      <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium text-surface-900">{type.name}</p>
                        <label className="mt-2 block max-w-xs text-xs font-medium text-surface-600">
                          Target hours
                          <Input
                            className="mt-1.5"
                            type="number"
                            min={1}
                            max={720}
                            value={hours[`t-${type.id}`] ?? ''}
                            onChange={(event) => setHours((current) => ({ ...current, [`t-${type.id}`]: event.target.value }))}
                          />
                        </label>
                      </div>
                      <Button variant="outline" disabled={saving} onClick={() => saveTypeHours(type.id)}>
                        Save
                      </Button>
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}
