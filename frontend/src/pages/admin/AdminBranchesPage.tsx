import { Plus, Search } from 'lucide-react'
import { useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'

import { ActiveStatusBadge } from '@/components/common/ActiveStatusBadge'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { DataTable, type DataTableColumn } from '@/components/ui/DataTable'
import { Combobox } from '@/components/ui/Combobox'
import { FilterField, FilterToolbar } from '@/components/ui/FilterToolbar'
import { Input } from '@/components/ui/Input'
import { Pagination } from '@/components/ui/Pagination'
import { BranchFormModal } from '@/features/admin/BranchFormModal'
import { useActivateAdminBranch, useAdminBranches, useDeactivateAdminBranch } from '@/features/admin/useAdminDirectory'
import { useDebouncedSearch } from '@/hooks/useDebouncedValue'
import { useToast } from '@/hooks/useToast'
import { ApiError } from '@/types/api'
import type { Branch } from '@/types/models'

export function AdminBranchesPage() {
  const toast = useToast()
  const [params, setParams] = useSearchParams()
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Branch | null>(null)
  const [pendingToggle, setPendingToggle] = useState<Branch | null>(null)

  const filters = useMemo(
    () => ({
      search: params.get('search') ?? '',
      status: (params.get('status') ?? '') as 'active' | 'inactive' | '',
      page: Number(params.get('page') ?? '1') || 1,
      per_page: 15,
    }),
    [params],
  )

  const query = useAdminBranches(filters)
  const activate = useActivateAdminBranch(pendingToggle ? String(pendingToggle.id) : '0')
  const deactivate = useDeactivateAdminBranch(pendingToggle ? String(pendingToggle.id) : '0')

  const [searchDraft, setSearchDraft] = useDebouncedSearch(filters.search, (value) =>
    updateFilter('search', value),
  )

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    if (key !== 'page') next.delete('page')
    setParams(next)
  }

  const columns: DataTableColumn<Branch>[] = [
    {
      id: 'name',
      header: 'Branch Name',
      cell: (branch) => <span className="font-medium text-surface-900">{branch.name}</span>,
    },
    {
      id: 'code',
      header: 'Branch Code',
      cell: (branch) => <span className="font-mono text-xs">{branch.code}</span>,
    },
    {
      id: 'address',
      header: 'Address',
      cell: (branch) => branch.address || branch.city || '—',
      className: 'hidden md:table-cell',
    },
    {
      id: 'phone',
      header: 'Contact Number',
      cell: (branch) => branch.phone || '—',
      className: 'hidden lg:table-cell',
    },
    {
      id: 'status',
      header: 'Status',
      cell: (branch) => (
        <ActiveStatusBadge isActive={branch.is_active} />
      ),
    },
    {
      id: 'actions',
      header: 'Actions',
      align: 'right',
      cell: (branch) => (
        <div className="flex justify-end gap-2">
          <Button
            size="sm"
            variant="outline"
            onClick={(event) => {
              event.stopPropagation()
              setEditing(branch)
              setFormOpen(true)
            }}
          >
            Edit
          </Button>
          <Button
            size="sm"
            variant={branch.is_active ? 'danger' : 'success'}
            onClick={(event) => {
              event.stopPropagation()
              setPendingToggle(branch)
            }}
          >
            {branch.is_active ? 'Deactivate' : 'Activate'}
          </Button>
        </div>
      ),
    },
  ]

  const rows = query.data?.data ?? []
  const errorMessage = query.isError
    ? query.error instanceof ApiError
      ? query.error.message
      : 'Branches could not be loaded.'
    : null

  return (
    <div className="space-y-6">
      <PageHeader
        title="Branches"
        description="Company locations used for tickets and employee registration. Only active branches appear on the registration form."
        actions={
          <Button
            leadingIcon={<Plus className="size-4" />}
            onClick={() => {
              setEditing(null)
              setFormOpen(true)
            }}
          >
            Create branch
          </Button>
        }
      />

      <Card>
        <FilterToolbar className="md:grid-cols-2 xl:grid-cols-2">
          <FilterField label="Search">
            {(id) => (
              <Input
                id={id}
                value={searchDraft}
                onChange={(event) => setSearchDraft(event.target.value)}
                placeholder="Name or code"
                leadingIcon={<Search className="size-4" />}
              />
            )}
          </FilterField>
          <FilterField label="Status">
            {(id) => (
              <Combobox
                id={id}
                value={filters.status}
                onChange={(value) => updateFilter('status', value)}
                placeholder="All statuses"
                options={[
                  { value: 'active', label: 'Active' },
                  { value: 'inactive', label: 'Inactive' },
                ]}
              />
            )}
          </FilterField>
        </FilterToolbar>

        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(branch) => branch.id}
          isLoading={query.isPending}
          errorMessage={errorMessage}
          onRetry={() => void query.refetch()}
          emptyTitle="No branches match these filters"
          emptyDescription="Create a branch or clear the search."
          mobileCard={(branch) => (
            <div className="rounded-xl border border-surface-200 bg-panel p-4 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-sm font-medium text-surface-900">{branch.name}</p>
                  <p className="mt-0.5 font-mono text-xs text-surface-500">{branch.code}</p>
                </div>
                <ActiveStatusBadge isActive={branch.is_active} />
              </div>
              <p className="mt-2 text-xs text-surface-500">{branch.address || branch.city || '—'}</p>
              <div className="mt-3 flex flex-wrap gap-2">
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => {
                    setEditing(branch)
                    setFormOpen(true)
                  }}
                >
                  Edit
                </Button>
                <Button
                  size="sm"
                  variant={branch.is_active ? 'danger' : 'success'}
                  onClick={() => setPendingToggle(branch)}
                >
                  {branch.is_active ? 'Deactivate' : 'Activate'}
                </Button>
              </div>
            </div>
          )}
        />

        {query.data?.meta && (
          <Pagination meta={query.data.meta} onPageChange={(page) => updateFilter('page', String(page))} />
        )}
      </Card>

      <BranchFormModal
        isOpen={formOpen}
        branch={editing}
        onClose={() => {
          setFormOpen(false)
          setEditing(null)
        }}
      />

      <ConfirmDialog
        isOpen={pendingToggle !== null}
        onClose={() => setPendingToggle(null)}
        title={pendingToggle?.is_active ? `Deactivate ${pendingToggle.name}?` : `Activate ${pendingToggle?.name}?`}
        description={
          pendingToggle?.is_active
            ? 'Employees will no longer be able to choose this branch when they register.'
            : 'This branch will appear again on the registration form.'
        }
        confirmLabel={pendingToggle?.is_active ? 'Deactivate' : 'Activate'}
        tone={pendingToggle?.is_active ? 'danger' : 'primary'}
        isConfirming={activate.isPending || deactivate.isPending}
        onConfirm={() => {
          if (!pendingToggle) return
          const action = pendingToggle.is_active ? deactivate.mutateAsync() : activate.mutateAsync()
          void action.then(() => {
            toast.success(
              pendingToggle.is_active ? 'Branch deactivated' : 'Branch activated',
              pendingToggle.name,
            )
            setPendingToggle(null)
          })
        }}
      />
    </div>
  )
}
