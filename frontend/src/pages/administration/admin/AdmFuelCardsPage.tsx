import { History, Plus } from 'lucide-react'
import { useState } from 'react'

import { PageHeader } from '@/components/common/PageHeader'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { DataTable, type DataTableColumn } from '@/components/ui/DataTable'
import { FilterToolbar } from '@/components/ui/FilterToolbar'
import { Pagination } from '@/components/ui/Pagination'
import { FuelCardFormModal } from '@/features/administration/registers/FuelCardFormModal'
import { HistoryModal } from '@/features/administration/registers/HistoryModal'
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
import { useAuth } from '@/hooks/useAuth'
import { admRegistersService } from '@/services/admRegistersService'
import { ApiError } from '@/types/api'
import type { FuelCard } from '@/types/admRegisters'

export function AdmFuelCardsPage() {
  const { hasPermission } = useAuth()
  const canManage = hasPermission('adm.registers.manage')
  const { data: lookups } = useRegisterLookups()
  const state = useRegisterFilters({ status: '', vehicle_id: '', driver_id: '', company_name: '' })

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<FuelCard | null>(null)
  const [historyId, setHistoryId] = useState<number | null>(null)
  const [deleting, setDeleting] = useState<FuelCard | null>(null)

  const query = useRegisterList('fuel-cards', admRegistersService.fuelCards.list, {
    ...state.filters,
    search: state.search,
    page: state.page,
    per_page: state.perPage,
  })

  function edit(card: FuelCard | null) {
    setEditing(card)
    setFormOpen(true)
  }

  const columns: DataTableColumn<FuelCard>[] = [
    {
      id: 'card',
      header: 'Card',
      cell: (card) => (
        <div>
          <p className="font-mono text-sm font-medium text-surface-900">{card.card_number}</p>
          <p className="font-mono text-xs text-surface-500">{card.masked_card_ref ?? 'No reference'}</p>
        </div>
      ),
    },
    {
      id: 'provider',
      header: 'Provider',
      cell: (card) => card.provider || '\u2014',
      className: 'hidden lg:table-cell',
    },
    {
      id: 'assigned',
      header: 'Assigned to',
      cell: (card) => (
        <div className="text-sm">
          <p>{card.vehicle?.registration_number ?? card.driver?.full_name ?? card.assigned_user?.full_name ?? '\u2014'}</p>
          {card.vehicle && card.driver && <p className="text-xs text-surface-500">{card.driver.full_name}</p>}
        </div>
      ),
      className: 'hidden md:table-cell',
    },
    {
      id: 'limit',
      header: 'Monthly limit',
      cell: (card) => (card.monthly_limit ? Number(card.monthly_limit).toLocaleString() : '\u2014'),
      className: 'hidden xl:table-cell',
    },
    { id: 'expiry', header: 'Expiry', cell: (card) => <ExpiryDate date={card.expiry_date} /> },
    { id: 'status', header: 'Status', cell: (card) => <StatusBadge status={card.status} /> },
    {
      id: 'actions',
      header: 'Actions',
      align: 'right',
      cell: (card) => (
        <RowActions>
          <RowButton onClick={() => setHistoryId(card.id)}>
            <History className="mr-1 size-3.5" />
            History
          </RowButton>
          {canManage && <RowButton onClick={() => edit(card)}>Edit</RowButton>}
          {canManage && (
            <RowButton variant="danger" onClick={() => setDeleting(card)}>
              Delete
            </RowButton>
          )}
        </RowActions>
      ),
    },
  ]

  const errorMessage = query.isError
    ? query.error instanceof ApiError
      ? query.error.message
      : 'Fuel cards could not be loaded.'
    : null

  return (
    <div className="space-y-6">
      <PageHeader
        title="Fuel Card Register"
        description="Fuel cards, who holds them and their limits."
        actions={
          canManage ? (
            <Button leadingIcon={<Plus className="size-4" />} onClick={() => edit(null)}>
              Add fuel card
            </Button>
          ) : undefined
        }
      />

      <Alert tone="info" title="PINs are never stored">
        Cards show a masked reference only. Card PINs and security codes must not be entered or shared through the help desk.
      </Alert>

      <Card>
        <FilterToolbar className="md:grid-cols-2 xl:grid-cols-5">
          <SearchFilter value={state.searchDraft} onChange={state.setSearchDraft} placeholder="Card ID, provider, vehicle" />
          <ComboFilter
            label="Status"
            value={state.filters.status}
            onChange={(value) => state.setFilter('status', value)}
            options={statusOptions(lookups?.enums.fuel_card_status)}
            placeholder="All statuses"
          />
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
          rowKey={(card) => card.id}
          isLoading={query.isPending}
          errorMessage={errorMessage}
          onRetry={() => void query.refetch()}
          emptyTitle="No fuel cards match these filters"
          emptyDescription={canManage ? 'Add a fuel card or clear the filters.' : 'Clear the filters to see more.'}
          mobileCard={(card) => (
            <div className="rounded-xl border border-surface-200 bg-panel p-4 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="font-mono text-sm font-medium text-surface-900">{card.card_number}</p>
                  <p className="font-mono text-xs text-surface-500">{card.masked_card_ref ?? 'No reference'}</p>
                </div>
                <StatusBadge status={card.status} />
              </div>
              <div className="mt-3 flex gap-2">
                <RowButton onClick={() => setHistoryId(card.id)}>History</RowButton>
                {canManage && <RowButton onClick={() => edit(card)}>Edit</RowButton>}
              </div>
            </div>
          )}
        />

        {query.data?.meta && <Pagination meta={query.data.meta} onPageChange={state.setPage} />}
      </Card>

      <FuelCardFormModal
        isOpen={formOpen}
        card={editing}
        onClose={() => {
          setFormOpen(false)
          setEditing(null)
        }}
      />
      <HistoryModal resource="fuel-cards" id={historyId} title="Fuel card history" onClose={() => setHistoryId(null)} />
      <DeleteRegisterDialog
        target={deleting ? { id: deleting.id, name: deleting.card_number } : null}
        title="Delete this fuel card?"
        description="The card is removed from the register."
        remove={admRegistersService.fuelCards.remove}
        onClose={() => setDeleting(null)}
      />
    </div>
  )
}
