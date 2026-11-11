import { useEffect, useState, type FormEvent } from 'react'

import { ActiveStatusBadge } from '@/components/common/ActiveStatusBadge'
import { PageHeader } from '@/components/common/PageHeader'
import { Alert } from '@/components/ui/Alert'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Checkbox } from '@/components/ui/Checkbox'
import { Combobox } from '@/components/ui/Combobox'
import { DataTable, type DataTableColumn } from '@/components/ui/DataTable'
import { ErrorState } from '@/components/ui/ErrorState'
import { FormField } from '@/components/ui/FormField'
import { Input } from '@/components/ui/Input'
import { Modal } from '@/components/ui/Modal'
import { LoadingPanel } from '@/components/ui/Spinner'
import {
  useAdminCatalogue,
  useCreateCategory,
  useCreatePriority,
  useUpdateCategory,
  useUpdatePriority,
  useUpdateStatus,
} from '@/features/admin/useAdminCatalogue'
import { TicketPriorityBadge, TicketStatusBadge } from '@/features/tickets/TicketStatusBadge'
import { useToast } from '@/hooks/useToast'
import { ApiError } from '@/types/api'
import type { ColorToken, TicketCategory, TicketPriority, TicketStatus } from '@/types/models'
import { cn } from '@/utils/cn'

const TABS = [
  { id: 'categories', label: 'Categories' },
  { id: 'priorities', label: 'Priorities' },
  { id: 'statuses', label: 'Statuses' },
] as const

type TabId = (typeof TABS)[number]['id']

const COLOR_OPTIONS = [
  { value: 'neutral', label: 'Neutral' },
  { value: 'primary', label: 'Primary' },
  { value: 'success', label: 'Success' },
  { value: 'warning', label: 'Warning' },
  { value: 'danger', label: 'Danger' },
  { value: 'info', label: 'Info' },
]

