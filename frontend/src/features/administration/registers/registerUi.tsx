import { Search } from 'lucide-react'
import type { ReactNode } from 'react'

import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Combobox } from '@/components/ui/Combobox'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { FilterField } from '@/components/ui/FilterToolbar'
import { Input } from '@/components/ui/Input'
import { humanize, type SelectOption } from '@/features/administration/registers/registerForm'
import { useRegisterMutation } from '@/features/administration/registers/useAdmRegisters'
import { useToast } from '@/hooks/useToast'
import { ApiError } from '@/types/api'
import type { ColorToken } from '@/types/models'
import { formatDate } from '@/utils/format'

export function SearchFilter({
  value,
  onChange,
  placeholder,
}: {
  value: string
  onChange: (value: string) => void
  placeholder: string
}) {
  return (
    <FilterField label="Search">
      {(id) => (
        <Input
          id={id}
          value={value}
          onChange={(event) => onChange(event.target.value)}
          placeholder={placeholder}
          leadingIcon={<Search className="size-4" />}
        />
      )}
    </FilterField>
  )
}

export function ComboFilter({
  label,
  value,
  onChange,
  options,
  placeholder,
}: {
  label: string
  value: string
  onChange: (value: string) => void
  options: SelectOption[]
  placeholder: string
}) {
  return (
    <FilterField label={label}>
      {(id) => <Combobox id={id} value={value} onChange={onChange} options={options} placeholder={placeholder} />}
    </FilterField>
  )
}

const STATUS_TONES: Record<string, ColorToken> = {
  active: 'success',
  available: 'success',
  renewed: 'success',
  completed: 'info',
  scheduled: 'primary',
  in_progress: 'warning',
  pending: 'warning',
  on_leave: 'warning',
  maintenance: 'warning',
  suspended: 'warning',
  not_due: 'neutral',
  inactive: 'neutral',
  retired: 'neutral',
  cancelled: 'neutral',
  expired: 'danger',
  lost: 'danger',
}

export function StatusBadge({ status }: { status: string }) {
  return (
    <Badge tone={STATUS_TONES[status] ?? 'neutral'} withDot>
      {humanize(status)}
    </Badge>
  )
}

/** A date with a coloured hint when it has passed or is close. */
export function ExpiryDate({ date, daysToExpiry }: { date: string | null; daysToExpiry?: number | null }) {
  if (!date) return <span className="text-surface-400">&mdash;</span>

  const days = daysToExpiry ?? Math.floor((new Date(date).getTime() - Date.now()) / 86_400_000)

  return (
    <div>
      <span className="text-sm text-surface-800">{formatDate(date)}</span>
      {days < 0 ? (
        <p className="text-xs font-medium text-danger-600">Expired {Math.abs(days)}d ago</p>
      ) : days <= 30 ? (
        <p className="text-xs font-medium text-warning-600">{days === 0 ? 'Expires today' : `In ${days} days`}</p>
      ) : null}
    </div>
  )
}

export function RowActions({ children }: { children: ReactNode }) {
  return <div className="flex flex-wrap justify-end gap-2">{children}</div>
}

export function RowButton({
  children,
  onClick,
  variant = 'outline',
}: {
  children: ReactNode
  onClick: () => void
  variant?: 'outline' | 'danger' | 'ghost'
}) {
  return (
    <Button
      size="sm"
      variant={variant}
      onClick={(event) => {
        event.stopPropagation()
        onClick()
      }}
    >
      {children}
    </Button>
  )
}

/** Confirmation for deleting a register row; surfaces API refusals (e.g. open allocations). */
export function DeleteRegisterDialog({
  target,
  title,
  description,
  remove,
  onClose,
}: {
  target: { id: number; name: string } | null
  title: string
  description: string
  remove: (id: number) => Promise<unknown>
  onClose: () => void
}) {
  const toast = useToast()
  const mutation = useRegisterMutation(remove)

  return (
    <ConfirmDialog
      isOpen={target !== null}
      onClose={onClose}
      title={title}
      description={description}
      confirmLabel="Delete"
      tone="danger"
      isConfirming={mutation.isPending}
      onConfirm={() => {
        if (!target) return

        mutation.mutate(target.id, {
          onSuccess: () => {
            toast.success('Deleted', target.name)
            onClose()
          },
          onError: (cause) => {
            toast.error('Could not delete', cause instanceof ApiError ? cause.message : 'Please try again.')
            onClose()
          },
        })
      }}
    />
  )
}
