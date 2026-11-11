import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'

export function TemporaryPasswordDialog({
  isOpen,
  onClose,
  username,
  password,
}: {
  isOpen: boolean
  onClose: () => void
  username: string
  password: string
}) {
  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Give this password to the user"
      description={`${username} can sign in with this temporary password. It will not be shown again.`}
      footer={<Button onClick={onClose}>I have given the user the password</Button>}
    >
      <div className="rounded-xl border border-primary-200 bg-primary-50 px-4 py-6 text-center">
        <p className="text-xs font-semibold tracking-[0.2em] text-primary-700 uppercase">Temporary password</p>
        <p className="mt-3 font-mono text-xl font-semibold tracking-wide break-all text-surface-900">{password}</p>
        <p className="mt-3 text-sm text-surface-600">Ask the user to sign in and change this password as soon as they can.</p>
      </div>
    </Modal>
  )
}