export function AdminCategoriesPage() {
  const query = useAdminCatalogue()
  const [tab, setTab] = useState<TabId>('categories')
  const [category, setCategory] = useState<TicketCategory | null | undefined>(undefined)
  const [priority, setPriority] = useState<TicketPriority | null | undefined>(undefined)
  const [status, setStatus] = useState<TicketStatus | null>(null)

  const categories = query.data?.categories ?? []
  const priorities = query.data?.priorities ?? []
  const statuses = query.data?.statuses ?? []

  const categoryColumns: DataTableColumn<TicketCategory>[] = [
    {
      id: 'name',
      header: 'Category',
      cell: (row) => (
        <div>
          <p className="font-medium text-surface-900">{row.name}</p>
          {row.description && <p className="text-xs text-surface-500">{row.description}</p>}
        </div>
      ),
    },
    {
      id: 'priority',
      header: 'Default priority',
      cell: (row) => priorities.find((item) => item.id === row.default_priority_id)?.name ?? '—',
    },
    {
      id: 'sort',
      header: 'Order',
      cell: (row) => row.sort_order ?? 0,
    },
    {
      id: 'status',
      header: 'Status',
      cell: (row) => <ActiveStatusBadge isActive={row.is_active} />,
    },
    {
      id: 'actions',
      header: 'Actions',
      align: 'right',
      cell: (row) => (
        <Button size="sm" variant="outline" onClick={() => setCategory(row)}>
          Edit
        </Button>
      ),
    },
  ]

  const priorityColumns: DataTableColumn<TicketPriority>[] = [
    {
      id: 'name',
      header: 'Priority',
      cell: (row) => (
        <div className="flex items-center gap-2">
          <TicketPriorityBadge priority={row} />
          {row.is_default && <Badge tone="neutral">Default</Badge>}
        </div>
      ),
    },
    { id: 'level', header: 'Level', cell: (row) => row.level },
    {
      id: 'sla',
      header: 'SLA (minutes)',
      cell: (row) =>
        [row.response_minutes ? `Respond ${row.response_minutes}` : null, row.resolution_minutes ? `Resolve ${row.resolution_minutes}` : null]
          .filter(Boolean)
          .join(' · ') || '—',
      className: 'hidden md:table-cell',
    },
    { id: 'sort', header: 'Order', cell: (row) => row.sort_order ?? 0 },
    {
      id: 'status',
      header: 'Status',
      cell: (row) => <ActiveStatusBadge isActive={row.is_active !== false} />,
    },
    {
      id: 'actions',
      header: 'Actions',
      align: 'right',
      cell: (row) => (
        <Button size="sm" variant="outline" onClick={() => setPriority(row)}>
          Edit
        </Button>
      ),
    },
  ]

  const statusColumns: DataTableColumn<TicketStatus>[] = [
    {
      id: 'name',
      header: 'Status',
      cell: (row) => (
        <div className="flex items-center gap-2">
          <TicketStatusBadge status={row} />
          {row.is_default && <Badge tone="neutral">Default</Badge>}
        </div>
      ),
    },
    {
      id: 'slug',
      header: 'Workflow',
      cell: (row) => <span className="font-mono text-xs">{row.slug}</span>,
    },
    { id: 'sort', header: 'Order', cell: (row) => row.sort_order ?? 0 },
    {
      id: 'status',
      header: 'Active',
      cell: (row) => <ActiveStatusBadge isActive={row.is_active !== false} />,
    },
    {
      id: 'actions',
      header: 'Actions',
      align: 'right',
      cell: (row) => (
        <Button size="sm" variant="outline" onClick={() => setStatus(row)}>
          Edit
        </Button>
      ),
    },
  ]

  if (query.isPending) {
    return <LoadingPanel label="Loading catalogue…" />
  }

  if (query.isError) {
    return (
      <ErrorState
        title="Unable to load the catalogue"
        description={query.error instanceof ApiError ? query.error.message : 'Categories could not be loaded.'}
        onRetry={() => void query.refetch()}
        isRetrying={query.isRefetching}
      />
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Ticket catalogue"
        description="Categories, priorities and status labels. Workflow slugs stay fixed."
        actions={
          tab === 'categories' ? (
            <Button onClick={() => setCategory(null)}>New category</Button>
          ) : tab === 'priorities' ? (
            <Button onClick={() => setPriority(null)}>New priority</Button>
          ) : undefined
        }
      />

      <div className="flex gap-1 rounded-lg border border-surface-200 bg-panel p-1">
        {TABS.map((item) => (
          <button
            key={item.id}
            type="button"
            onClick={() => setTab(item.id)}
            className={cn(
              'flex-1 rounded-md px-3 py-2 text-sm font-medium',
              tab === item.id ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100',
            )}
          >
            {item.label}
          </button>
        ))}
      </div>

      <Card>
        {tab === 'categories' && (
          <DataTable columns={categoryColumns} rows={categories} rowKey={(row) => row.id} emptyTitle="No categories yet" />
        )}
        {tab === 'priorities' && (
          <DataTable columns={priorityColumns} rows={priorities} rowKey={(row) => row.id} emptyTitle="No priorities yet" />
        )}
        {tab === 'statuses' && (
          <DataTable
            columns={statusColumns}
            rows={statuses}
            rowKey={(row) => row.id}
            emptyTitle="No statuses"
            emptyDescription="Statuses are seeded with the workflow. You can rename, recolour or hide them."
          />
        )}
      </Card>

      <CategoryFormModal
        isOpen={category !== undefined}
        onClose={() => setCategory(undefined)}
        category={category ?? null}
        priorities={priorities}
      />
      <PriorityFormModal isOpen={priority !== undefined} onClose={() => setPriority(undefined)} priority={priority ?? null} />
      <StatusFormModal isOpen={status !== null} onClose={() => setStatus(null)} status={status} />
    </div>
  )
}

function CategoryFormModal({
  isOpen,
  onClose,
  category,
  priorities,
}: {
  isOpen: boolean
  onClose: () => void
  category: TicketCategory | null
  priorities: TicketPriority[]
}) {
  const toast = useToast()
  const create = useCreateCategory()
  const update = useUpdateCategory()
  const [values, setValues] = useState({
    name: '',
    description: '',
    default_priority_id: '',
    is_active: true,
    sort_order: '0',
  })
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  useEffect(() => {
    if (!isOpen) return
    setValues({
      name: category?.name ?? '',
      description: category?.description ?? '',
      default_priority_id: category?.default_priority_id ? String(category.default_priority_id) : '',
      is_active: category?.is_active ?? true,
      sort_order: String(category?.sort_order ?? 0),
    })
    setError(null)
    setFieldErrors({})
  }, [isOpen, category])

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault()
    setError(null)
    setFieldErrors({})
    const payload = {
      name: values.name.trim(),
      description: values.description.trim() || null,
      default_priority_id: values.default_priority_id ? Number(values.default_priority_id) : null,
      is_active: values.is_active,
      sort_order: Number(values.sort_order) || 0,
    }

    try {
      if (category) {
        await update.mutateAsync({ id: category.id, payload })
        toast.success('Category updated', payload.name)
      } else {
        await create.mutateAsync(payload)
        toast.success('Category created', payload.name)
      }
      onClose()
    } catch (caught) {
      if (caught instanceof ApiError) {
        if (caught.isValidationError) setFieldErrors(caught.fieldErrors)
        setError(caught.message)
      } else {
        setError('Unable to save the category.')
      }
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={category ? 'Edit category' : 'New category'}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="category-form" isLoading={create.isPending || update.isPending}>
            Save
          </Button>
        </>
      }
    >
      <form id="category-form" className="space-y-4" onSubmit={handleSubmit}>
        {error && (
          <Alert tone="danger" title="Save failed">
            {error}
          </Alert>
        )}
        <FormField label="Name" required error={fieldErrors.name}>
          {({ id, hasError }) => (
            <Input
              id={id}
              hasError={hasError}
              value={values.name}
              onChange={(event) => setValues((current) => ({ ...current, name: event.target.value }))}
            />
          )}
        </FormField>
        <FormField label="Description" error={fieldErrors.description}>
          {({ id, hasError }) => (
            <Input
              id={id}
              hasError={hasError}
              value={values.description}
              onChange={(event) => setValues((current) => ({ ...current, description: event.target.value }))}
            />
          )}
        </FormField>
        <FormField label="Default priority" error={fieldErrors.default_priority_id}>
          {({ id }) => (
            <Combobox
              id={id}
              value={values.default_priority_id}
              onChange={(value) => setValues((current) => ({ ...current, default_priority_id: value }))}
              placeholder="None"
              options={priorities.map((item) => ({ value: String(item.id), label: item.name }))}
            />
          )}
        </FormField>
        <FormField label="Sort order" error={fieldErrors.sort_order}>
          {({ id, hasError }) => (
            <Input
              id={id}
              type="number"
              min={0}
              hasError={hasError}
              value={values.sort_order}
              onChange={(event) => setValues((current) => ({ ...current, sort_order: event.target.value }))}
            />
          )}
        </FormField>
        <Checkbox
          label="Active"
          checked={values.is_active}
          onChange={(event) => setValues((current) => ({ ...current, is_active: event.target.checked }))}
        />
      </form>
    </Modal>
  )
}

