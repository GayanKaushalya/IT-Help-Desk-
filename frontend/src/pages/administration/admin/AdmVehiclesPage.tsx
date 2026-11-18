import { CalendarPlus, Plus } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'

import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { DataTable, type DataTableColumn } from '@/components/ui/DataTable'
import { FilterField, FilterToolbar } from '@/components/ui/FilterToolbar'
import { Input } from '@/components/ui/Input'
import { Pagination } from '@/components/ui/Pagination'
import { AllocationFormModal } from '@/features/administration/registers/AllocationFormModal'
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
import { useRegisterList, useRegisterLookups, useRegisterMutation } from '@/features/administration/registers/useAdmRegisters'
import { useRegisterFilters } from '@/features/administration/registers/useRegisterFilters'
import { VehicleFormModal } from '@/features/administration/registers/VehicleDriverFormModals'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { admRegistersService } from '@/services/admRegistersService'
import { ApiError } from '@/types/api'
import type { Vehicle, VehicleAllocation } from '@/types/admRegisters'
import { cn } from '@/utils/cn'
import { formatDateTime } from '@/utils/format'

type Tab = 'vehicles' | 'allocations'

export function AdmVehiclesPage() {
  const [params, setParams] = useSearchParams()
  const tab: Tab = params.get('tab') === 'allocations' ? 'allocations' : 'vehicles'
  const { hasPermission } = useAuth()
  const canManage = hasPermission('adm.registers.manage')
  const [formOpen, setFormOpen] = useState(false)
  const [allocationOpen, setAllocationOpen] = useState(false)
  const [editingVehicle, setEditingVehicle] = useState<Vehicle | null>(null)
  const [editingAllocation, setEditingAllocation] = useState<VehicleAllocation | null>(null)

  function selectTab(next: Tab) {
    setParams(next === 'vehicles' ? {} : { tab: next })
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Vehicles"
        description="The company vehicle register and vehicle bookings. A vehicle or driver cannot be double-booked."
        actions={
          canManage ? (
            tab === 'vehicles' ? (
              <Button
                leadingIcon={<Plus className="size-4" />}
                onClick={() => {
                  setEditingVehicle(null)
                  setFormOpen(true)
                }}
              >
                Add vehicle
              </Button>
            ) : (
              <Button
                leadingIcon={<CalendarPlus className="size-4" />}
                onClick={() => {
                  setEditingAllocation(null)
                  setAllocationOpen(true)
                }}
              >
                Allocate vehicle
              </Button>
            )
          ) : undefined
        }
      />

      <div role="tablist" aria-label="Vehicle views" className="flex gap-1 border-b border-surface-200">
        {(['vehicles', 'allocations'] as const).map((value) => (
          <button
            key={value}
            type="button"
            role="tab"
            aria-selected={tab === value}
            onClick={() => selectTab(value)}
            className={cn(
              '-mb-px border-b-2 px-4 py-2 text-sm font-medium transition-colors',
              tab === value
                ? 'border-primary-600 text-primary-700'
                : 'border-transparent text-surface-500 hover:text-surface-800',
            )}
          >
            {value === 'vehicles' ? 'Vehicles' : 'Allocations'}
          </button>
        ))}
      </div>

      {tab === 'vehicles' ? (
        <VehiclesSection
          canManage={canManage}
          onEdit={(vehicle) => {
            setEditingVehicle(vehicle)
            setFormOpen(true)
          }}
        />
      ) : (
        <AllocationsSection
          canManage={canManage}
          onEdit={(allocation) => {
            setEditingAllocation(allocation)
            setAllocationOpen(true)
          }}
        />
      )}

      <VehicleFormModal
        isOpen={formOpen}
        vehicle={editingVehicle}
        onClose={() => {
          setFormOpen(false)
          setEditingVehicle(null)
        }}
      />
      <AllocationFormModal
        isOpen={allocationOpen}
        allocation={editingAllocation}
        onClose={() => {
          setAllocationOpen(false)
          setEditingAllocation(null)
        }}
      />
    </div>
  )
}

/* ------------------------------ vehicles ------------------------------ */

