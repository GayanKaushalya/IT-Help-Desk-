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
import type { Driver, Vehicle } from '@/types/admRegisters'

/* ------------------------------ vehicle ------------------------------ */

export function VehicleFormModal({
  isOpen,
  vehicle,
  onClose,
}: {
  isOpen: boolean
  vehicle: Vehicle | null
  onClose: () => void
}) {
  if (!isOpen) return null

  return <VehicleForm vehicle={vehicle} onClose={onClose} />
}

function VehicleForm({ vehicle, onClose }: { vehicle: Vehicle | null; onClose: () => void }) {
  const toast = useToast()
  const { data: lookups } = useRegisterLookups()
  const form = useRegisterForm({
    registration_number: vehicle?.registration_number ?? '',
    company_name: vehicle?.company_name ?? '',
    make: vehicle?.make ?? '',
    model: vehicle?.model ?? '',
    year: vehicle?.year ? String(vehicle.year) : '',
    vehicle_type: vehicle?.vehicle_type ?? '',
    fuel_type: vehicle?.fuel_type ?? '',
    chassis_number: vehicle?.chassis_number ?? '',
    current_mileage: vehicle?.current_mileage !== null && vehicle?.current_mileage !== undefined ? String(vehicle.current_mileage) : '',
    driver_id: vehicle?.driver_id ? String(vehicle.driver_id) : '',
    insurance_policy_id: vehicle?.insurance_policy_id ? String(vehicle.insurance_policy_id) : '',
    revenue_license_expiry: vehicle?.revenue_license_expiry ?? '',
    status: vehicle?.status ?? 'available',
    notes: vehicle?.notes ?? '',
  })

  const save = useRegisterMutation(() => {
    const v = form.values
    const payload = {
      registration_number: v.registration_number.trim(),
      company_name: nullable(v.company_name),
      make: nullable(v.make),
      model: nullable(v.model),
      year: nullable(v.year),
      vehicle_type: nullable(v.vehicle_type),
      fuel_type: nullable(v.fuel_type),
      chassis_number: nullable(v.chassis_number),
      current_mileage: nullable(v.current_mileage),
      driver_id: nullable(v.driver_id),
      insurance_policy_id: nullable(v.insurance_policy_id),
      revenue_license_expiry: nullable(v.revenue_license_expiry),
      status: v.status,
      notes: nullable(v.notes),
    }

    return vehicle ? admRegistersService.vehicles.update(vehicle.id, payload) : admRegistersService.vehicles.create(payload)
  })

  async function submit() {
    form.reset()

    try {
      const saved = await save.mutateAsync(undefined)
      toast.success(vehicle ? 'Vehicle updated' : 'Vehicle added', saved.registration_number)
      onClose()
    } catch (cause) {
      form.fail(cause, 'The vehicle could not be saved.')
    }
  }

  return (
    <RegisterFormModal
      title={vehicle ? 'Edit vehicle' : 'Add vehicle'}
      description="Company vehicle register."
      formId="adm-vehicle-form"
      submitLabel={vehicle ? 'Save changes' : 'Add vehicle'}
      isPending={save.isPending}
      error={form.error}
      onClose={onClose}
      onSubmit={submit}
    >
      <FormGrid>
        <TextField form={form} name="registration_number" label="Registration number" required mono />
        <TextField form={form} name="company_name" label="Company" />
        <TextField form={form} name="make" label="Make" />
        <TextField form={form} name="model" label="Model" />
        <TextField form={form} name="year" label="Year" type="number" min="1950" />
        <TextField form={form} name="vehicle_type" label="Vehicle type" hint="e.g. Van, Car, Lorry" />
        <TextField form={form} name="fuel_type" label="Fuel type" hint="e.g. Petrol, Diesel" />
        <TextField form={form} name="chassis_number" label="Chassis number" mono />
        <TextField form={form} name="current_mileage" label="Current mileage (km)" type="number" min="0" />
        <TextField form={form} name="revenue_license_expiry" label="Revenue licence expiry" type="date" />
        <SelectField
          form={form}
          name="driver_id"
          label="Default driver"
          emptyLabel="No default driver"
          options={(lookups?.drivers ?? []).map((driver) => ({ value: String(driver.id), label: driver.full_name }))}
        />
        <SelectField
          form={form}
          name="insurance_policy_id"
          label="Insurance policy"
          emptyLabel="Not linked"
          options={(lookups?.insurance_policies ?? []).map((policy) => ({ value: String(policy.id), label: policy.label }))}
        />
        <SelectField
          form={form}
          name="status"
          label="Status"
          options={statusOptions(lookups?.enums.vehicle_status ?? ['available', 'maintenance', 'retired'])}
          hint="Only available vehicles can be allocated."
        />
      </FormGrid>
      <TextAreaField form={form} name="notes" label="Notes" />
    </RegisterFormModal>
  )
}