function PriorityFormModal({
  isOpen,
  onClose,
  priority,
}: {
  isOpen: boolean
  onClose: () => void
  priority: TicketPriority | null
}) {
  const toast = useToast()
  const create = useCreatePriority()
  const update = useUpdatePriority()
  const [values, setValues] = useState({
    name: '',
    level: '10',
    color_token: 'neutral',
    response_minutes: '',
    resolution_minutes: '',
    is_active: true,
    sort_order: '0',
  })
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  useEffect(() => {
    if (!isOpen) return
    setValues({
      name: priority?.name ?? '',
      level: String(priority?.level ?? 10),
      color_token: priority?.color_token ?? 'neutral',
      response_minutes: priority?.response_minutes ? String(priority.response_minutes) : '',
      resolution_minutes: priority?.resolution_minutes ? String(priority.resolution_minutes) : '',
      is_active: priority?.is_active ?? true,
      sort_order: String(priority?.sort_order ?? 0),
    })
    setError(null)
    setFieldErrors({})
  }, [isOpen, priority])

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault()
    setError(null)
    setFieldErrors({})
    const payload = {
      name: values.name.trim(),
      level: Number(values.level) || 1,
      color_token: values.color_token,
      response_minutes: values.response_minutes ? Number(values.response_minutes) : null,
      resolution_minutes: values.resolution_minutes ? Number(values.resolution_minutes) : null,
      is_active: values.is_active,
      sort_order: Number(values.sort_order) || 0,
    }

    try {
      if (priority) {
        await update.mutateAsync({ id: priority.id, payload })
        toast.success('Priority updated', payload.name)
      } else {
        await create.mutateAsync(payload)
        toast.success('Priority created', payload.name)
      }
      onClose()
    } catch (caught) {
      if (caught instanceof ApiError) {
        if (caught.isValidationError) setFieldErrors(caught.fieldErrors)
        setError(caught.message)
      } else {
        setError('Unable to save the priority.')
      }
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={priority ? 'Edit priority' : 'New priority'}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="priority-form" isLoading={create.isPending || update.isPending}>
            Save
          </Button>
        </>
      }
    >
      <form id="priority-form" className="space-y-4" onSubmit={handleSubmit}>
        {error && (
          <Alert tone="danger" title="Save failed">
            {error}
          </Alert>
        )}
        <FormField label="Name" required error={fieldErrors.name}>
          {({ id, hasError }) => (
            <Input
              id={id}
              hasError={hasError}
              value={values.name}
              onChange={(event) => setValues((current) => ({ ...current, name: event.target.value }))}
            />
          )}
        </FormField>
        <div className="grid gap-4 sm:grid-cols-2">
          <FormField label="Level" required error={fieldErrors.level}>
            {({ id, hasError }) => (
              <Input
                id={id}
                type="number"
                min={1}
                hasError={hasError}
                value={values.level}
                onChange={(event) => setValues((current) => ({ ...current, level: event.target.value }))}
              />
            )}
          </FormField>
          <FormField label="Colour" required error={fieldErrors.color_token}>
            {({ id }) => (
              <Combobox
                id={id}
                value={values.color_token}
                allowEmpty={false}
                onChange={(value) => setValues((current) => ({ ...current, color_token: value }))}
                options={COLOR_OPTIONS}
              />
            )}
          </FormField>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <FormField label="Response minutes" error={fieldErrors.response_minutes}>
            {({ id, hasError }) => (
              <Input
                id={id}
                type="number"
                min={1}
                hasError={hasError}
                value={values.response_minutes}
                onChange={(event) => setValues((current) => ({ ...current, response_minutes: event.target.value }))}
              />
            )}
          </FormField>
          <FormField label="Resolution minutes" error={fieldErrors.resolution_minutes}>
            {({ id, hasError }) => (
              <Input
                id={id}
                type="number"
                min={1}
                hasError={hasError}
                value={values.resolution_minutes}
                onChange={(event) => setValues((current) => ({ ...current, resolution_minutes: event.target.value }))}
              />
            )}
          </FormField>
        </div>
        <FormField label="Sort order" error={fieldErrors.sort_order}>
          {({ id, hasError }) => (
            <Input
              id={id}
              type="number"
              min={0}
              hasError={hasError}
              value={values.sort_order}
              onChange={(event) => setValues((current) => ({ ...current, sort_order: event.target.value }))}
            />
          )}
        </FormField>
        <Checkbox
          label={priority?.is_default ? 'Active (default priority cannot be deactivated)' : 'Active'}
          checked={values.is_active}
          disabled={Boolean(priority?.is_default)}
          onChange={(event) => setValues((current) => ({ ...current, is_active: event.target.checked }))}
        />
      </form>
    </Modal>
  )
}

