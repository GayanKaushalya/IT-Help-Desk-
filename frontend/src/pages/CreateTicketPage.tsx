import { ArrowLeft, CheckCircle2, TicketPlus } from 'lucide-react'
import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'

import { PageHeader } from '@/components/common/PageHeader'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/Card'
import { ErrorState } from '@/components/ui/ErrorState'
import { Form } from '@/components/ui/Form'
import { FormField } from '@/components/ui/FormField'
import { Input } from '@/components/ui/Input'
import { Select } from '@/components/ui/Select'
import { LoadingPanel } from '@/components/ui/Spinner'
import { Textarea } from '@/components/ui/Textarea'
import { useLookups } from '@/features/system/useSystemStatus'
import {
  DESCRIPTION_MAX,
  DETAILS_MAX,
  EMPTY_TICKET_FORM,
  ITEM_OTHER_MAX,
  SUBJECT_MAX,
  validateCreateTicket,
  type CreateTicketFormValues,
} from '@/features/tickets/createTicketValidation'
import { FileUpload, type SelectedFile } from '@/features/tickets/FileUpload'
import { TicketPriorityBadge, TicketStatusBadge } from '@/features/tickets/TicketStatusBadge'
import { useInvalidateEmployeeTickets } from '@/features/tickets/useTickets'
import { useAuth } from '@/hooks/useAuth'
import { usePauseLiveRefresh } from '@/hooks/useLiveRefreshGuard'
import { useToast } from '@/hooks/useToast'
import { paths } from '@/routes/paths'
import { ticketService } from '@/services/ticketService'
import { ApiError } from '@/types/api'
import type { Ticket } from '@/types/models'
import { clearFormDraft, readFormDraft, writeFormDraft } from '@/utils/formDraft'

const CREATE_DRAFT_KEY = 'helpdesk.create-ticket.draft'

function hasDraftContent(values: CreateTicketFormValues): boolean {
  return Object.entries(values).some(([key, value]) => {
    if (key === 'branch_id') return false
    return typeof value === 'string' && value.trim().length > 0
  })
}

