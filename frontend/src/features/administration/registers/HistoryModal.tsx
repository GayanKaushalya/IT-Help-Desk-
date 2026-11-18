import { Button } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/EmptyState'
import { ErrorState } from '@/components/ui/ErrorState'
import { Modal } from '@/components/ui/Modal'
import { LoadingPanel } from '@/components/ui/Spinner'
import { humanize } from '@/features/administration/registers/registerForm'
import { useRegisterHistory } from '@/features/administration/registers/useAdmRegisters'
import { admRegistersService } from '@/services/admRegistersService'
import { formatDateTime } from '@/utils/format'

/** Change history of an insurance policy or fuel card. */
export function HistoryModal({
  resource,
  id,
  title,
  onClose,
}: {
  resource: 'insurance' | 'fuel-cards'
  id: number | null
  title: string
  onClose: () => void
}) {
  const fetcher = resource === 'insurance' ? admRegistersService.insurance.history : admRegistersService.fuelCards.history
  const { data, isPending, isError, refetch } = useRegisterHistory(resource, id, fetcher)

  return (
    <Modal
      isOpen={id !== null}
      onClose={onClose}
      title={title}
      description="Most recent changes first."
      footer={
        <Button variant="outline" onClick={onClose}>
          Close
        </Button>
      }
    >
      {isPending ? (
        <LoadingPanel label="Loading history..." />
      ) : isError ? (
        <ErrorState title="Unable to load history" description="Please try again." onRetry={() => void refetch()} />
      ) : data.length === 0 ? (
        <EmptyState title="No history yet" description="Changes will appear here." />
      ) : (
        <ol className="space-y-4">
          {data.map((entry) => (
            <li key={entry.id} className="border-l-2 border-primary-200 pl-4">
              <p className="text-sm font-medium text-surface-900">{humanize(entry.event)}</p>
              {entry.notes && <p className="mt-0.5 text-sm text-surface-600">{entry.notes}</p>}
              <p className="mt-0.5 text-xs text-surface-500">
                {formatDateTime(entry.changed_at)}
                {entry.changed_by ? ` \u00b7 ${entry.changed_by.full_name}` : ' \u00b7 System'}
              </p>
            </li>
          ))}
        </ol>
      )}
    </Modal>
  )
}