function VehiclesSection({ canManage, onEdit }: { canManage: boolean; onEdit: (vehicle: Vehicle) => void }) {
  const { data: lookups } = useRegisterLookups()
  const state = useRegisterFilters({ status: '', company_name: '' })
  const [deleting, setDeleting] = useState<Vehicle | null>(null)

  const query = useRegisterList('vehicles', admRegistersService.vehicles.list, {
    ...state.filters,
    search: state.search,
    page: state.page,
    per_page: state.perPage,
  })

  const columns: DataTableColumn<Vehicle>[] = [
    {
      id: 'registration',
      header: 'Vehicle',
      cell: (vehicle) => (
        <div>
          <p className="font-mono text-sm font-medium text-surface-900">{vehicle.registration_number}</p>
          <p className="text-xs text-surface-500">
            {[vehicle.make, vehicle.model, vehicle.year].filter(Boolean).join(' ') || '\u2014'}
          </p>
        </div>
      ),
    },
    {
      id: 'type',
      header: 'Type',
      cell: (vehicle) => [vehicle.vehicle_type, vehicle.fuel_type].filter(Boolean).join(' \u00b7 ') || '\u2014',
      className: 'hidden md:table-cell',
    },
    {
      id: 'driver',
      header: 'Default driver',
      cell: (vehicle) => vehicle.driver?.full_name ?? '\u2014',
      className: 'hidden lg:table-cell',
    },
    {
      id: 'insurance',
      header: 'Insurance expiry',
      cell: (vehicle) => <ExpiryDate date={vehicle.insurance_policy?.expiry_date ?? null} />,
      className: 'hidden xl:table-cell',
    },
    {
      id: 'revenue',
      header: 'Revenue licence',
      cell: (vehicle) => <ExpiryDate date={vehicle.revenue_license_expiry} />,
      className: 'hidden xl:table-cell',
    },
    { id: 'status', header: 'Status', cell: (vehicle) => <StatusBadge status={vehicle.status} /> },
    ...(canManage
      ? [
          {
            id: 'actions',
            header: 'Actions',
            align: 'right' as const,
            cell: (vehicle: Vehicle) => (
              <RowActions>
                <RowButton onClick={() => onEdit(vehicle)}>Edit</RowButton>
                <RowButton variant="danger" onClick={() => setDeleting(vehicle)}>
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
      : 'Vehicles could not be loaded.'
    : null

  return (
    <>
      <Card>
        <FilterToolbar className="md:grid-cols-3 xl:grid-cols-3">
          <SearchFilter value={state.searchDraft} onChange={state.setSearchDraft} placeholder="Registration, make, model" />
          <ComboFilter
            label="Status"
            value={state.filters.status}
            onChange={(value) => state.setFilter('status', value)}
            options={statusOptions(lookups?.enums.vehicle_status)}
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
          rowKey={(vehicle) => vehicle.id}
          isLoading={query.isPending}
          errorMessage={errorMessage}
          onRetry={() => void query.refetch()}
          emptyTitle="No vehicles match these filters"
          emptyDescription={canManage ? 'Add a vehicle or clear the filters.' : 'Clear the filters to see more.'}
          mobileCard={(vehicle) => (
            <div className="rounded-xl border border-surface-200 bg-panel p-4 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="font-mono text-sm font-medium text-surface-900">{vehicle.registration_number}</p>
                  <p className="text-xs text-surface-500">{[vehicle.make, vehicle.model].filter(Boolean).join(' ') || '\u2014'}</p>
                </div>
                <StatusBadge status={vehicle.status} />
              </div>
              {canManage && (
                <div className="mt-3 flex gap-2">
                  <RowButton onClick={() => onEdit(vehicle)}>Edit</RowButton>
                </div>
              )}
            </div>
          )}
        />

        {query.data?.meta && <Pagination meta={query.data.meta} onPageChange={state.setPage} />}
      </Card>

      <DeleteRegisterDialog
        target={deleting ? { id: deleting.id, name: deleting.registration_number } : null}
        title="Delete this vehicle?"
        description="The vehicle is removed from the register. Vehicles with current or upcoming allocations cannot be deleted."
        remove={admRegistersService.vehicles.remove}
        onClose={() => setDeleting(null)}
      />
    </>
  )
}

/* ----------------------------- allocations ---------------------------- */

function AllocationsSection({
  canManage,
  onEdit,
}: {
  canManage: boolean
  onEdit: (allocation: VehicleAllocation) => void
}) {
  const toast = useToast()
  const { data: lookups } = useRegisterLookups()
  const state = useRegisterFilters({ status: '', vehicle_id: '', driver_id: '', date_from: '', date_to: '' })
  const [cancelling, setCancelling] = useState<VehicleAllocation | null>(null)
  const cancel = useRegisterMutation((id: number) => admRegistersService.allocations.cancel(id))

  const query = useRegisterList('allocations', admRegistersService.allocations.list, {
    ...state.filters,
    page: state.page,
    per_page: state.perPage,
  })

  const columns: DataTableColumn<VehicleAllocation>[] = [
    {
      id: 'vehicle',
      header: 'Vehicle',
      cell: (row) => (
        <span className="font-mono text-sm font-medium text-surface-900">{row.vehicle?.registration_number ?? '\u2014'}</span>
      ),
    },
    { id: 'driver', header: 'Driver', cell: (row) => row.driver?.full_name ?? '\u2014' },
    {
      id: 'window',
      header: 'When',
      cell: (row) => (
        <div className="text-sm">
          <p>{formatDateTime(row.start_at)}</p>
          <p className="text-xs text-surface-500">to {formatDateTime(row.end_at)}</p>
        </div>
      ),
    },
    {
      id: 'purpose',
      header: 'Purpose',
      cell: (row) => (
        <div className="text-sm">
          <p>{row.purpose || '\u2014'}</p>
          {row.destination && <p className="text-xs text-surface-500">{row.destination}</p>}
        </div>
      ),
      className: 'hidden lg:table-cell',
    },
    {
      id: 'request',
      header: 'Request',
      cell: (row) => <span className="font-mono text-xs">{row.request?.reference ?? '\u2014'}</span>,
      className: 'hidden xl:table-cell',
    },
    { id: 'status', header: 'Status', cell: (row) => <StatusBadge status={row.status} /> },
    ...(canManage
      ? [
          {
            id: 'actions',
            header: 'Actions',
            align: 'right' as const,
            cell: (row: VehicleAllocation) =>
              row.status === 'cancelled' || row.status === 'completed' ? null : (
                <RowActions>
                  <RowButton onClick={() => onEdit(row)}>Edit</RowButton>
                  <RowButton variant="danger" onClick={() => setCancelling(row)}>
                    Cancel
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
      : 'Allocations could not be loaded.'
    : null

  return (
    <>
      <Card>
        <FilterToolbar className="md:grid-cols-3 xl:grid-cols-5">
          <ComboFilter
            label="Vehicle"
            value={state.filters.vehicle_id}
            onChange={(value) => state.setFilter('vehicle_id', value)}
            options={(lookups?.vehicles ?? []).map((vehicle) => ({ value: String(vehicle.id), label: vehicle.label }))}
            placeholder="Any vehicle"
          />
          <ComboFilter
            label="Driver"
            value={state.filters.driver_id}
            onChange={(value) => state.setFilter('driver_id', value)}
            options={(lookups?.drivers ?? []).map((driver) => ({ value: String(driver.id), label: driver.full_name }))}
            placeholder="Any driver"
          />
          <ComboFilter
            label="Status"
            value={state.filters.status}
            onChange={(value) => state.setFilter('status', value)}
            options={statusOptions(lookups?.enums.allocation_status)}
            placeholder="All statuses"
          />
          <FilterField label="From">
            {(id) => (
              <Input id={id} type="date" value={state.filters.date_from} onChange={(event) => state.setFilter('date_from', event.target.value)} />
            )}
          </FilterField>
          <FilterField label="To">
            {(id) => (
              <Input id={id} type="date" value={state.filters.date_to} onChange={(event) => state.setFilter('date_to', event.target.value)} />
            )}
          </FilterField>
        </FilterToolbar>

        <DataTable
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={query.isPending}
          errorMessage={errorMessage}
          onRetry={() => void query.refetch()}
          emptyTitle="No allocations in this range"
          emptyDescription={canManage ? 'Allocate a vehicle or widen the date range.' : 'Widen the date range.'}
          mobileCard={(row) => (
            <div className="rounded-xl border border-surface-200 bg-panel p-4 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="font-mono text-sm font-medium text-surface-900">{row.vehicle?.registration_number ?? '\u2014'}</p>
                  <p className="text-xs text-surface-500">{row.driver?.full_name ?? 'No driver'}</p>
                </div>
                <StatusBadge status={row.status} />
              </div>
              <p className="mt-2 text-xs text-surface-500">
                {formatDateTime(row.start_at)} &rarr; {formatDateTime(row.end_at)}
              </p>
              {canManage && row.status !== 'cancelled' && row.status !== 'completed' && (
                <div className="mt-3 flex gap-2">
                  <RowButton onClick={() => onEdit(row)}>Edit</RowButton>
                  <RowButton variant="danger" onClick={() => setCancelling(row)}>
                    Cancel
                  </RowButton>
                </div>
              )}
            </div>
          )}
        />

        {query.data?.meta && <Pagination meta={query.data.meta} onPageChange={state.setPage} />}
      </Card>

      <ConfirmDialog
        isOpen={cancelling !== null}
        onClose={() => setCancelling(null)}
        title="Cancel this allocation?"
        description="The vehicle and driver become available for this time window again."
        confirmLabel="Cancel allocation"
        tone="danger"
        isConfirming={cancel.isPending}
        onConfirm={() => {
          if (!cancelling) return

          cancel.mutate(cancelling.id, {
            onSuccess: () => {
              toast.success('Allocation cancelled')
              setCancelling(null)
            },
            onError: (cause) => {
              toast.error('Could not cancel', cause instanceof ApiError ? cause.message : 'Please try again.')
              setCancelling(null)
            },
          })
        }}
      />
    </>
  )
}
