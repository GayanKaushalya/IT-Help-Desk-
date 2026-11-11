import { useState } from 'react'

import { Button } from '@/components/ui/Button'
import { FileUpload, type SelectedFile } from '@/features/tickets/FileUpload'
import { TicketAttachments } from '@/features/tickets/TicketAttachments'
import { useStaffTicketAttachment } from '@/features/admin/useStaffDashboard'
import { useLookups } from '@/features/system/useSystemStatus'
import { usePauseLiveRefresh } from '@/hooks/useLiveRefreshGuard'
import { useToast } from '@/hooks/useToast'
import { ApiError } from '@/types/api'
import type { Ticket } from '@/types/models'

export function StaffAttachments({ ticket }: { ticket: Ticket }) {
  const toast = useToast()
  const { data: lookups } = useLookups()
  const upload = useStaffTicketAttachment(String(ticket.id))
  const [files, setFiles] = useState<SelectedFile[]>([])
  const [progress, setProgress] = useState<number | null>(null)

  usePauseLiveRefresh(files.length > 0 || progress !== null)

  const constraints = lookups?.attachments ?? {
    max_kilobytes: 5120,
    max_per_ticket: 5,
    allowed_extensions: ['png', 'jpg', 'jpeg', 'pdf', 'docx'],
  }

  async function handleUpload(): Promise<void> {
    if (files.length === 0) return

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
      toast.success('Attachment uploaded')
    } catch (error) {
      setProgress(null)
      toast.error('Upload failed', error instanceof ApiError ? error.message : 'Please try again.')
    }
  }

  return (
    <div className="space-y-4">
      <TicketAttachments attachments={ticket.attachments ?? []} />

      {!ticket.status?.is_closed && (
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
