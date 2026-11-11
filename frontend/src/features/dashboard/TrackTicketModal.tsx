import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'

import { Button } from '@/components/ui/Button'
import { Form } from '@/components/ui/Form'
import { FormField } from '@/components/ui/FormField'
import { Input } from '@/components/ui/Input'
import { Modal } from '@/components/ui/Modal'
import { paths } from '@/routes/paths'

export function TrackTicketModal({
  isOpen,
  onClose,
}: {
  isOpen: boolean
  onClose: () => void
}) {
  const navigate = useNavigate()
  const [reference, setReference] = useState('')
  const [error, setError] = useState<string | null>(null)

  function handleSubmit(_event: FormEvent<HTMLFormElement>): void {
    const value = reference.trim()

    if (!value) {
      setError('Enter a ticket ID or reference.')
      return
    }

    onClose()
    setReference('')
    setError(null)
    navigate(paths.tickets.detail(value))
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Track a ticket"
      description="Enter the ticket ID or reference from your confirmation, for example TKT-2026-100001."
      size="sm"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="track-ticket-form">
            Open ticket
          </Button>
        </>
      }
    >
      <Form id="track-ticket-form" onSubmit={handleSubmit}>
        <FormField label="Ticket ID or reference" required error={error ?? undefined}>
          {({ id, hasError }) => (
            <Input
              id={id}
              name="ticket_reference"
              autoFocus
              hasError={hasError}
              value={reference}
              onChange={(event) => {
                setReference(event.target.value)
                setError(null)
              }}
              placeholder="TKT-2026-100001"
            />
          )}
        </FormField>
      </Form>
    </Modal>
  )
}