export function CreateTicketPage() {
  const navigate = useNavigate()
  const toast = useToast()
  const invalidateEmployeeTickets = useInvalidateEmployeeTickets()
  const { user } = useAuth()
  const { data: lookups, isPending, isError, refetch, isRefetching } = useLookups()

  const [values, setValues] = useState<CreateTicketFormValues>(() => {
    const draft = readFormDraft<CreateTicketFormValues>(CREATE_DRAFT_KEY)
    if (draft) {
      return {
        ...EMPTY_TICKET_FORM,
        ...draft,
        branch_id: draft.branch_id || (user?.branch?.id ? String(user.branch.id) : ''),
      }
    }

    return {
      ...EMPTY_TICKET_FORM,
      branch_id: user?.branch?.id ? String(user.branch.id) : '',
    }
  })
  const [files, setFiles] = useState<SelectedFile[]>([])
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [uploadProgress, setUploadProgress] = useState<number | null>(null)
  const [created, setCreated] = useState<Ticket | null>(null)

  usePauseLiveRefresh(hasDraftContent(values) || files.length > 0 || isSubmitting)

  useEffect(() => {
    if (created) {
      clearFormDraft(CREATE_DRAFT_KEY)
      return
    }

    if (hasDraftContent(values)) {
      writeFormDraft(CREATE_DRAFT_KEY, values)
    } else {
      clearFormDraft(CREATE_DRAFT_KEY)
    }
  }, [values, created])

  const selectedCategory = useMemo(
    () => lookups?.categories.find((category) => String(category.id) === values.category_id),
    [lookups?.categories, values.category_id],
  )

  function update<K extends keyof CreateTicketFormValues>(field: K, value: CreateTicketFormValues[K]) {
    setValues((current) => {
      const next = { ...current, [field]: value }

      if (field === 'category_id') {
        const category = lookups?.categories.find((item) => String(item.id) === value)
        if (category?.default_priority_id && !current.priority_id) {
          next.priority_id = String(category.default_priority_id)
        }

        if (!category?.requires_item_details) {
          next.issue_type = ''
          next.item_type = ''
          next.item_other = ''
        }
      }

      if (field === 'issue_type' || field === 'item_type') {
        const issueType = field === 'issue_type' ? value : next.issue_type
        const itemType = field === 'item_type' ? value : next.item_type
        if (issueType !== 'other' && itemType !== 'other') {
          next.item_other = ''
        }
      }

      return next
    })
    setFieldErrors((current) => {
      if (!current[field]) return current
      const next = { ...current }
      delete next[field]
      return next
    })
  }

  async function handleSubmit(_event: FormEvent<HTMLFormElement>): Promise<void> {
    setFormError(null)

    const clientErrors = validateCreateTicket(values, selectedCategory)
    if (Object.keys(clientErrors).length > 0) {
      setFieldErrors(clientErrors)
      return
    }

    const body = new FormData()
    body.append('subject', values.subject.trim())
    body.append('category_id', values.category_id)
    body.append('priority_id', values.priority_id)
    body.append('branch_id', values.branch_id)
    body.append('contact_phone', values.contact_phone.trim())
    if (values.description.trim()) {
      body.append('description', values.description.trim())
    }

    if (values.preferred_contact_method) {
      body.append('preferred_contact_method', values.preferred_contact_method)
    }
    if (values.contact_location.trim()) {
      body.append('contact_location', values.contact_location.trim())
    }
    if (values.additional_details.trim()) {
      body.append('additional_details', values.additional_details.trim())
    }

    if (selectedCategory?.requires_item_details) {
      body.append('issue_type', values.issue_type)
      body.append('item_type', values.item_type)
      if (values.item_other.trim()) {
        body.append('item_other', values.item_other.trim())
      }
    }

    files.forEach((item) => body.append('attachments[]', item.file))

    setIsSubmitting(true)
    setUploadProgress(files.length > 0 ? 0 : null)

    try {
      const ticket = await ticketService.create(body, (percent) => setUploadProgress(percent))
      clearFormDraft(CREATE_DRAFT_KEY)
      setCreated(ticket)
      toast.success('Request submitted', ticket.reference)
      invalidateEmployeeTickets()
    } catch (error) {
      if (error instanceof ApiError && error.isValidationError) {
        setFieldErrors(error.fieldErrors)
        setFormError(error.message)
      } else if (error instanceof ApiError) {
        setFormError(error.message)
      } else {
        setFormError('The ticket could not be submitted. Please try again.')
      }
    } finally {
      setIsSubmitting(false)
      setUploadProgress(null)
    }
  }

  function resetForm() {
    clearFormDraft(CREATE_DRAFT_KEY)
    setCreated(null)
    setValues({
      ...EMPTY_TICKET_FORM,
      branch_id: user?.branch?.id ? String(user.branch.id) : '',
    })
    setFiles([])
    setFieldErrors({})
    setFormError(null)
  }

  // Only block the first load — never unmount the form during a background refetch.
  if (isPending && !lookups) {
    return <LoadingPanel label="Loading ticket form…" />
  }

  if ((isError && !lookups) || !lookups) {
    return (
      <div className="space-y-6">
        <PageHeader title="Create IT Ticket" description="Tell IT support what you need help with." />
        <Card>
          <ErrorState
            title="Unable to load the ticket form"
            description="Categories and priorities could not be loaded from the server."
            onRetry={() => void refetch()}
            isRetrying={isRefetching}
          />
        </Card>
      </div>
    )
  }

  if (created) {
    return (
      <div className="mx-auto max-w-2xl space-y-6">
        <PageHeader title="Create IT Ticket" description="Your request has been received." />
        <Card>
          <CardContent className="py-8">
            <div className="flex flex-col items-center text-center">
              <span className="flex size-14 items-center justify-center rounded-full bg-success-50 text-success-500">
                <CheckCircle2 className="size-7" aria-hidden />
              </span>
              <h2 className="mt-4 text-lg font-semibold text-surface-900">
                Your IT support request has been submitted.
              </h2>
              <p className="mt-1 text-sm text-surface-500">
                Keep this ticket number for your records. IT support will pick it up shortly.
              </p>
            </div>

            <dl className="mt-8 grid gap-4 rounded-xl border border-surface-200 bg-surface-50 px-5 py-4 sm:grid-cols-2">
              <div>
                <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">Ticket Number</dt>
                <dd className="mt-1 font-mono text-sm font-semibold text-primary-700">{created.reference}</dd>
              </div>
              <div>
                <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">Subject</dt>
                <dd className="mt-1 text-sm font-medium text-surface-900">{created.subject}</dd>
              </div>
              <div>
                <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">Priority</dt>
                <dd className="mt-1">
                  <TicketPriorityBadge priority={created.priority} />
                </dd>
              </div>
              <div>
                <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">Status</dt>
                <dd className="mt-1">
                  <TicketStatusBadge status={created.status} />
                </dd>
              </div>
            </dl>
          </CardContent>
          <CardFooter className="justify-between">
            <Button variant="ghost" onClick={resetForm}>
              Submit another request
            </Button>
            <Button onClick={() => void navigate(paths.tickets.detail(created.id))}>View Ticket</Button>
          </CardFooter>
        </Card>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <PageHeader
        eyebrow={
          <button
            type="button"
            onClick={() => void navigate(paths.dashboard)}
            className="text-xs font-medium text-primary-600 hover:text-primary-700"
          >
            ← Dashboard
          </button>
        }
        title="Create IT Ticket"
        description="Describe the issue clearly so IT support can help you faster."
      />

      <Card>
        <CardHeader
          title="Ticket information"
          description="Required fields are marked. Attachments and extra details are optional."
        />
        <CardContent>
          <Form onSubmit={handleSubmit} className="space-y-6">
            {formError && (
              <Alert tone="danger" title="The ticket could not be submitted">
                {formError}
              </Alert>
            )}

            <FormField label="Subject" required error={fieldErrors.subject}>
              {({ id, hasError }) => (
                <Input
                  id={id}
                  hasError={hasError}
                  maxLength={SUBJECT_MAX}
                  value={values.subject}
                  onChange={(event) => update('subject', event.target.value)}
                  placeholder="Short summary of the issue"
                  disabled={isSubmitting}
                />
              )}
            </FormField>

            <div className="grid gap-5 sm:grid-cols-2">
              <FormField
                label="Category"
                required
                error={fieldErrors.category_id}
                hint={selectedCategory?.description ?? undefined}
              >
                {({ id, hasError }) => (
                  <Select
                    id={id}
                    hasError={hasError}
                    value={values.category_id}
                    onChange={(event) => update('category_id', event.target.value)}
                    disabled={isSubmitting}
                  >
                    <option value="">Select a category</option>
                    {lookups.categories.map((category) => (
                      <option key={category.id} value={category.id}>
                        {category.name}
                      </option>
                    ))}
                  </Select>
                )}
              </FormField>

              <FormField
                label="Priority"
                required
                error={fieldErrors.priority_id}
                hint="Choose how urgently this is affecting your work."
              >
                {({ id, hasError }) => (
                  <Select
                    id={id}
                    hasError={hasError}
                    value={values.priority_id}
                    onChange={(event) => update('priority_id', event.target.value)}
                    disabled={isSubmitting}
                  >
                    <option value="">Select a priority</option>
                    {lookups.priorities.map((priority) => (
                      <option key={priority.id} value={priority.id}>
                        {priority.name}
                      </option>
                    ))}
                  </Select>
                )}
              </FormField>
            </div>

            {selectedCategory?.requires_item_details && (
              <div className="space-y-5 rounded-xl border border-surface-200 bg-surface-50/80 p-4 sm:p-5">
                <div>
                  <h3 className="text-sm font-semibold text-surface-900">Item request</h3>
                  <p className="mt-1 text-sm text-surface-600">
                    Tell us what you need and why so IT can fulfil the request accurately.
                  </p>
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                  <FormField label="Issue type" required error={fieldErrors.issue_type}>
                    {({ id, hasError }) => (
                      <Select
                        id={id}
                        hasError={hasError}
                        value={values.issue_type}
                        onChange={(event) => update('issue_type', event.target.value)}
                        disabled={isSubmitting}
                      >
                        <option value="">Select an issue type</option>
                        {(lookups.issue_types ?? []).map((option) => (
                          <option key={option.value} value={option.value}>
                            {option.label}
                          </option>
                        ))}
                      </Select>
                    )}
                  </FormField>

                  <FormField label="Item type" required error={fieldErrors.item_type}>
                    {({ id, hasError }) => (
                      <Select
                        id={id}
                        hasError={hasError}
                        value={values.item_type}
                        onChange={(event) => update('item_type', event.target.value)}
                        disabled={isSubmitting}
                      >
                        <option value="">Select an item type</option>
                        {(lookups.item_types ?? []).map((option) => (
                          <option key={option.value} value={option.value}>
                            {option.label}
                          </option>
                        ))}
                      </Select>
                    )}
                  </FormField>
                </div>

                {(values.issue_type === 'other' || values.item_type === 'other') && (
                  <FormField
                    label="Other details"
                    required
                    error={fieldErrors.item_other}
                    hint={`${values.item_other.length} / ${ITEM_OTHER_MAX} characters`}
                  >
                    {({ id, hasError }) => (
                      <Input
                        id={id}
                        hasError={hasError}
                        maxLength={ITEM_OTHER_MAX}
                        value={values.item_other}
                        onChange={(event) => update('item_other', event.target.value)}
                        placeholder="Describe the other issue or item"
                        disabled={isSubmitting}
                      />
                    )}
                  </FormField>
                )}
              </div>
            )}

            <FormField
              label="Description"
              error={fieldErrors.description}
              hint={`Optional · ${values.description.length} / ${DESCRIPTION_MAX} characters`}
            >
              {({ id, hasError }) => (
                <Textarea
                  id={id}
                  hasError={hasError}
                  rows={6}
                  maxLength={DESCRIPTION_MAX}
                  value={values.description}
                  onChange={(event) => update('description', event.target.value)}
                  placeholder="What happened, when it started, and what you have already tried. (optional)"
                  disabled={isSubmitting}
                />
              )}
            </FormField>

            <div className="grid gap-5 sm:grid-cols-2">
              <FormField
                label="Phone number"
                required
                error={fieldErrors.contact_phone}
                hint="IT will use this number to contact you about the ticket."
              >
                {({ id, hasError }) => (
                  <Input
                    id={id}
                    hasError={hasError}
                    type="tel"
                    inputMode="tel"
                    maxLength={30}
                    value={values.contact_phone}
                    onChange={(event) => update('contact_phone', event.target.value)}
                    placeholder="e.g. 077 123 4567"
                    disabled={isSubmitting}
                  />
                )}
              </FormField>

              <FormField label="Preferred contact method" error={fieldErrors.preferred_contact_method}>
                {({ id, hasError }) => (
                  <Select
                    id={id}
                    hasError={hasError}
                    value={values.preferred_contact_method}
                    onChange={(event) => update('preferred_contact_method', event.target.value)}
                    disabled={isSubmitting}
                  >
                    <option value="">No preference</option>
                    {lookups.contact_methods.map((method) => (
                      <option key={method.value} value={method.value}>
                        {method.label}
                      </option>
                    ))}
                  </Select>
                )}
              </FormField>
            </div>

            <div className="grid gap-5 sm:grid-cols-2">
              <FormField
                label="Branch"
                required
                error={fieldErrors.branch_id}
                hint="Where the issue is occurring."
              >
                {({ id, hasError }) => (
                  <Select
                    id={id}
                    hasError={hasError}
                    value={values.branch_id}
                    onChange={(event) => update('branch_id', event.target.value)}
                    disabled={isSubmitting}
                  >
                    <option value="">Select a branch</option>
                    {lookups.branches.map((branch) => (
                      <option key={branch.id} value={branch.id}>
                        {branch.name}
                        {branch.city ? ` · ${branch.city}` : ''}
                      </option>
                    ))}
                  </Select>
                )}
              </FormField>

              <FormField
                label="Location / desk"
                error={fieldErrors.contact_location}
                hint="Building, floor or desk number if relevant."
              >
                {({ id, hasError }) => (
                  <Input
                    id={id}
                    hasError={hasError}
                    maxLength={150}
                    value={values.contact_location}
                    onChange={(event) => update('contact_location', event.target.value)}
                    placeholder="e.g. Head Office, Desk A-12"
                    disabled={isSubmitting}
                  />
                )}
              </FormField>
            </div>

            <FormField
              label="Additional details"
              error={fieldErrors.additional_details}
              hint={`${values.additional_details.length} / ${DETAILS_MAX} characters`}
            >
              {({ id, hasError }) => (
                <Textarea
                  id={id}
                  hasError={hasError}
                  rows={4}
                  maxLength={DETAILS_MAX}
                  value={values.additional_details}
                  onChange={(event) => update('additional_details', event.target.value)}
                  placeholder="Asset tags, error messages or anything else that may help."
                  disabled={isSubmitting}
                />
              )}
            </FormField>

              <div className="space-y-1.5">
              <p className="text-sm font-medium text-surface-700">
                Attachments <span className="font-normal text-surface-500">(optional)</span>
              </p>
              <FileUpload
                files={files}
                onChange={setFiles}
                constraints={lookups.attachments}
                disabled={isSubmitting}
                error={fieldErrors.attachments ?? fieldErrors['attachments.0']}
                progress={uploadProgress}
              />
            </div>

            <div className="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
              <Button
                variant="outline"
                leadingIcon={<ArrowLeft className="size-4" />}
                onClick={() => void navigate(paths.dashboard)}
                disabled={isSubmitting}
              >
                Cancel
              </Button>
              <Button
                type="submit"
                isLoading={isSubmitting}
                leadingIcon={<TicketPlus className="size-4" />}
              >
                Submit ticket
              </Button>
            </div>
          </Form>
        </CardContent>
      </Card>
    </div>
  )
}
