import { useEffect, useState, type FormEvent } from 'react'

import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { FormField } from '@/components/ui/FormField'
import { Input } from '@/components/ui/Input'
import { Modal } from '@/components/ui/Modal'
import { Select } from '@/components/ui/Select'
import { useAdminLookups, useCreateAdminUser, useUpdateAdminUser } from '@/features/admin/useAdminDirectory'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { ApiError } from '@/types/api'
import type { User } from '@/types/models'

interface UserFormState {
  full_name: string
  username: string
  employee_id: string
  epf_number: string
  email: string
  phone: string
  job_title: string
  role_id: string
  branch_id: string
  department_id: string
}

const EMPTY: UserFormState = {
  full_name: '',
  username: '',
  employee_id: '',
  epf_number: '',
  email: '',
  phone: '',
  job_title: '',
  role_id: '',
  branch_id: '',
  department_id: '',
}

function fromUser(user: User): UserFormState {
  return {
    full_name: user.full_name,
    username: user.username,
    employee_id: user.employee_id,
    epf_number: user.epf_number,
    email: user.email ?? '',
    phone: user.phone ?? '',
    job_title: user.job_title ?? '',
    role_id: user.role ? String(user.role.id) : '',
    branch_id: user.branch ? String(user.branch.id) : '',
    department_id: user.department ? String(user.department.id) : '',
  }
}

export function UserFormModal({
  isOpen,
  onClose,
  user,
  onIssuedPassword,
}: {
  isOpen: boolean
  onClose: () => void
  user?: User | null
  onIssuedPassword?: (username: string, password: string) => void
}) {
  const toast = useToast()
  const { user: actor } = useAuth()
  const { data: lookups } = useAdminLookups()
  const createUser = useCreateAdminUser()
  const updateUser = useUpdateAdminUser(user ? String(user.id) : '0')
  const [values, setValues] = useState<UserFormState>(EMPTY)
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  const editing = Boolean(user)
  const assignableRoles = (lookups?.roles ?? []).filter((role) => {
    if (actor?.role?.slug === 'super-admin') return true
    if (role.slug === 'super-admin') return false
    // Administrators may assign Administrator; higher roles stay hidden.
    return (actor?.role?.level ?? 0) >= role.level
  })

  useEffect(() => {
    if (!isOpen) return
    setValues(user ? fromUser(user) : EMPTY)
    setError(null)
    setFieldErrors({})
  }, [isOpen, user])

  function update<K extends keyof UserFormState>(field: K, value: UserFormState[K]) {
    setValues((current) => ({ ...current, [field]: value }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault()
    setError(null)
    setFieldErrors({})

    if (!values.full_name.trim() || !values.username.trim() || !values.role_id || !values.branch_id) {
      setError('Name, username, role and branch are required.')
      return
    }

    const payload = {
      full_name: values.full_name.trim(),
      username: values.username.trim(),
      employee_id: values.employee_id.trim(),
      epf_number: values.epf_number.trim(),
      email: values.email.trim() || null,
      phone: values.phone.trim() || null,
      job_title: values.job_title.trim() || null,
      role_id: Number(values.role_id),
      branch_id: Number(values.branch_id),
      department_id: values.department_id ? Number(values.department_id) : null,
    }

    try {
      if (editing && user) {
        await updateUser.mutateAsync(payload)
        toast.success('User updated', `${payload.full_name} has been saved.`)
        onClose()
        return
      }

      const created = await createUser.mutateAsync(payload)
      toast.success('User created', `${created.full_name} can now sign in.`)
      if (created.temporary_password) {
        onIssuedPassword?.(created.username, created.temporary_password)
      }
      onClose()
    } catch (cause) {
      if (cause instanceof ApiError) {
        setError(cause.message)
        setFieldErrors(cause.fieldErrors)
        return
      }
      setError('The account could not be saved.')
    }
  }

  const pending = createUser.isPending || updateUser.isPending

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={editing ? 'Edit user' : 'Create user'}
      description={
        editing
          ? 'Update directory details. Passwords are changed through Reset password.'
          : 'A temporary password will be generated and shown once.'
      }
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={pending}>
            Cancel
          </Button>
          <Button type="submit" form="admin-user-form" isLoading={pending}>
            {editing ? 'Save changes' : 'Create user'}
          </Button>
        </>
      }
    >
      <form id="admin-user-form" className="grid gap-4 sm:grid-cols-2" noValidate onSubmit={(event) => void handleSubmit(event)}>
        {error && (
          <Alert tone="danger" title="Could not save" className="sm:col-span-2">
            {error}
          </Alert>
        )}
        <FormField label="Full name" required error={fieldErrors.full_name}>
          {({ id, hasError }) => (
            <Input id={id} hasError={hasError} value={values.full_name} onChange={(event) => update('full_name', event.target.value)} />
          )}
        </FormField>
        <FormField label="Username" required error={fieldErrors.username}>
          {({ id, hasError }) => (
            <Input id={id} hasError={hasError} value={values.username} onChange={(event) => update('username', event.target.value)} />
          )}
        </FormField>
        <FormField label="Employee ID" required error={fieldErrors.employee_id}>
          {({ id, hasError }) => (
            <Input id={id} hasError={hasError} value={values.employee_id} onChange={(event) => update('employee_id', event.target.value)} />
          )}
        </FormField>
        <FormField label="EPF Number" required error={fieldErrors.epf_number}>
          {({ id, hasError }) => (
            <Input id={id} hasError={hasError} value={values.epf_number} onChange={(event) => update('epf_number', event.target.value)} />
          )}
        </FormField>
        <FormField label="Email" error={fieldErrors.email}>
          {({ id, hasError }) => (
            <Input id={id} type="email" hasError={hasError} value={values.email} onChange={(event) => update('email', event.target.value)} />
          )}
        </FormField>
        <FormField label="Phone" error={fieldErrors.phone}>
          {({ id, hasError }) => (
            <Input id={id} hasError={hasError} value={values.phone} onChange={(event) => update('phone', event.target.value)} />
          )}
        </FormField>
        <FormField label="Job title" error={fieldErrors.job_title} className="sm:col-span-2">
          {({ id, hasError }) => (
            <Input id={id} hasError={hasError} value={values.job_title} onChange={(event) => update('job_title', event.target.value)} />
          )}
        </FormField>
        <FormField label="Role" required error={fieldErrors.role_id}>
          {({ id, hasError }) => (
            <Select id={id} hasError={hasError} value={values.role_id} onChange={(event) => update('role_id', event.target.value)}>
              <option value="">Select role</option>
              {assignableRoles.map((role) => (
                <option key={role.id} value={role.id}>
                  {role.name}
                </option>
              ))}
            </Select>
          )}
        </FormField>
        <FormField label="Branch" required error={fieldErrors.branch_id}>
          {({ id, hasError }) => (
            <Select id={id} hasError={hasError} value={values.branch_id} onChange={(event) => update('branch_id', event.target.value)}>
              <option value="">Select branch</option>
              {lookups?.branches.map((branch) => (
                <option key={branch.id} value={branch.id}>
                  {branch.name} ({branch.code})
                </option>
              ))}
            </Select>
          )}
        </FormField>
        <FormField label="Department" error={fieldErrors.department_id} className="sm:col-span-2">
          {({ id, hasError }) => (
            <Select id={id} hasError={hasError} value={values.department_id} onChange={(event) => update('department_id', event.target.value)}>
              <option value="">No department</option>
              {lookups?.departments.map((department) => (
                <option key={department.id} value={department.id}>
                  {department.name}
                </option>
              ))}
            </Select>
          )}
        </FormField>
      </form>
    </Modal>
  )
}
