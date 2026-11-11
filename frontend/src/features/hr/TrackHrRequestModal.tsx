import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'

import { Button } from '@/components/ui/Button'
import { Form } from '@/components/ui/Form'
import { FormField } from '@/components/ui/FormField'
import { Input } from '@/components/ui/Input'
import { Modal } from '@/components/ui/Modal'
import { paths } from '@/routes/paths'

export function TrackHrRequestModal({
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
      setError('Enter an HR request reference.')
      return
    }

    onClose()
    setReference('')
    setError(null)
    void navigate(`${paths.hr.track}?reference=${encodeURIComponent(value)}`)
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Track an HR request"
      description="Enter the reference from your confirmation, for example HR-2026-000001."
      size="sm"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="track-hr-request-form">
            Track request
          </Button>
        </>
      }
    >
      <Form id="track-hr-request-form" onSubmit={handleSubmit}>
        <FormField label="Request reference" required error={error ?? undefined}>
          {({ id, hasError }) => (
            <Input
              id={id}
              name="hr_reference"
              autoFocus
              hasError={hasError}
              value={reference}
              onChange={(event) => {
                setReference(event.target.value)
                setError(null)
              }}
              placeholder="HR-2026-000001"
            />
          )}
        </FormField>
      </Form>
    </Modal>
  )
}