function StatusFormModal({
  isOpen,
  onClose,
  status,
}: {
  isOpen: boolean
  onClose: () => void
  status: TicketStatus | null
}) {
  const toast = useToast()
  const update = useUpdateStatus()
  const [values, setValues] = useState({
    name: '',
    color_token: 'neutral' as ColorToken,
    is_active: true,
    sort_order: '0',
  })
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  useEffect(() => {
    if (!isOpen || !status) return
    setValues({
      name: status.name,
      color_token: status.color_token,
      is_active: status.is_active ?? true,
      sort_order: String(status.sort_order ?? 0),
    })
    setError(null)
    setFieldErrors({})
  }, [isOpen, status])

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault()
    if (!status) return
    setError(null)
    setFieldErrors({})

    try {
      await update.mutateAsync({
        id: status.id,
        payload: {
          name: values.name.trim(),
          color_token: values.color_token,
          is_active: values.is_active,
          sort_order: Number(values.sort_order) || 0,
        },
      })
      toast.success('Status updated', values.name)
      onClose()
    } catch (caught) {
      if (caught instanceof ApiError) {
        if (caught.isValidationError) setFieldErrors(caught.fieldErrors)
        setError(caught.message)
      } else {
        setError('Unable to save the status.')
      }
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Edit status"
      description="Name, colour and visibility only. The workflow slug stays the same."
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="status-form" isLoading={update.isPending}>
            Save
          </Button>
        </>
      }
    >
      <form id="status-form" className="space-y-4" onSubmit={handleSubmit}>
        {error && (
          <Alert tone="danger" title="Save failed">
            {error}
          </Alert>
        )}
        <FormField label="Name" required error={fieldErrors.name}>
          {({ id, hasError }) => (
            <Input
              id={id}
              hasError={hasError}
              value={values.name}
              onChange={(event) => setValues((current) => ({ ...current, name: event.target.value }))}
            />
          )}
        </FormField>
        <FormField label="Colour" required error={fieldErrors.color_token}>
          {({ id }) => (
              <Combobox
                id={id}
                value={values.color_token}
                allowEmpty={false}
                onChange={(value) => setValues((current) => ({ ...current, color_token: value as ColorToken }))}
                options={COLOR_OPTIONS}
              />
          )}
        </FormField>
        <FormField label="Sort order" error={fieldErrors.sort_order}>
          {({ id, hasError }) => (
            <Input
              id={id}
              type="number"
              min={0}
              hasError={hasError}
              value={values.sort_order}
              onChange={(event) => setValues((current) => ({ ...current, sort_order: event.target.value }))}
            />
          )}
        </FormField>
        <Checkbox
          label={status?.is_default ? 'Active (default status cannot be deactivated)' : 'Active'}
          checked={values.is_active}
          disabled={Boolean(status?.is_default)}
          onChange={(event) => setValues((current) => ({ ...current, is_active: event.target.checked }))}
        />
      </form>
    </Modal>
  )
}
