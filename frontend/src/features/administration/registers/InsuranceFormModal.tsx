import { useState } from 'react'

import { FormField } from '@/components/ui/FormField'
import { Input } from '@/components/ui/Input'
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
import { useToast } from '@/hooks/useToast'
import { admRegistersService } from '@/services/admRegistersService'
import type { InsurancePolicy } from '@/types/admRegisters'

function initialValues(policy: InsurancePolicy | null) {
  return {
    company_name: policy?.company_name ?? '',
    insurance_type: policy?.insurance_type ?? '',
    provider: policy?.provider ?? '',
    policy_number: policy?.policy_number ?? '',
    insured_asset: policy?.insured_asset ?? '',
    vehicle_registration: policy?.vehicle_registration ?? '',
    start_date: policy?.start_date ?? '',
    expiry_date: policy?.expiry_date ?? '',
    premium_amount: policy?.premium_amount ?? '',
    sum_insured: policy?.sum_insured ?? '',
    responsible_officer_id: policy?.responsible_officer_id ? String(policy.responsible_officer_id) : '',
    renewal_status: policy?.renewal_status ?? 'not_due',
    status: policy?.status ?? 'active',
    notes: policy?.notes ?? '',
  }
}

export function InsuranceFormModal({
  isOpen,
  policy,
  onClose,
}: {
  isOpen: boolean
  policy: InsurancePolicy | null
  onClose: () => void
}) {
  if (!isOpen) return null

  return <InsuranceForm policy={policy} onClose={onClose} />
}

function InsuranceForm({ policy, onClose }: { policy: InsurancePolicy | null; onClose: () => void }) {
  const toast = useToast()
  const form = useRegisterForm(initialValues(policy))
  const { data: lookups } = useRegisterLookups()
  const [file, setFile] = useState<File | null>(null)

  const save = useRegisterMutation(async () => {
    const v = form.values
    const payload = {
      company_name: nullable(v.company_name),
      insurance_type: v.insurance_type.trim(),
      provider: v.provider.trim(),
      policy_number: v.policy_number.trim(),
      insured_asset: nullable(v.insured_asset),
      vehicle_registration: nullable(v.vehicle_registration),
      start_date: nullable(v.start_date),
      expiry_date: nullable(v.expiry_date),
      premium_amount: nullable(v.premium_amount),
      sum_insured: nullable(v.sum_insured),
      responsible_officer_id: nullable(v.responsible_officer_id),
      renewal_status: v.renewal_status,
      status: v.status,
      notes: nullable(v.notes),
    }

    const saved = policy
      ? await admRegistersService.insurance.update(policy.id, payload)
      : await admRegistersService.insurance.create(payload)

    if (file) {
      try {
        await admRegistersService.insurance.uploadDocument(saved.id, file)
      } catch {
        toast.warning('Policy saved', 'The document could not be uploaded. Try again from Edit.')
      }
    }

    return saved
  })

  async function submit() {
    form.reset()

    try {
      const saved = await save.mutateAsync(undefined)
      toast.success(policy ? 'Policy updated' : 'Policy added', saved.policy_number)
      onClose()
    } catch (cause) {
      form.fail(cause, 'The policy could not be saved.')
    }
  }

  return (
    <RegisterFormModal
      title={policy ? 'Edit insurance policy' : 'Add insurance policy'}
      description="Track policy cover, expiry and renewal status."
      formId="adm-insurance-form"
      submitLabel={policy ? 'Save changes' : 'Add policy'}
      isPending={save.isPending}
      error={form.error}
      onClose={onClose}
      onSubmit={submit}
    >
      <FormGrid>
        <TextField form={form} name="policy_number" label="Policy number" required />
        <TextField form={form} name="insurance_type" label="Insurance type" required hint="e.g. Motor, Property, Fire" />
        <TextField form={form} name="provider" label="Provider" required hint="Insurance company" />
        <TextField form={form} name="company_name" label="Company" hint="Which group company owns the policy" />
        <TextField form={form} name="insured_asset" label="Insured asset" />
        <TextField form={form} name="vehicle_registration" label="Vehicle registration" hint="Motor policies only" />
        <TextField form={form} name="start_date" label="Start date" type="date" />
        <TextField form={form} name="expiry_date" label="Expiry date" type="date" required />
        <TextField form={form} name="premium_amount" label="Premium amount" type="number" step="0.01" min="0" />
        <TextField form={form} name="sum_insured" label="Sum insured" type="number" step="0.01" min="0" />
        <SelectField
          form={form}
          name="responsible_officer_id"
          label="Responsible officer"
          emptyLabel="Unassigned (notify register managers)"
          options={(lookups?.officers ?? []).map((officer) => ({ value: String(officer.id), label: officer.full_name }))}
        />
        <SelectField
          form={form}
          name="renewal_status"
          label="Renewal status"
          options={statusOptions(lookups?.enums.renewal_status ?? ['not_due', 'pending', 'in_progress', 'renewed'])}
        />
        <SelectField
          form={form}
          name="status"
          label="Policy status"
          options={statusOptions(lookups?.enums.insurance_status ?? ['active', 'expired', 'cancelled'])}
        />
        <FormField
          label="Policy document"
          error={form.errors.document}
          hint={policy?.has_document ? `Current: ${policy.document_name ?? 'uploaded'}. Choose a file to replace it.` : 'PDF, image or Word, up to 10 MB.'}
        >
          {({ id }) => (
            <Input
              id={id}
              type="file"
              accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
              onChange={(event) => setFile(event.target.files?.[0] ?? null)}
            />
          )}
        </FormField>
      </FormGrid>
      <TextAreaField form={form} name="notes" label="Notes" />
    </RegisterFormModal>
  )
}