/* ------------------------------ driver ------------------------------- */

export function DriverFormModal({
  isOpen,
  driver,
  onClose,
}: {
  isOpen: boolean
  driver: Driver | null
  onClose: () => void
}) {
  if (!isOpen) return null

  return <DriverForm driver={driver} onClose={onClose} />
}

function DriverForm({ driver, onClose }: { driver: Driver | null; onClose: () => void }) {
  const toast = useToast()
  const { data: lookups } = useRegisterLookups()
  const form = useRegisterForm({
    full_name: driver?.full_name ?? '',
    company_name: driver?.company_name ?? '',
    employee_id: driver?.employee_id ?? '',
    license_number: driver?.license_number ?? '',
    license_class: driver?.license_class ?? '',
    license_expiry_date: driver?.license_expiry_date ?? '',
    phone: driver?.phone ?? '',
    email: driver?.email ?? '',
    status: driver?.status ?? 'active',
    notes: driver?.notes ?? '',
  })

  const save = useRegisterMutation(() => {
    const v = form.values
    const payload = {
      full_name: v.full_name.trim(),
      company_name: nullable(v.company_name),
      employee_id: nullable(v.employee_id),
      license_number: nullable(v.license_number),
      license_class: nullable(v.license_class),
      license_expiry_date: nullable(v.license_expiry_date),
      phone: nullable(v.phone),
      email: nullable(v.email),
      status: v.status,
      notes: nullable(v.notes),
    }

    return driver ? admRegistersService.drivers.update(driver.id, payload) : admRegistersService.drivers.create(payload)
  })

  async function submit() {
    form.reset()

    try {
      const saved = await save.mutateAsync(undefined)
      toast.success(driver ? 'Driver updated' : 'Driver added', saved.full_name)
      onClose()
    } catch (cause) {
      form.fail(cause, 'The driver could not be saved.')
    }
  }

  return (
    <RegisterFormModal
      title={driver ? 'Edit driver' : 'Add driver'}
      description="Driver details and licence tracking."
      formId="adm-driver-form"
      submitLabel={driver ? 'Save changes' : 'Add driver'}
      isPending={save.isPending}
      error={form.error}
      onClose={onClose}
      onSubmit={submit}
    >
      <FormGrid>
        <TextField form={form} name="full_name" label="Full name" required />
        <TextField form={form} name="employee_id" label="Employee ID" />
        <TextField form={form} name="company_name" label="Company" />
        <TextField form={form} name="phone" label="Phone" type="tel" />
        <TextField form={form} name="email" label="Email" type="email" />
        <TextField form={form} name="license_number" label="Licence number" mono />
        <TextField form={form} name="license_class" label="Licence class" hint="e.g. B, C1" />
        <TextField form={form} name="license_expiry_date" label="Licence expiry" type="date" />
        <SelectField
          form={form}
          name="status"
          label="Status"
          options={statusOptions(lookups?.enums.driver_status ?? ['active', 'on_leave', 'inactive'])}
          hint="Only active drivers can be allocated."
        />
      </FormGrid>
      <TextAreaField form={form} name="notes" label="Notes" />
    </RegisterFormModal>
  )
}
