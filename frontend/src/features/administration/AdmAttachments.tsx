import { Download, Paperclip } from 'lucide-react'
import { useState } from 'react'

import { ticketService } from '@/services/ticketService'
import { ApiError } from '@/types/api'
import type { AdmAttachment } from '@/types/models'
import { formatFileSize } from '@/utils/format'

export function AdmAttachments({ attachments }: { attachments: AdmAttachment[] }) {
  const [downloading, setDownloading] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)

  async function download(file: AdmAttachment): Promise<void> {
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

  return (
    <div className="space-y-3">
      {error && <p className="text-xs text-danger-600">{error}</p>}

      {attachments.length === 0 ? (
        <p className="text-sm text-surface-500">No files were attached to this request.</p>
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
                {downloading === file.id ? 'Downloading...' : 'Download'}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
