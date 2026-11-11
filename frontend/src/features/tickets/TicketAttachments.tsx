import { Download, Paperclip } from 'lucide-react'
import { useState } from 'react'

import { Button } from '@/components/ui/Button'
import { FileUpload, type SelectedFile } from '@/features/tickets/FileUpload'
import { useAddTicketAttachment } from '@/features/tickets/useTickets'
import { useLookups } from '@/features/system/useSystemStatus'
import { useAuth } from '@/hooks/useAuth'
import { usePauseLiveRefresh } from '@/hooks/useLiveRefreshGuard'
import { useToast } from '@/hooks/useToast'
import { ticketService } from '@/services/ticketService'
import { ApiError } from '@/types/api'
import type { Ticket, TicketAttachment } from '@/types/models'
import { formatFileSize } from '@/utils/format'

interface TicketAttachmentsProps {
  ticket?: Ticket
  attachments: TicketAttachment[]
  allowUpload?: boolean
}

export function TicketAttachments({ ticket, attachments, allowUpload = false }: TicketAttachmentsProps) {
  const { hasPermission } = useAuth()
  const toast = useToast()
  const { data: lookups } = useLookups()
  const upload = useAddTicketAttachment(ticket ? String(ticket.id) : '')
  const [files, setFiles] = useState<SelectedFile[]>([])
  const [progress, setProgress] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [downloading, setDownloading] = useState<number | null>(null)

  usePauseLiveRefresh(files.length > 0 || progress !== null)

  const canUpload =
    allowUpload &&
    Boolean(ticket) &&
    hasPermission('tickets.comment') &&
    !ticket?.status?.is_closed

  const constraints = lookups?.attachments ?? {
    max_kilobytes: 10240,
    max_per_ticket: 10,
    allowed_extensions: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'],
  }

  async function download(file: TicketAttachment): Promise<void> {
    setError(null)
    setDownloading(file.id)

    try {
      await ticketService.downloadAttachment(file.download_url, file.original_name)
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : 'The file could not be downloaded. Please try again.')
    } finally {
      setDownloading(null)
    }
  }

  async function handleUpload(): Promise<void> {
    if (!ticket || files.length === 0) return

    try {
      await upload.mutateAsync({
        files: files.map((item) => item.file),
        onProgress: setProgress,
      })
      files.forEach((item) => {
        if (item.previewUrl) URL.revokeObjectURL(item.previewUrl)
      })
      setFiles([])
      setProgress(null)
      toast.success('Attachment uploaded', 'IT support can now see the file on this ticket.')
    } catch (cause) {
      setProgress(null)
      toast.error('Upload failed', cause instanceof ApiError ? cause.message : 'Please try again.')
    }
  }

  return (
    <div className="space-y-4">
      {error && <p className="text-xs text-danger-600">{error}</p>}
      {attachments.length === 0 ? (
        <p className="text-sm text-surface-500">No files were attached to this ticket.</p>
      ) : (
        <ul className="space-y-2">
          {attachments.map((file) => (
            <li
              key={file.id}
              className="flex items-center justify-between gap-3 rounded-lg border border-surface-200 px-3 py-2"
            >
              <div className="flex min-w-0 items-center gap-3">
                <span className="flex size-9 items-center justify-center rounded-md bg-surface-100 text-surface-500">
                  <Paperclip className="size-4" />
                </span>
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium text-surface-800">{file.original_name}</p>
                  <p className="text-xs text-surface-500">{formatFileSize(file.size_bytes)}</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => void download(file)}
                disabled={downloading === file.id}
                className="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium text-primary-700 hover:bg-primary-50 disabled:opacity-50"
              >
                <Download className="size-3.5" />
                {downloading === file.id ? 'Downloading…' : 'Download'}
              </button>
            </li>
          ))}
        </ul>
      )}

      {canUpload && (
        <div className="space-y-3 border-t border-surface-200 pt-4">
          <FileUpload
            files={files}
            onChange={setFiles}
            constraints={constraints}
            disabled={upload.isPending}
            progress={progress}
          />
          <Button
            variant="outline"
            disabled={files.length === 0}
            isLoading={upload.isPending}
            onClick={() => void handleUpload()}
          >
            Upload attachments
          </Button>
        </div>
      )}
    </div>
  )
}
