import type { AttachmentConstraints, TicketCategory } from '@/types/models'

export const DESCRIPTION_MAX = 4000
export const DETAILS_MAX = 2000
export const SUBJECT_MAX = 200
export const SUBJECT_MIN = 5
export const LOCATION_MAX = 150
export const ITEM_OTHER_MAX = 200
export const PHONE_MAX = 30
export const PHONE_PATTERN = /^[0-9+\-\s()]+$/

export interface CreateTicketFormValues {
  subject: string
  category_id: string
  priority_id: string
  description: string
  preferred_contact_method: string
  contact_phone: string
  branch_id: string
  contact_location: string
  additional_details: string
  issue_type: string
  item_type: string
  item_other: string
}

export const EMPTY_TICKET_FORM: CreateTicketFormValues = {
  subject: '',
  category_id: '',
  priority_id: '',
  description: '',
  preferred_contact_method: '',
  contact_phone: '',
  branch_id: '',
  contact_location: '',
  additional_details: '',
  issue_type: '',
  item_type: '',
  item_other: '',
}

export function validateCreateTicket(
  values: CreateTicketFormValues,
  category?: TicketCategory | null,
): Record<string, string> {
  const errors: Record<string, string> = {}
  const subject = values.subject.trim()
  const description = values.description.trim()

  if (!subject) {
    errors.subject = 'Enter a subject for this request.'
  } else if (subject.length < SUBJECT_MIN) {
    errors.subject = `Subject must be at least ${SUBJECT_MIN} characters.`
  } else if (subject.length > SUBJECT_MAX) {
    errors.subject = `Subject cannot exceed ${SUBJECT_MAX} characters.`
  }

  if (!values.category_id) {
    errors.category_id = 'Select a category.'
  }

  if (!values.priority_id) {
    errors.priority_id = 'Select a priority.'
  }

  if (!values.branch_id) {
    errors.branch_id = 'Select a branch.'
  }

  if (description.length > DESCRIPTION_MAX) {
    errors.description = `Description cannot exceed ${DESCRIPTION_MAX} characters.`
  }

  const phone = values.contact_phone.trim()
  if (!phone) {
    errors.contact_phone = 'Enter a phone number so IT can contact you.'
  } else if (phone.length < 7 || phone.length > PHONE_MAX || !PHONE_PATTERN.test(phone)) {
    errors.contact_phone = 'Enter a valid phone number.'
  }

  if (values.contact_location.trim().length > LOCATION_MAX) {
    errors.contact_location = `Location cannot exceed ${LOCATION_MAX} characters.`
  }

  if (values.additional_details.trim().length > DETAILS_MAX) {
    errors.additional_details = `Additional details cannot exceed ${DETAILS_MAX} characters.`
  }

  if (category?.requires_item_details) {
    if (!values.issue_type) {
      errors.issue_type = 'Select an issue type.'
    }

    if (!values.item_type) {
      errors.item_type = 'Select an item type.'
    }

    const needsOther = values.issue_type === 'other' || values.item_type === 'other'
    const other = values.item_other.trim()

    if (needsOther && !other) {
      errors.item_other = 'Describe the other issue or item.'
    } else if (other.length > ITEM_OTHER_MAX) {
      errors.item_other = `Other details cannot exceed ${ITEM_OTHER_MAX} characters.`
    }
  }

  return errors
}

export function validateAttachment(
  file: File,
  constraints: AttachmentConstraints,
  existingCount: number,
): string | null {
  if (existingCount >= constraints.max_per_ticket) {
    return `A ticket may have at most ${constraints.max_per_ticket} attachments.`
  }

  const extension = file.name.includes('.') ? file.name.split('.').pop()?.toLowerCase() ?? '' : ''
  const allowed = constraints.allowed_extensions.map((item) => item.toLowerCase())

  if (!extension || !allowed.includes(extension)) {
    return `Allowed file types: ${constraints.allowed_extensions.join(', ')}.`
  }

  const maxBytes = constraints.max_kilobytes * 1024

  if (file.size > maxBytes) {
    return `Each file must be ${Math.round(constraints.max_kilobytes / 1024)} MB or smaller.`
  }

  return null
}
