import { Plus, Search } from 'lucide-react'
import { useMemo, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'

import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { DataTable, type DataTableColumn } from '@/components/ui/DataTable'
import { Combobox } from '@/components/ui/Combobox'
import { FilterField, FilterToolbar } from '@/components/ui/FilterToolbar'
import { Input } from '@/components/ui/Input'
import { Pagination } from '@/components/ui/Pagination'
import { AccountStatusBadge } from '@/features/admin/accountStatus'
import { TemporaryPasswordDialog } from '@/features/admin/TemporaryPasswordDialog'
import { UserFormModal } from '@/features/admin/UserFormModal'
import { useAdminLookups, useAdminUsers } from '@/features/admin/useAdminDirectory'
import { useAuth } from '@/hooks/useAuth'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'
import type { AccountStatus, User } from '@/types/models'
import { useDebouncedSearch } from '@/hooks/useDebouncedValue'
import { formatDate } from '@/utils/format'

export function AdminUsersPage() {
  const navigate = useNavigate()
  const { hasPermission } = useAuth()
  const [params, setParams] = useSearchParams()
  const { data: lookups } = useAdminLookups()
  const [createOpen, setCreateOpen] = useState(false)
  const [issued, setIssued] = useState<{ username: string; password: string } | null>(null)

  const filters = useMemo(
    () => ({
      search: params.get('search') ?? '',
      branch_id: params.get('branch_id') ?? '',
      role_id: params.get('role_id') ?? '',
      account_status: (params.get('account_status') ?? '') as AccountStatus | '',
      page: Number(params.get('page') ?? '1') || 1,
      per_page: 15,
    }),
    [params],
  )

  const query = useAdminUsers(filters)
  const canCreate = hasPermission('users.create')

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

  const columns: DataTableColumn<User>[] = [
    {
      id: 'name',
      header: 'Name',
      cell: (user) => <span className="font-medium text-surface-900">{user.full_name}</span>,
      className: 'min-w-[12rem]',
    },
    {
      id: 'employee_id',
      header: 'Employee ID',
      cell: (user) => <span className="font-mono text-xs">{user.employee_id}</span>,
    },
    {
      id: 'epf_number',
      header: 'EPF Number',
      cell: (user) => <span className="font-mono text-xs">{user.epf_number}</span>,
      className: 'hidden lg:table-cell',
    },
    {
      id: 'username',
      header: 'Username',
      cell: (user) => <span className="font-mono text-xs">{user.username}</span>,
    },
    {
      id: 'branch',
      header: 'Branch',
      cell: (user) => user.branch?.name ?? '—',
      className: 'hidden md:table-cell',
    },
    {
      id: 'role',
      header: 'Role',
      cell: (user) => user.role?.name ?? '—',
    },
    {
      id: 'status',
      header: 'Status',
      cell: (user) => (
        <AccountStatusBadge status={user.account_status} isActive={user.is_active} isApproved={user.is_approved} />
      ),
    },
    {
      id: 'created',
      header: 'Created',
      cell: (user) => formatDate(user.created_at),
      className: 'hidden xl:table-cell',
    },
    {
      id: 'actions',
      header: 'Actions',
      align: 'right',
      cell: (user) => (
        <Button
          size="sm"
          variant="outline"
          onClick={(event) => {
            event.stopPropagation()
            void navigate(paths.admin.userDetail(user.id))
          }}
        >
          Open
        </Button>
      ),
    },
  ]

  const rows = query.data?.data ?? []
  const errorMessage = query.isError
    ? query.error instanceof ApiError
      ? query.error.message
      : 'Users could not be loaded.'
    : null

  return (
    <div className="space-y-6">
      <PageHeader
        title="Users"
        description="Directory of employees and IT staff. Passwords are never displayed."
        actions={
          canCreate ? (
            <Button leadingIcon={<Plus className="size-4" />} onClick={() => setCreateOpen(true)}>
              Create user
            </Button>
          ) : undefined
        }
      />

      <Card>
        <FilterToolbar>
          <FilterField label="Search">
            {(id) => (
              <Input
                id={id}
                value={searchDraft}
                onChange={(event) => setSearchDraft(event.target.value)}
                placeholder="Name, username or ID"
                leadingIcon={<Search className="size-4" />}
              />
            )}
          </FilterField>
          <FilterField label="Branch">
            {(id) => (
              <Combobox
                id={id}
                value={filters.branch_id}
                onChange={(value) => updateFilter('branch_id', value)}
                placeholder="All branches"
                options={lookups?.branches.map((branch) => ({ value: String(branch.id), label: branch.name })) ?? []}
              />
            )}
          </FilterField>
          <FilterField label="Role">
            {(id) => (
              <Combobox
                id={id}
                value={filters.role_id}
                onChange={(value) => updateFilter('role_id', value)}
                placeholder="All roles"
                options={lookups?.roles.map((role) => ({ value: String(role.id), label: role.name })) ?? []}
              />
            )}
          </FilterField>
          <FilterField label="Account status">
            {(id) => (
              <Combobox
                id={id}
                value={filters.account_status}
                onChange={(value) => updateFilter('account_status', value)}
                placeholder="All account statuses"
                options={[
                  { value: 'active', label: 'Active' },
                  { value: 'inactive', label: 'Inactive' },
                  { value: 'pending', label: 'Pending' },
                ]}
              />
            )}
          </FilterField>
        </FilterToolbar>

        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(user) => user.id}
          isLoading={query.isPending}
          errorMessage={errorMessage}
          onRetry={() => void query.refetch()}
          onRowClick={(user) => void navigate(paths.admin.userDetail(user.id))}
          emptyTitle="No users match these filters"
          emptyDescription="Try clearing a filter or create a new account."
          mobileCard={(user) => (
            <button
              type="button"
              onClick={() => void navigate(paths.admin.userDetail(user.id))}
              className="w-full rounded-xl border border-surface-200 bg-panel p-4 text-left shadow-sm"
            >
              <div className="flex items-start justify-between gap-3">
                <p className="text-sm font-medium text-surface-900">{user.full_name}</p>
                <AccountStatusBadge status={user.account_status} isActive={user.is_active} isApproved={user.is_approved} />
              </div>
              <p className="mt-1 font-mono text-xs text-surface-500">
                {user.username} · {user.employee_id}
              </p>
              <p className="mt-2 text-xs text-surface-500">
                {user.role?.name ?? '—'} · {user.branch?.name ?? '—'}
              </p>
            </button>
          )}
        />

        {query.data?.meta && (
          <Pagination meta={query.data.meta} onPageChange={(page) => updateFilter('page', String(page))} />
        )}
      </Card>

      <UserFormModal
        isOpen={createOpen}
        onClose={() => setCreateOpen(false)}
        onIssuedPassword={(username, password) => setIssued({ username, password })}
      />
      <TemporaryPasswordDialog
        isOpen={issued !== null}
        username={issued?.username ?? ''}
        password={issued?.password ?? ''}
        onClose={() => setIssued(null)}
      />
    </div>
  )
}
