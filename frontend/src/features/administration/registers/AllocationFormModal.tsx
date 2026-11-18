import { useQuery } from '@tanstack/react-query'

import { Alert } from '@/components/ui/Alert'
import {
  FormGrid,
  nullable,
  RegisterFormModal,
  SelectField,
  TextAreaField,
  TextField,
  useRegisterForm,
} from '@/features/administration/registers/registerForm'
import { registerKeys, useRegisterLookups, useRegisterMutation } from '@/features/administration/registers/useAdmRegisters'
import { useToast } from '@/hooks/useToast'
import { admRegistersService } from '@/services/admRegistersService'
import type { VehicleAllocation } from '@/types/admRegisters'
import { formatDateTime } from '@/utils/format'

/** Same rule as the server: `existing.start < new.end AND existing.end > new.start`. */
export function findOverlaps(
  existing: VehicleAllocation[],
  startAt: string,
  endAt: string,
  ignoreId: number | null,
): VehicleAllocation[] {
  if (!startAt || !endAt || endAt <= startAt) return []

  return existing.filter(
    (row) => row.id !== ignoreId && row.status !== 'cancelled' && row.start_at < endAt && row.end_at > startAt,
  )
}

export function AllocationFormModal({
  isOpen,
  allocation,
  onClose,
}: {
  isOpen: boolean
  allocation: VehicleAllocation | null
  onClose: () => void
}) {
  if (!isOpen) return null

  return <AllocationForm allocation={allocation} onClose={onClose} />
}

function AllocationForm({ allocation, onClose }: { allocation: VehicleAllocation | null; onClose: () => void }) {
  const toast = useToast()
  const { data: lookups } = useRegisterLookups()
  const form = useRegisterForm({
    vehicle_id: allocation ? String(allocation.vehicle_id) : '',
    driver_id: allocation?.driver_id ? String(allocation.driver_id) : '',
    adm_request_id: allocation?.adm_request_id ? String(allocation.adm_request_id) : '',
    start_at: allocation?.start_at ?? '',
    end_at: allocation?.end_at ?? '',
    status: allocation?.status ?? 'scheduled',
    purpose: allocation?.purpose ?? '',
    destination: allocation?.destination ?? '',
    notes: allocation?.notes ?? '',
  })

  const { vehicle_id: vehicleId, driver_id: driverId, start_at: startAt, end_at: endAt } = form.values
  const rangeValid = Boolean(startAt && endAt && endAt > startAt)

  // Pre-flight check so the user sees a clash before submitting; the server stays authoritative.
  const clashes = useQuery({
    queryKey: [...registerKeys.all, 'allocation-clashes', vehicleId, driverId, startAt, endAt],
    enabled: rangeValid && (vehicleId !== '' || driverId !== ''),
    queryFn: async () => {
      const window = { date_from: startAt.slice(0, 10), date_to: endAt.slice(0, 10), per_page: 100 }
      const [byVehicle, byDriver] = await Promise.all([
        vehicleId ? admRegistersService.allocations.list({ ...window, vehicle_id: vehicleId }) : null,
        driverId ? admRegistersService.allocations.list({ ...window, driver_id: driverId }) : null,
      ])

      return {
        vehicle: findOverlaps(byVehicle?.data ?? [], startAt, endAt, allocation?.id ?? null),
        driver: findOverlaps(byDriver?.data ?? [], startAt, endAt, allocation?.id ?? null),
      }
    },
  })

  const vehicleClash = clashes.data?.vehicle ?? []
  const driverClash = clashes.data?.driver ?? []
  const hasClash = rangeValid && (vehicleClash.length > 0 || driverClash.length > 0)
  const invalidRange = Boolean(startAt && endAt && endAt <= startAt)

  const save = useRegisterMutation(() => {
    const v = form.values
    const payload = {
      vehicle_id: v.vehicle_id,
      driver_id: nullable(v.driver_id),
      adm_request_id: nullable(v.adm_request_id),
      start_at: v.start_at,
      end_at: v.end_at,
      ...(allocation ? { status: v.status } : {}),
      purpose: nullable(v.purpose),
      destination: nullable(v.destination),
      notes: nullable(v.notes),
    }

    return allocation
      ? admRegistersService.allocations.update(allocation.id, payload)
      : admRegistersService.allocations.create(payload)
  })

  async function submit() {
    form.reset()

    try {
      await save.mutateAsync(undefined)
      toast.success(allocation ? 'Allocation updated' : 'Vehicle allocated')
      onClose()
    } catch (cause) {
      form.fail(cause, 'The allocation could not be saved.')
    }
  }

  return (
    <RegisterFormModal
      title={allocation ? 'Edit allocation' : 'Allocate vehicle'}
      description="Book a vehicle, and optionally a driver, for a date range."
      formId="adm-allocation-form"
      submitLabel={allocation ? 'Save changes' : 'Allocate'}
      isPending={save.isPending}
      error={form.error}
      submitDisabled={hasClash || invalidRange}
      onClose={onClose}
      onSubmit={submit}
    >
      <FormGrid>
        <SelectField
          form={form}
          name="vehicle_id"
          label="Vehicle"
          required
          emptyLabel="Select a vehicle"
          options={(lookups?.vehicles ?? []).map((vehicle) => ({
            value: String(vehicle.id),
            label: vehicle.status === 'available' ? vehicle.label : `${vehicle.label} (${vehicle.status})`,
          }))}
        />
        <SelectField
          form={form}
          name="driver_id"
          label="Driver"
          emptyLabel="No driver"
          options={(lookups?.drivers ?? []).map((driver) => ({
            value: String(driver.id),
            label: driver.status === 'active' ? driver.full_name : `${driver.full_name} (${driver.status})`,
          }))}
        />
        <TextField form={form} name="start_at" label="Start" type="datetime-local" required />
        <TextField form={form} name="end_at" label="End" type="datetime-local" required />
        <TextField form={form} name="purpose" label="Purpose" />
        <TextField form={form} name="destination" label="Destination" />
        <SelectField
          form={form}
          name="adm_request_id"
          label="Linked request"
          emptyLabel="None"
          options={(lookups?.booking_requests ?? []).map((request) => ({ value: String(request.id), label: request.label }))}
        />
        {allocation && (
          <SelectField
            form={form}
            name="status"
            label="Status"
            options={(lookups?.enums.allocation_status ?? ['scheduled', 'active', 'completed', 'cancelled']).map((value) => ({
              value,
              label: value.charAt(0).toUpperCase() + value.slice(1),
            }))}
          />
        )}
      </FormGrid>

      {invalidRange && (
        <Alert tone="danger" title="Check the dates">
          The end must be after the start.
        </Alert>
      )}

      {hasClash && (
        <Alert tone="danger" title="Booking conflict">
          <ul className="list-disc space-y-1 pl-4">
            {vehicleClash.map((row) => (
              <li key={`v-${row.id}`}>
                This vehicle is already booked {formatDateTime(row.start_at)} to {formatDateTime(row.end_at)}.
              </li>
            ))}
            {driverClash.map((row) => (
              <li key={`d-${row.id}`}>
                This driver is already booked {formatDateTime(row.start_at)} to {formatDateTime(row.end_at)}
                {row.vehicle ? ` (${row.vehicle.registration_number})` : ''}.
              </li>
            ))}
          </ul>
        </Alert>
      )}

      <TextAreaField form={form} name="notes" label="Notes" />
    </RegisterFormModal>
  )
}
