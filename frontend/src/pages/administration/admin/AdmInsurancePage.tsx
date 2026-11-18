import { Download, History, Plus } from 'lucide-react'
import { useState } from 'react'

import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { DataTable, type DataTableColumn } from '@/components/ui/DataTable'
import { FilterToolbar } from '@/components/ui/FilterToolbar'
import { Pagination } from '@/components/ui/Pagination'
import { HistoryModal } from '@/features/administration/registers/HistoryModal'
import { InsuranceFormModal } from '@/features/administration/registers/InsuranceFormModal'
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
import { useToast } from '@/hooks/useToast'
import { admRegistersService, type RegisterListParams } from '@/services/admRegistersService'
import { ApiError } from '@/types/api'
import type { InsurancePolicy } from '@/types/admRegisters'
import { formatDate } from '@/utils/format'

const EXPIRY_OPTIONS = [
  { value: 'expired', label: 'Already expired' },
  { value: '30', label: 'Expiring in 30 days' },
  { value: '60', label: 'Expiring in 60 days' },
  { value: '90', label: 'Expiring in 90 days' },
]

export function AdmInsurancePage() {
  const toast = useToast()
  const { hasPermission } = useAuth()
  const canManage = hasPermission('adm.registers.manage')
  const { data: lookups } = useRegisterLookups()
  const state = useRegisterFilters({ status: '', renewal_status: '', expiry: '', company_name: '' })

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<InsurancePolicy | null>(null)
  const [historyId, setHistoryId] = useState<number | null>(null)
  const [deleting, setDeleting] = useState<InsurancePolicy | null>(null)

  const { expiry, ...rest } = state.filters
  const params: RegisterListParams = {
    ...rest,
    search: state.search,
    page: state.page,
    per_page: state.perPage,
    ...(expiry === 'expired' ? { expired: true } : expiry ? { expiring_within: expiry } : {}),
  }

  const query = useRegisterList('insurance', admRegistersService.insurance.list, params)

  function edit(policy: InsurancePolicy | null) {
    setEditing(policy)
    setFormOpen(true)
  }

  async function download(policy: InsurancePolicy) {
    try {
      await admRegistersService.insurance.downloadDocument(policy.id)
    } catch (cause) {
      toast.error('Download failed', cause instanceof ApiError ? cause.message : 'Please try again.')
    }
  }

  const columns: DataTableColumn<InsurancePolicy>[] = [
    {
      id: 'policy',
      header: 'Policy',
      cell: (policy) => (
        <div>
          <p className="font-medium text-surface-900">{policy.policy_number}</p>
          <p className="text-xs text-surface-500">{policy.insurance_type}</p>
        </div>
      ),
    },
    {
      id: 'provider',
      header: 'Provider',
      cell: (policy) => policy.provider,
      className: 'hidden lg:table-cell',
    },
    {
      id: 'asset',
      header: 'Insured asset',
      cell: (policy) => (
        <div>
          <p>{policy.insured_asset || '\u2014'}</p>
          {policy.vehicle_registration && (
            <p className="font-mono text-xs text-surface-500">{policy.vehicle_registration}</p>
          )}
        </div>
      ),
      className: 'hidden md:table-cell',
    },
    {
      id: 'expiry',
      header: 'Expiry',
      cell: (policy) => <ExpiryDate date={policy.expiry_date} daysToExpiry={policy.days_to_expiry} />,
    },
    {
      id: 'officer',
      header: 'Officer',
      cell: (policy) => policy.responsible_officer?.full_name ?? '\u2014',
      className: 'hidden xl:table-cell',
    },
    {
      id: 'renewal',
      header: 'Renewal',
      cell: (policy) => <StatusBadge status={policy.renewal_status} />,
      className: 'hidden md:table-cell',
    },
    { id: 'status', header: 'Status', cell: (policy) => <StatusBadge status={policy.status} /> },
    {
      id: 'actions',
      header: 'Actions',
      align: 'right',
      cell: (policy) => (
        <RowActions>
          {policy.has_document && (
            <RowButton onClick={() => void download(policy)}>
              <Download className="mr-1 size-3.5" />
              Document
            </RowButton>
          )}
          <RowButton onClick={() => setHistoryId(policy.id)}>
            <History className="mr-1 size-3.5" />
            History
          </RowButton>
          {canManage && <RowButton onClick={() => edit(policy)}>Edit</RowButton>}
          {canManage && (
            <RowButton variant="danger" onClick={() => setDeleting(policy)}>
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
      : 'Insurance policies could not be loaded.'
    : null

  return (
    <div className="space-y-6">
      <PageHeader
        title="Insurance Register"
        description="Company insurance policies, expiry dates and renewal progress. Reminders go out automatically before expiry."
        actions={
          canManage ? (
            <Button leadingIcon={<Plus className="size-4" />} onClick={() => edit(null)}>
              Add policy
            </Button>
          ) : undefined
        }
      />

      <Card>
        <FilterToolbar className="md:grid-cols-2 xl:grid-cols-5">
          <SearchFilter
            value={state.searchDraft}
            onChange={state.setSearchDraft}
            placeholder="Policy no., provider, asset, vehicle"
          />
          <ComboFilter
            label="Status"
            value={state.filters.status}
            onChange={(value) => state.setFilter('status', value)}
            options={statusOptions(lookups?.enums.insurance_status)}
            placeholder="All statuses"
          />
          <ComboFilter
            label="Renewal"
            value={state.filters.renewal_status}
            onChange={(value) => state.setFilter('renewal_status', value)}
            options={statusOptions(lookups?.enums.renewal_status)}
            placeholder="Any renewal state"
          />
          <ComboFilter
            label="Expiry"
            value={state.filters.expiry}
            onChange={(value) => state.setFilter('expiry', value)}
            options={EXPIRY_OPTIONS}
            placeholder="Any expiry"
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
          rowKey={(policy) => policy.id}
          isLoading={query.isPending}
          errorMessage={errorMessage}
          onRetry={() => void query.refetch()}
          emptyTitle="No insurance policies match these filters"
          emptyDescription={canManage ? 'Add a policy or clear the filters.' : 'Clear the filters to see more.'}
          mobileCard={(policy) => (
            <div className="rounded-xl border border-surface-200 bg-panel p-4 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-sm font-medium text-surface-900">{policy.policy_number}</p>
                  <p className="text-xs text-surface-500">
                    {policy.insurance_type} &middot; {policy.provider}
                  </p>
                </div>
                <StatusBadge status={policy.status} />
              </div>
              <p className="mt-2 text-xs text-surface-500">Expires {formatDate(policy.expiry_date)}</p>
              <div className="mt-3 flex flex-wrap gap-2">
                <RowButton onClick={() => setHistoryId(policy.id)}>History</RowButton>
                {canManage && <RowButton onClick={() => edit(policy)}>Edit</RowButton>}
              </div>
            </div>
          )}
        />

        {query.data?.meta && <Pagination meta={query.data.meta} onPageChange={state.setPage} />}
      </Card>

      <InsuranceFormModal
        isOpen={formOpen}
        policy={editing}
        onClose={() => {
          setFormOpen(false)
          setEditing(null)
        }}
      />
      <HistoryModal resource="insurance" id={historyId} title="Policy history" onClose={() => setHistoryId(null)} />
      <DeleteRegisterDialog
        target={deleting ? { id: deleting.id, name: deleting.policy_number } : null}
        title="Delete this policy?"
        description="The policy is removed from the register. Its history is kept for audit."
        remove={admRegistersService.insurance.remove}
        onClose={() => setDeleting(null)}
      />
    </div>
  )
}
