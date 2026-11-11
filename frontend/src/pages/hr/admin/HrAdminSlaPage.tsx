import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'

import { PageHeader } from '@/components/common/PageHeader'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { Input } from '@/components/ui/Input'
import { LoadingPanel } from '@/components/ui/Spinner'
import { useToast } from '@/hooks/useToast'
import { hrService } from '@/services/hrService'
import { ApiError } from '@/types/api'
import type { HrSlaCategory } from '@/types/models'

const slaKeys = {
  all: ['hr', 'staff', 'sla'] as const,
}

export function HrAdminSlaPage() {
  const toast = useToast()
  const queryClient = useQueryClient()
  const { data, isPending, isError, error, refetch, isRefetching } = useQuery({
    queryKey: slaKeys.all,
    queryFn: hrService.staff.sla,
  })

  const [drafts, setDrafts] = useState<Record<string, { hours: string; escalate: boolean }>>({})

  useEffect(() => {
    if (!data) return

    const next: Record<string, { hours: string; escalate: boolean }> = {}
    for (const category of data) {
      next[`c-${category.id}`] = {
        hours: String(category.target_hours ?? category.default_sla_hours ?? 48),
        escalate: category.escalate_to_manager,
      }
      for (const type of category.types) {
        next[`t-${type.id}`] = {
          hours: String(type.target_hours ?? type.sla_hours ?? category.target_hours ?? 48),
          escalate: type.escalate_to_manager,
        }
      }
    }
    setDrafts(next)
  }, [data])

  const saveCategory = useMutation({
    mutationFn: ({ id, hours, escalate }: { id: number; hours: number; escalate: boolean }) =>
      hrService.staff.updateCategorySla(id, { target_hours: hours, escalate_to_manager: escalate }),
    onSuccess: (catalogue) => {
      queryClient.setQueryData(slaKeys.all, catalogue)
      toast.success('SLA updated', 'Category target times were saved.')
    },
    onError: (cause: unknown) => {
      toast.error('Could not save SLA', cause instanceof ApiError ? cause.message : 'Please try again.')
    },
  })

  const saveType = useMutation({
    mutationFn: ({ id, hours, escalate }: { id: number; hours: number; escalate: boolean }) =>
      hrService.staff.updateTypeSla(id, { target_hours: hours, escalate_to_manager: escalate }),
    onSuccess: (catalogue) => {
      queryClient.setQueryData(slaKeys.all, catalogue)
      toast.success('SLA updated', 'Request type target times were saved.')
    },
    onError: (cause: unknown) => {
      toast.error('Could not save SLA', cause instanceof ApiError ? cause.message : 'Please try again.')
    },
  })

  if (isPending && !data) {
    return <LoadingPanel label="Loading SLA settings..." />
  }

  if (isError || !data) {
    return (
      <div className="space-y-6">
        <PageHeader title="SLA Settings" description="Target resolution times for HR request categories." />
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

  return (
    <div className="space-y-6">
      <PageHeader
        title="SLA Settings"
        description="Define target resolution hours. Overdue requests with escalation enabled are sent to the HR manager hourly."
      />

      <div className="space-y-4">
        {data.map((category) => (
          <CategorySlaCard
            key={category.id}
            category={category}
            drafts={drafts}
            setDrafts={setDrafts}
            saving={saveCategory.isPending || saveType.isPending}
            onSaveCategory={() => {
              const draft = drafts[`c-${category.id}`]
              const hours = Number(draft?.hours)
              if (!Number.isFinite(hours) || hours < 1) {
                toast.error('Invalid hours', 'Enter a target between 1 and 720 hours.')
                return
              }
              void saveCategory.mutateAsync({
                id: category.id,
                hours,
                escalate: Boolean(draft?.escalate),
              })
            }}
            onSaveType={(typeId) => {
              const draft = drafts[`t-${typeId}`]
              const hours = Number(draft?.hours)
              if (!Number.isFinite(hours) || hours < 1) {
                toast.error('Invalid hours', 'Enter a target between 1 and 720 hours.')
                return
              }
              void saveType.mutateAsync({
                id: typeId,
                hours,
                escalate: Boolean(draft?.escalate),
              })
            }}
          />
        ))}
      </div>
    </div>
  )
}

function CategorySlaCard({
  category,
  drafts,
  setDrafts,
  saving,
  onSaveCategory,
  onSaveType,
}: {
  category: HrSlaCategory
  drafts: Record<string, { hours: string; escalate: boolean }>
  setDrafts: React.Dispatch<React.SetStateAction<Record<string, { hours: string; escalate: boolean }>>>
  saving: boolean
  onSaveCategory: () => void
  onSaveType: (typeId: number) => void
}) {
  const categoryDraft = drafts[`c-${category.id}`] ?? {
    hours: String(category.target_hours ?? 48),
    escalate: category.escalate_to_manager,
  }

  return (
    <Card>
      <CardHeader
        title={category.name}
        description={
          category.is_confidential
            ? 'Confidential category — restricted to cleared HR staff.'
            : 'Applies to new requests in this category unless a request type overrides it.'
        }
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
                value={categoryDraft.hours}
                onChange={(event) =>
                  setDrafts((current) => ({
                    ...current,
                    [`c-${category.id}`]: { ...categoryDraft, hours: event.target.value },
                  }))
                }
              />
            </label>
            <label className="flex items-center gap-2 pt-6 text-sm text-surface-700">
              <input
                type="checkbox"
                checked={categoryDraft.escalate}
                onChange={(event) =>
                  setDrafts((current) => ({
                    ...current,
                    [`c-${category.id}`]: { ...categoryDraft, escalate: event.target.checked },
                  }))
                }
              />
              Escalate to HR manager when overdue
            </label>
          </div>
          <Button variant="secondary" disabled={saving} onClick={onSaveCategory}>
            Save category
          </Button>
        </div>

        <ul className="divide-y divide-surface-100 rounded-xl border border-surface-200">
          {category.types.map((type) => {
            const draft = drafts[`t-${type.id}`] ?? {
              hours: String(type.target_hours ?? 48),
              escalate: type.escalate_to_manager,
            }

            return (
              <li key={type.id} className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-end sm:justify-between">
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium text-surface-900">{type.name}</p>
                  {type.default_referral_department && (
                    <p className="text-xs text-surface-500">
                      Default referral: {type.default_referral_department.toUpperCase()}
                    </p>
                  )}
                  <div className="mt-3 grid gap-3 sm:grid-cols-2">
                    <label className="block text-xs font-medium text-surface-600">
                      Target hours
                      <Input
                        className="mt-1.5"
                        type="number"
                        min={1}
                        max={720}
                        value={draft.hours}
                        onChange={(event) =>
                          setDrafts((current) => ({
                            ...current,
                            [`t-${type.id}`]: { ...draft, hours: event.target.value },
                          }))
                        }
                      />
                    </label>
                    <label className="flex items-center gap-2 pt-6 text-sm text-surface-700">
                      <input
                        type="checkbox"
                        checked={draft.escalate}
                        onChange={(event) =>
                          setDrafts((current) => ({
                            ...current,
                            [`t-${type.id}`]: { ...draft, escalate: event.target.checked },
                          }))
                        }
                      />
                      Escalate when overdue
                    </label>
                  </div>
                </div>
                <Button variant="outline" disabled={saving} onClick={() => onSaveType(type.id)}>
                  Save
                </Button>
              </li>
            )
          })}
        </ul>
      </CardContent>
    </Card>
  )
}
