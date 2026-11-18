import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'

import { Alert } from '@/components/ui/Alert'
import { FormField } from '@/components/ui/FormField'
import { Input } from '@/components/ui/Input'
import { Select } from '@/components/ui/Select'
import {
  FormGrid,
  nullable,
  RegisterFormModal,
  SelectField,
  statusOptions,
  TextAreaField,
  TextField,
  useRegisterForm,
} from '@/features/administration/registers/registerForm'
import { useRegisterLookups, useRegisterMutation } from '@/features/administration/registers/useAdmRegisters'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import { useToast } from '@/hooks/useToast'
import { admRegistersService } from '@/services/admRegistersService'
import type { FuelCard } from '@/types/admRegisters'

export function FuelCardFormModal({
  isOpen,
  card,
  onClose,
}: {
  isOpen: boolean
  card: FuelCard | null
  onClose: () => void
}) {
  if (!isOpen) return null

  return <FuelCardForm card={card} onClose={onClose} />
}

function FuelCardForm({ card, onClose }: { card: FuelCard | null; onClose: () => void }) {
  const toast = useToast()
  const { data: lookups } = useRegisterLookups()
  const form = useRegisterForm({
    card_number: card?.card_number ?? '',
    card_ref: '',
    company_name: card?.company_name ?? '',
    provider: card?.provider ?? '',
    vehicle_id: card?.vehicle_id ? String(card.vehicle_id) : '',
    driver_id: card?.driver_id ? String(card.driver_id) : '',
    assigned_user_id: card?.assigned_user_id ? String(card.assigned_user_id) : '',
    monthly_limit: card?.monthly_limit ?? '',
    issued_at: card?.issued_at ?? '',
    expiry_date: card?.expiry_date ?? '',
    status: card?.status ?? 'active',
    notes: card?.notes ?? '',
  })

  const [userSearch, setUserSearch] = useState('')
  const debouncedSearch = useDebouncedValue(userSearch)
  const users = useQuery({
    queryKey: ['administration', 'registers', 'lookups', 'users', debouncedSearch],
    queryFn: () => admRegistersService.users(debouncedSearch),
    staleTime: 30_000,
  })

  const userOptions = [...(users.data ?? [])]
  if (card?.assigned_user && !userOptions.some((user) => user.id === card.assigned_user?.id)) {
    userOptions.unshift({ id: card.assigned_user.id, full_name: card.assigned_user.full_name, username: '' })
  }

  const save = useRegisterMutation(() => {
    const v = form.values
    const payload = {
      card_number: v.card_number.trim(),
      // Write-only: the server keeps just a masked reference.
      ...(v.card_ref.trim() ? { card_ref: v.card_ref.trim() } : {}),
      company_name: nullable(v.company_name),
      provider: nullable(v.provider),
      vehicle_id: nullable(v.vehicle_id),
      driver_id: nullable(v.driver_id),
      assigned_user_id: nullable(v.assigned_user_id),
      monthly_limit: nullable(v.monthly_limit),
      issued_at: nullable(v.issued_at),
      expiry_date: nullable(v.expiry_date),
      status: v.status,
      notes: nullable(v.notes),
    }

    return card ? admRegistersService.fuelCards.update(card.id, payload) : admRegistersService.fuelCards.create(payload)
  })

  async function submit() {
    form.reset()

    try {
      const saved = await save.mutateAsync(undefined)
      toast.success(card ? 'Fuel card updated' : 'Fuel card added', saved.card_number)
      onClose()
    } catch (cause) {
      form.fail(cause, 'The fuel card could not be saved.')
    }
  }

  return (
    <RegisterFormModal
      title={card ? 'Edit fuel card' : 'Add fuel card'}
      description="Cards are tracked by an internal ID and a masked reference."
      formId="adm-fuel-card-form"
      submitLabel={card ? 'Save changes' : 'Add card'}
      isPending={save.isPending}
      error={form.error}
      onClose={onClose}
      onSubmit={submit}
    >
      <Alert tone="warning" title="Never enter a PIN">
        The help desk does not store card PINs or security codes. Only the last four characters of the card number are kept.
      </Alert>

      <FormGrid>
        <TextField form={form} name="card_number" label="Card ID" required mono hint="Internal reference, e.g. FUEL-0012" />
        <TextField
          form={form}
          name="card_ref"
          label={card?.masked_card_ref ? `Card number (current ${card.masked_card_ref})` : 'Card number'}
          hint="Optional. Only the last 4 characters are saved."
          mono
        />
        <TextField form={form} name="provider" label="Provider" />
        <TextField form={form} name="company_name" label="Company" />
        <SelectField
          form={form}
          name="vehicle_id"
          label="Vehicle"
          emptyLabel="Not assigned"
          options={(lookups?.vehicles ?? []).map((vehicle) => ({ value: String(vehicle.id), label: vehicle.label }))}
        />
        <SelectField
          form={form}
          name="driver_id"
          label="Driver"
          emptyLabel="Not assigned"
          options={(lookups?.drivers ?? []).map((driver) => ({ value: String(driver.id), label: driver.full_name }))}
        />

        <FormField label="Search employee" hint="Type a name to find an employee to assign.">
          {({ id }) => (
            <Input id={id} value={userSearch} onChange={(event) => setUserSearch(event.target.value)} placeholder="Name or username" />
          )}
        </FormField>
        <FormField label="Assigned employee" error={form.errors.assigned_user_id}>
          {({ id, hasError }) => (
            <Select
              id={id}
              hasError={hasError}
              value={form.values.assigned_user_id}
              onChange={(event) => form.set('assigned_user_id', event.target.value)}
            >
              <option value="">Not assigned</option>
              {userOptions.map((user) => (
                <option key={user.id} value={user.id}>
                  {user.full_name}
                  {user.username ? ` (${user.username})` : ''}
                </option>
              ))}
            </Select>
          )}
        </FormField>

        <TextField form={form} name="monthly_limit" label="Monthly limit" type="number" step="0.01" min="0" />
        <SelectField
          form={form}
          name="status"
          label="Status"
          options={statusOptions(lookups?.enums.fuel_card_status ?? ['active', 'suspended', 'lost', 'cancelled'])}
        />
        <TextField form={form} name="issued_at" label="Issued on" type="date" />
        <TextField form={form} name="expiry_date" label="Expiry date" type="date" />
      </FormGrid>
      <TextAreaField form={form} name="notes" label="Notes" />
    </RegisterFormModal>
  )
}
