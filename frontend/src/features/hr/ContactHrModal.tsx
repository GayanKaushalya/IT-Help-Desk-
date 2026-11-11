import { Mail, Phone } from 'lucide-react'
import { useNavigate } from 'react-router-dom'

import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { useHrLookups } from '@/features/hr/useHr'
import { paths } from '@/routes/paths'

export function ContactHrModal({
  isOpen,
  onClose,
}: {
  isOpen: boolean
  onClose: () => void
}) {
  const navigate = useNavigate()
  const { data: lookups } = useHrLookups()
  const email = lookups?.support_email || 'hr@company.local'
  const phone = lookups?.support_phone
  const hours = lookups?.support_hours || 'Weekdays 08:00–17:00'

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Contact HR"
      description="Reach the HR help desk during business hours, or submit a request so the case is tracked."
      size="sm"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Close
          </Button>
          <Button
            onClick={() => {
              onClose()
              void navigate(paths.hr.submit)
            }}
          >
            Submit HR request
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
