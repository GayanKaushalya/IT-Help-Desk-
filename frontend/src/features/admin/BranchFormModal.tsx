import { useEffect, useState, type FormEvent } from 'react'

import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { FormField } from '@/components/ui/FormField'
import { Input } from '@/components/ui/Input'
import { Modal } from '@/components/ui/Modal'
import { useCreateAdminBranch, useUpdateAdminBranch } from '@/features/admin/useAdminDirectory'
import { useToast } from '@/hooks/useToast'
import { ApiError } from '@/types/api'
import type { Branch } from '@/types/models'

interface BranchFormState {
  name: string
  code: string
  address: string
  city: string
  phone: string
}

const EMPTY: BranchFormState = {
  name: '',
  code: '',
  address: '',
  city: '',
  phone: '',
}

export function BranchFormModal({
  isOpen,
  onClose,
  branch,
}: {
  isOpen: boolean
  onClose: () => void
  branch?: Branch | null
}) {
  const toast = useToast()
  const createBranch = useCreateAdminBranch()
  const updateBranch = useUpdateAdminBranch(branch ? String(branch.id) : '0')
  const [values, setValues] = useState<BranchFormState>(EMPTY)
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  const editing = Boolean(branch)

  useEffect(() => {
    if (!isOpen) return
    setValues(
      branch
        ? {
            name: branch.name,
            code: branch.code,
            address: branch.address ?? '',
            city: branch.city ?? '',
            phone: branch.phone ?? '',
          }
        : EMPTY,
    )
    setError(null)
    setFieldErrors({})
  }, [isOpen, branch])

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault()
    setError(null)
    setFieldErrors({})

    const payload = {
      name: values.name.trim(),
      code: values.code.trim(),
      address: values.address.trim() || null,
      city: values.city.trim() || null,
      phone: values.phone.trim() || null,
    }

    if (!payload.name || !payload.code) {
      setError('Branch name and code are required.')
      return
    }

    try {
      if (editing && branch) {
        await updateBranch.mutateAsync(payload)
        toast.success('Branch updated', `${payload.name} has been saved.`)
      } else {
        const created = await createBranch.mutateAsync(payload)
        toast.success('Branch created', `${created.name} is available for registration.`)
      }
      onClose()
    } catch (cause) {
      if (cause instanceof ApiError) {
        setError(cause.message)
        setFieldErrors(cause.fieldErrors)
        return
      }
      setError('The branch could not be saved.')
    }
  }

  const pending = createBranch.isPending || updateBranch.isPending

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={editing ? 'Edit branch' : 'Create branch'}
      description="Only active branches appear on employee registration."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={pending}>
            Cancel
          </Button>
          <Button type="submit" form="admin-branch-form" isLoading={pending}>
            {editing ? 'Save changes' : 'Create branch'}
          </Button>
        </>
      }
    >
      <form id="admin-branch-form" className="space-y-4" noValidate onSubmit={(event) => void handleSubmit(event)}>
        {error && (
          <Alert tone="danger" title="Could not save">
            {error}
          </Alert>
        )}
        <FormField label="Branch Name" required error={fieldErrors.name}>
          {({ id, hasError }) => (
            <Input id={id} hasError={hasError} value={values.name} onChange={(event) => setValues((current) => ({ ...current, name: event.target.value }))} />
          )}
        </FormField>
        <FormField label="Branch Code" required error={fieldErrors.code} hint="Must be unique. Letters, numbers, hyphens and underscores.">
          {({ id, hasError }) => (
            <Input
              id={id}
              hasError={hasError}
              value={values.code}
              onChange={(event) => setValues((current) => ({ ...current, code: event.target.value.toUpperCase() }))}
            />
          )}
        </FormField>
        <FormField label="Address" error={fieldErrors.address}>
          {({ id, hasError }) => (
            <Input id={id} hasError={hasError} value={values.address} onChange={(event) => setValues((current) => ({ ...current, address: event.target.value }))} />
          )}
        </FormField>
        <FormField label="City" error={fieldErrors.city}>
          {({ id, hasError }) => (
            <Input id={id} hasError={hasError} value={values.city} onChange={(event) => setValues((current) => ({ ...current, city: event.target.value }))} />
          )}
        </FormField>
        <FormField label="Contact Number" error={fieldErrors.phone}>
          {({ id, hasError }) => (
            <Input id={id} hasError={hasError} value={values.phone} onChange={(event) => setValues((current) => ({ ...current, phone: event.target.value }))} />
          )}
        </FormField>
      </form>
    </Modal>
  )
}
