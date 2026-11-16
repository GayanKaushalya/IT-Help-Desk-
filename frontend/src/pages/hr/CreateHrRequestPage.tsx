import { ArrowLeft, CheckCircle2, Lock, Send } from 'lucide-react'
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
import { HrStatusBadge } from '@/features/hr/HrStatusBadge'
import { useHrLookups, useInvalidateEmployeeHr } from '@/features/hr/useHr'
import { FileUpload, type SelectedFile } from '@/features/tickets/FileUpload'
import { useAuth } from '@/hooks/useAuth'
import { usePauseLiveRefresh } from '@/hooks/useLiveRefreshGuard'
import { useToast } from '@/hooks/useToast'
import { paths } from '@/routes/paths'
import { hrService } from '@/services/hrService'
import { ApiError } from '@/types/api'
import type { AttachmentConstraints, HrRequest } from '@/types/models'

const DESCRIPTION_MIN = 10
const DESCRIPTION_MAX = 4000

export function CreateHrRequestPage() {
  const navigate = useNavigate()
  const toast = useToast()
  const { user } = useAuth()
  const invalidate = useInvalidateEmployeeHr()
  const { data: lookups, isPending, isError, refetch, isRefetching } = useHrLookups()

  const [categoryId, setCategoryId] = useState('')
  const [typeId, setTypeId] = useState('')
  const [description, setDescription] = useState('')
  const [employeeId, setEmployeeId] = useState(user?.employee_id ?? '')
  const [employeeName, setEmployeeName] = useState(user?.full_name ?? '')
  const [departmentName, setDepartmentName] = useState(user?.department?.name ?? '')
  const [designation, setDesignation] = useState(user?.job_title ?? '')
  const [files, setFiles] = useState<SelectedFile[]>([])
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [uploadProgress, setUploadProgress] = useState<number | null>(null)
  const [created, setCreated] = useState<HrRequest | null>(null)

  usePauseLiveRefresh(description.trim().length > 0 || files.length > 0 || isSubmitting)

  useEffect(() => {
    if (!user) return
    setEmployeeId((current) => current || user.employee_id || '')
    setEmployeeName((current) => current || user.full_name || '')
    setDepartmentName((current) => current || user.department?.name || '')
    setDesignation((current) => current || user.job_title || '')
  }, [user])

  const selectedCategory = useMemo(
    () => lookups?.categories.find((category) => String(category.id) === categoryId),
    [lookups?.categories, categoryId],
  )
  const requestTypes = selectedCategory?.types ?? []
  const selectedType = requestTypes.find((type) => String(type.id) === typeId)

  const constraints: AttachmentConstraints = {
    max_kilobytes: lookups?.attachments.max_kilobytes ?? 10240,
    max_per_ticket: lookups?.attachments.max_per_request ?? 5,
    allowed_extensions: lookups?.attachments.allowed_extensions ?? ['jpg', 'jpeg', 'png', 'pdf'],
  }

  function clearError(field: string) {
    setFieldErrors((current) => {
      if (!current[field]) return current
      const next = { ...current }
      delete next[field]
      return next
    })
  }

  function validate(): Record<string, string> {
    const errors: Record<string, string> = {}
    const text = description.trim()

    if (!employeeId.trim()) errors.employee_id = 'Enter your employee ID.'
    if (!employeeName.trim()) errors.employee_name = 'Enter your name.'
    if (!categoryId) errors.category_id = 'Select a category.'
    if (!typeId) errors.request_type_id = 'Select a request type.'

    if (!text) {
      errors.description = 'Describe your request.'
    } else if (text.length < DESCRIPTION_MIN) {
      errors.description = `Please give a little more detail (at least ${DESCRIPTION_MIN} characters).`
    } else if (text.length > DESCRIPTION_MAX) {
      errors.description = `Description cannot exceed ${DESCRIPTION_MAX} characters.`
    }

    return errors
  }

  async function handleSubmit(_event: FormEvent<HTMLFormElement>): Promise<void> {
    setFormError(null)

    const clientErrors = validate()
    if (Object.keys(clientErrors).length > 0) {
      setFieldErrors(clientErrors)
      return
    }

    const body = new FormData()
    body.append('category_id', categoryId)
    body.append('request_type_id', typeId)
    body.append('description', description.trim())
    body.append('employee_id', employeeId.trim())
    body.append('employee_name', employeeName.trim())
    if (departmentName.trim()) body.append('department_name', departmentName.trim())
    if (designation.trim()) body.append('designation', designation.trim())
    files.forEach((item) => body.append('attachments[]', item.file))

    setIsSubmitting(true)
    setUploadProgress(files.length > 0 ? 0 : null)

    try {
      const request = await hrService.create(body, (percent) => setUploadProgress(percent))
      setCreated(request)
      toast.success('HR request submitted', request.reference)
      invalidate()
    } catch (error) {
      if (error instanceof ApiError && error.isValidationError) {
        setFieldErrors(error.fieldErrors)
        setFormError(error.message)
      } else if (error instanceof ApiError) {
        setFormError(error.message)
      } else {
        setFormError('The request could not be submitted. Please try again.')
      }
    } finally {
      setIsSubmitting(false)
      setUploadProgress(null)
    }
  }

  function resetForm() {
    files.forEach((item) => {
      if (item.previewUrl) URL.revokeObjectURL(item.previewUrl)
    })
    setCreated(null)
    setCategoryId('')
    setTypeId('')
    setDescription('')
    setEmployeeId(user?.employee_id ?? '')
    setEmployeeName(user?.full_name ?? '')
    setDepartmentName(user?.department?.name ?? '')
    setDesignation(user?.job_title ?? '')
    setFiles([])
    setFieldErrors({})
    setFormError(null)
  }

  if (isPending && !lookups) {
    return <LoadingPanel label="Loading HR request form..." />
  }

  if ((isError && !lookups) || !lookups) {
    return (
      <div className="space-y-6">
        <PageHeader title="Submit HR Request" description="Tell the HR team what you need help with." />
        <Card>
          <ErrorState
            title="Unable to load the request form"
            description="Categories and request types could not be loaded from the server."
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
        <PageHeader title="Submit HR Request" description="Your request has been received." />
        <Card>
          <CardContent className="py-8">
            <div className="flex flex-col items-center text-center">
              <span className="flex size-14 items-center justify-center rounded-full bg-success-50 text-success-500">
                <CheckCircle2 className="size-7" aria-hidden />
              </span>
              <h2 className="mt-4 text-lg font-semibold text-surface-900">Your HR request has been submitted.</h2>
              <p className="mt-1 text-sm text-surface-500">
                Keep this reference number for your records. The HR team will pick it up shortly.
              </p>
            </div>

            <dl className="mt-8 grid gap-4 rounded-xl border border-surface-200 bg-surface-50 px-5 py-4 sm:grid-cols-2">
              <div>
                <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">Reference Number</dt>
                <dd className="mt-1 font-mono text-sm font-semibold text-primary-700">{created.reference}</dd>
              </div>
              <div>
                <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">Request</dt>
                <dd className="mt-1 text-sm font-medium text-surface-900">
                  {created.request_type?.name ?? selectedType?.name ?? '\u2014'}
                </dd>
              </div>
              <div>
                <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">Category</dt>
                <dd className="mt-1 text-sm text-surface-900">{created.category?.name ?? selectedCategory?.name}</dd>
              </div>
              <div>
                <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">Status</dt>
                <dd className="mt-1">
                  <HrStatusBadge status={created.status} />
                </dd>
              </div>
            </dl>
          </CardContent>
          <CardFooter className="justify-between">
            <Button variant="ghost" onClick={resetForm}>
              Submit another request
            </Button>
            <Button onClick={() => void navigate(paths.hr.detail(created.id))}>View Request</Button>
          </CardFooter>
        </Card>
      </div>
    )
  }

  const submissionDate = new Date().toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <PageHeader
        eyebrow={
          <button
            type="button"
            onClick={() => void navigate(paths.hr.index)}
            className="text-xs font-medium text-primary-600 hover:text-primary-700"
          >
            &larr; HR Help Desk
          </button>
        }
        title="Submit HR Request"
        description="Choose a category and describe what you need so HR can help you faster."
      />

      <Card>
        <CardHeader
          title="Your details"
          description="Pre-filled from your profile — edit anything that is missing or out of date before you submit."
        />
        <CardContent>
          <div className="grid gap-5 sm:grid-cols-2">
            <FormField label="Employee ID" required error={fieldErrors.employee_id}>
              {({ id, hasError }) => (
                <Input
                  id={id}
                  hasError={hasError}
                  value={employeeId}
                  onChange={(event) => {
                    setEmployeeId(event.target.value)
                    clearError('employee_id')
                  }}
                  disabled={isSubmitting}
                />
              )}
            </FormField>
            <FormField label="Employee Name" required error={fieldErrors.employee_name}>
              {({ id, hasError }) => (
                <Input
                  id={id}
                  hasError={hasError}
                  value={employeeName}
                  onChange={(event) => {
                    setEmployeeName(event.target.value)
                    clearError('employee_name')
                  }}
                  disabled={isSubmitting}
                />
              )}
            </FormField>
            <FormField label="Department" error={fieldErrors.department_name}>
              {({ id, hasError }) => (
                <Input
                  id={id}
                  hasError={hasError}
                  value={departmentName}
                  onChange={(event) => {
                    setDepartmentName(event.target.value)
                    clearError('department_name')
                  }}
                  placeholder="e.g. Human Resources"
                  disabled={isSubmitting}
                />
              )}
            </FormField>
            <FormField label="Designation" error={fieldErrors.designation}>
              {({ id, hasError }) => (
                <Input
                  id={id}
                  hasError={hasError}
                  value={designation}
                  onChange={(event) => {
                    setDesignation(event.target.value)
                    clearError('designation')
                  }}
                  placeholder="e.g. Accounts Clerk"
                  disabled={isSubmitting}
                />
              )}
            </FormField>
            <FormField label="Branch / Location">
              {({ id }) => <Input id={id} value={user?.branch?.name ?? '\u2014'} readOnly disabled />}
            </FormField>
            <FormField label="Submission Date">
              {({ id }) => <Input id={id} value={submissionDate} readOnly disabled />}
            </FormField>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader title="Request information" description="Attachments are optional." />
        <CardContent>
          <Form onSubmit={handleSubmit} className="space-y-6">
            {formError && (
              <Alert tone="danger" title="The request could not be submitted">
                {formError}
              </Alert>
            )}

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
                    value={categoryId}
                    onChange={(event) => {
                      setCategoryId(event.target.value)
                      setTypeId('')
                      clearError('category_id')
                      clearError('request_type_id')
                    }}
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
                label="Request type"
                required
                error={fieldErrors.request_type_id}
                hint={selectedType?.description ?? undefined}
              >
                {({ id, hasError }) => (
                  <Select
                    id={id}
                    hasError={hasError}
                    value={typeId}
                    onChange={(event) => {
                      setTypeId(event.target.value)
                      clearError('request_type_id')
                    }}
                    disabled={isSubmitting || !selectedCategory}
                  >
                    <option value="">{selectedCategory ? 'Select a request type' : 'Select a category first'}</option>
                    {requestTypes.map((type) => (
                      <option key={type.id} value={type.id}>
                        {type.name}
                      </option>
                    ))}
                  </Select>
                )}
              </FormField>
            </div>

            {selectedCategory?.is_confidential && (
              <Alert tone="info" title="This request is confidential">
                <span className="inline-flex items-center gap-1.5">
                  <Lock className="size-3.5" aria-hidden />
                  Only authorised HR staff can see it.
                </span>
              </Alert>
            )}

            <FormField
              label="Description"
              required
              error={fieldErrors.description}
              hint={`${description.length} / ${DESCRIPTION_MAX} characters`}
            >
              {({ id, hasError }) => (
                <Textarea
                  id={id}
                  hasError={hasError}
                  rows={6}
                  maxLength={DESCRIPTION_MAX}
                  value={description}
                  onChange={(event) => {
                    setDescription(event.target.value)
                    clearError('description')
                  }}
                  placeholder="Describe your request with as much detail as you can."
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
                constraints={constraints}
                disabled={isSubmitting}
                error={fieldErrors.attachments ?? fieldErrors['attachments.0']}
                progress={uploadProgress}
              />
            </div>

            <div className="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
              <Button
                variant="outline"
                leadingIcon={<ArrowLeft className="size-4" />}
                onClick={() => void navigate(paths.hr.index)}
                disabled={isSubmitting}
              >
                Cancel
              </Button>
              <Button type="submit" isLoading={isSubmitting} leadingIcon={<Send className="size-4" />}>
                Submit request
              </Button>
            </div>
          </Form>
        </CardContent>
      </Card>
    </div>
  )
}
