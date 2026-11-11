import { Plus } from 'lucide-react'
import { useState } from 'react'

import { PageHeader } from '@/components/common/PageHeader'
import { StatCard } from '@/components/common/StatCard'
import { Alert } from '@/components/ui/Alert'
import { Badge } from '@/components/ui/Badge'
import { Button, type ButtonVariant } from '@/components/ui/Button'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { EmptyState } from '@/components/ui/EmptyState'
import { ErrorState } from '@/components/ui/ErrorState'
import { FormField } from '@/components/ui/FormField'
import { Input } from '@/components/ui/Input'
import { Modal } from '@/components/ui/Modal'
import { Select } from '@/components/ui/Select'
import { Skeleton } from '@/components/ui/Skeleton'
import { Spinner } from '@/components/ui/Spinner'
import { Textarea } from '@/components/ui/Textarea'
import { useToast } from '@/hooks/useToast'
import type { ColorToken } from '@/types/models'

const BUTTON_VARIANTS: ButtonVariant[] = [
  'primary',
  'secondary',
  'outline',
  'ghost',
  'danger',
  'success',
]

const TONES: ColorToken[] = ['neutral', 'primary', 'success', 'warning', 'danger', 'info']

/**
 * Living reference for the design system. Every screen should be buildable
 * from the components shown here.
 */
export function DesignSystemPage() {
  const [isModalOpen, setModalOpen] = useState(false)
  const toast = useToast()

  return (
    <div className="space-y-6">
      <PageHeader
        title="Design system"
        description="The shared component library and colour tokens used across IT Helpdesk."
      />

      <Card>
        <CardHeader title="Buttons" description="Variants, sizes and the loading state." />
        <CardContent className="space-y-4">
          <div className="flex flex-wrap gap-3">
            {BUTTON_VARIANTS.map((variant) => (
              <Button key={variant} variant={variant}>
                {variant}
              </Button>
            ))}
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <Button size="sm">Small</Button>
            <Button size="md">Medium</Button>
            <Button size="lg">Large</Button>
            <Button leadingIcon={<Plus className="size-4" />}>With icon</Button>
            <Button isLoading>Saving</Button>
            <Button disabled>Disabled</Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader
          title="Status badges"
          description="Tones map to the color_token values returned by the API."
        />
        <CardContent className="flex flex-wrap gap-3">
          {TONES.map((tone) => (
            <Badge key={tone} tone={tone} withDot>
              {tone}
            </Badge>
          ))}
        </CardContent>
      </Card>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader title="Form controls" description="Labels, hints and error messaging." />
          <CardContent className="space-y-4">
            <FormField label="Full name" required hint="As it appears on your employee record">
              {({ id, hasError }) => (
                <Input id={id} hasError={hasError} placeholder="Nimal Perera" />
              )}
            </FormField>

            <FormField label="Branch" required>
              {({ id, hasError }) => (
                <Select id={id} hasError={hasError} defaultValue="">
                  <option value="" disabled>
                    Select a branch
                  </option>
                  <option value="1">Head Office</option>
                  <option value="2">Kandy Branch</option>
                </Select>
              )}
            </FormField>

            <FormField label="Employee ID" error="This employee ID is already registered.">
              {({ id, hasError }) => <Input id={id} hasError={hasError} defaultValue="EMP-0001" />}
            </FormField>

            <FormField label="Describe the issue">
              {({ id, hasError }) => (
                <Textarea id={id} hasError={hasError} placeholder="What went wrong?" />
              )}
            </FormField>
          </CardContent>
        </Card>

        <div className="space-y-6">
          <Card>
            <CardHeader title="Alerts and notifications" />
            <CardContent className="space-y-3">
              <Alert tone="info" title="Scheduled maintenance">
                The service desk will be unavailable on Sunday from 02:00.
              </Alert>
              <Alert tone="success" title="Ticket resolved" />
              <Alert tone="warning" title="Awaiting your confirmation" />
              <Alert tone="danger" title="Could not save your response" />

              <div className="flex flex-wrap gap-2 pt-1">
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => toast.success('Ticket created', 'Reference TKT-2026-000123')}
                >
                  Success toast
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => toast.error('Could not save', 'Please try again in a moment.')}
                >
                  Error toast
                </Button>
                <Button size="sm" variant="outline" onClick={() => setModalOpen(true)}>
                  Open modal
                </Button>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Metrics" />
            <CardContent className="grid gap-4 sm:grid-cols-2">
              <StatCard label="Open" value={42} hint="Across all branches" tone="primary" />
              <StatCard label="Breached SLA" value={3} hint="Needs attention" tone="danger" />
            </CardContent>
          </Card>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <Card>
          <CardHeader title="Loading state" />
          <CardContent className="space-y-3">
            <Skeleton className="h-4 w-3/4" />
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-1/2" />
            <div className="pt-2">
              <Spinner />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader title="Empty state" />
          <EmptyState
            title="No tickets yet"
            description="Tickets you raise will appear here."
            action={<Button size="sm">Raise a ticket</Button>}
          />
        </Card>

        <Card>
          <CardHeader title="Error state" />
          <ErrorState onRetry={() => toast.info('Retrying…')} />
        </Card>
      </div>

      <Modal
        isOpen={isModalOpen}
        onClose={() => setModalOpen(false)}
        title="Confirm resolution"
        description="Confirm whether your issue is fixed."
        footer={
          <>
            <Button variant="outline" onClick={() => setModalOpen(false)}>
              Cancel
            </Button>
            <Button
              variant="danger"
              onClick={() => {
                setModalOpen(false)
                toast.info('Marked as not resolved')
              }}
            >
              Not resolved
            </Button>
            <Button
              variant="success"
              onClick={() => {
                setModalOpen(false)
                toast.success('Ticket closed')
              }}
            >
              Yes, it is resolved
            </Button>
          </>
        }
      >
        <p className="text-sm text-surface-700">
          Choose whether IT has fixed the issue. No verification code is required.
        </p>
      </Modal>
    </div>
  )
}
