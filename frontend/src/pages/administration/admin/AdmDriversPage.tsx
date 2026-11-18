import { Plus } from 'lucide-react'
import { useState } from 'react'

import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { DataTable, type DataTableColumn } from '@/components/ui/DataTable'
import { FilterToolbar } from '@/components/ui/FilterToolbar'
import { Pagination } from '@/components/ui/Pagination'
import { statusOptions } from '@/features/administration/registers/registerForm'
import {
  ComboFilter,
  DeleteRegisterDialog,
  ExpiryDate,
  RowActions,
  RowButton,
  SearchFilter,
  StatusBadge,
} from '@/features/administration/registers/registerUi'
import { useRegisterList, useRegisterLookups } from '@/features/administration/registers/useAdmRegisters'
import { useRegisterFilters } from '@/features/administration/registers/useRegisterFilters'
import { DriverFormModal } from '@/features/administration/registers/VehicleDriverFormModals'
import { useAuth } from '@/hooks/useAuth'
import { admRegistersService } from '@/services/admRegistersService'
import { ApiError } from '@/types/api'
import type { Driver } from '@/types/admRegisters'

export function AdmDriversPage() {
  const { hasPermission } = useAuth()
  const canManage = hasPermission('adm.registers.manage')
  const { data: lookups } = useRegisterLookups()
  const state = useRegisterFilters({ status: '', company_name: '' })

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Driver | null>(null)
  const [deleting, setDeleting] = useState<Driver | null>(null)

  const query = useRegisterList('drivers', admRegistersService.drivers.list, {
    ...state.filters,
    search: state.search,
    page: state.page,
    per_page: state.perPage,
  })

  function edit(driver: Driver | null) {
    setEditing(driver)
    setFormOpen(true)
  }

  const columns: DataTableColumn<Driver>[] = [
    {
      id: 'name',
      header: 'Driver',
      cell: (driver) => (
        <div>
          <p className="font-medium text-surface-900">{driver.full_name}</p>
          {driver.employee_id && <p className="text-xs text-surface-500">ID {driver.employee_id}</p>}
        </div>
      ),
    },
    {
      id: 'contact',
      header: 'Contact',
      cell: (driver) => (
        <div className="text-sm">
          <p>{driver.phone || '\u2014'}</p>
          {driver.email && <p className="text-xs text-surface-500">{driver.email}</p>}
        </div>
      ),
      className: 'hidden md:table-cell',
    },
    {
      id: 'licence',
      header: 'Licence',
      cell: (driver) => (
        <div>
          <p className="font-mono text-xs">
            {driver.license_number || '\u2014'}
            {driver.license_class ? ` \u00b7 ${driver.license_class}` : ''}
          </p>
        </div>
      ),
      className: 'hidden lg:table-cell',
    },
    {
      id: 'expiry',
      header: 'Licence expiry',
      cell: (driver) => <ExpiryDate date={driver.license_expiry_date} />,
    },
    {
      id: 'company',
      header: 'Company',
      cell: (driver) => driver.company_name || '\u2014',
      className: 'hidden xl:table-cell',
    },
    { id: 'status', header: 'Status', cell: (driver) => <StatusBadge status={driver.status} /> },
    ...(canManage
      ? [
          {
            id: 'actions',
            header: 'Actions',
            align: 'right' as const,
            cell: (driver: Driver) => (
              <RowActions>
                <RowButton onClick={() => edit(driver)}>Edit</RowButton>
                <RowButton variant="danger" onClick={() => setDeleting(driver)}>
                  Delete
                </RowButton>
              </RowActions>
            ),
          },
        ]
      : []),
  ]

  const errorMessage = query.isError
    ? query.error instanceof ApiError
      ? query.error.message
      : 'Drivers could not be loaded.'
    : null

  return (
    <div className="space-y-6">
      <PageHeader
        title="Drivers"
        description="Driver records and licence expiry. Only active drivers with a valid licence can be allocated to a vehicle."
        actions={
          canManage ? (
            <Button leadingIcon={<Plus className="size-4" />} onClick={() => edit(null)}>
              Add driver
            </Button>
          ) : undefined
        }
      />

      <Card>
        <FilterToolbar className="md:grid-cols-3 xl:grid-cols-3">
          <SearchFilter value={state.searchDraft} onChange={state.setSearchDraft} placeholder="Name, employee ID, licence, phone" />
          <ComboFilter
            label="Status"
            value={state.filters.status}
            onChange={(value) => state.setFilter('status', value)}
            options={statusOptions(lookups?.enums.driver_status)}
            placeholder="All statuses"
          />
          <ComboFilter
            label="Company"
            value={state.filters.company_name}
            onChange={(value) => state.setFilter('company_name', value)}
            options={(lookups?.companies ?? []).map((company) => ({ value: company, label: company }))}
            placeholder="All companies"
          />
        </FilterToolbar>

        <DataTable
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(driver) => driver.id}
          isLoading={query.isPending}
          errorMessage={errorMessage}
          onRetry={() => void query.refetch()}
          emptyTitle="No drivers match these filters"
          emptyDescription={canManage ? 'Add a driver or clear the filters.' : 'Clear the filters to see more.'}
          mobileCard={(driver) => (
            <div className="rounded-xl border border-surface-200 bg-panel p-4 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-sm font-medium text-surface-900">{driver.full_name}</p>
                  <p className="text-xs text-surface-500">{driver.phone || driver.email || '\u2014'}</p>
                </div>
                <StatusBadge status={driver.status} />
              </div>
              <div className="mt-2">
                <ExpiryDate date={driver.license_expiry_date} />
              </div>
              {canManage && (
                <div className="mt-3 flex gap-2">
                  <RowButton onClick={() => edit(driver)}>Edit</RowButton>
                </div>
              )}
            </div>
          )}
        />

        {query.data?.meta && <Pagination meta={query.data.meta} onPageChange={state.setPage} />}
      </Card>

      <DriverFormModal
        isOpen={formOpen}
        driver={editing}
        onClose={() => {
          setFormOpen(false)
          setEditing(null)
        }}
      />
      <DeleteRegisterDialog
        target={deleting ? { id: deleting.id, name: deleting.full_name } : null}
        title="Delete this driver?"
        description="Drivers with current or upcoming allocations cannot be deleted; cancel those first."
        remove={admRegistersService.drivers.remove}
        onClose={() => setDeleting(null)}
      />
    </div>
  )
}
