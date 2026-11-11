import { Mail, Phone } from 'lucide-react'
import { useNavigate } from 'react-router-dom'

import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { usePublicLookups } from '@/features/system/useSystemStatus'
import { paths } from '@/routes/paths'

export function ContactItModal({
  isOpen,
  onClose,
}: {
  isOpen: boolean
  onClose: () => void
}) {
  const navigate = useNavigate()
  const { data: lookups } = usePublicLookups()
  const email = lookups?.support_email || 'itsupport@company.local'
  const phone = lookups?.support_phone
  const hours = lookups?.support_hours || 'Weekdays 08:00–17:00'

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Contact IT"
      description="Reach the service desk during business hours, or raise a ticket so the request is tracked."
      size="sm"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Close
          </Button>
          <Button
            onClick={() => {
              onClose()
              void navigate(paths.tickets.create)
            }}
          >
            Create IT ticket
          </Button>
        </>
      }
    >
      <div className="space-y-3 text-sm text-surface-600">
        {email && (
          <p className="flex items-center gap-2">
            <Mail className="size-4 text-surface-400" />
            <a href={`mailto:${email}`} className="font-medium text-primary-600 hover:text-primary-700">
              {email}
            </a>
          </p>
        )}
        <p className="flex items-center gap-2">
          <Phone className="size-4 text-surface-400" />
          <span>
            {phone ? `${phone} · ` : ''}
            {hours}
          </span>
        </p>
      </div>
    </Modal>
  )
}
