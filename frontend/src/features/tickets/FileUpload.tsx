import { FileText, Image as ImageIcon, Trash2, Upload } from 'lucide-react'
import { useCallback, useEffect, useId, useRef, useState, type ChangeEvent, type DragEvent } from 'react'

import type { AttachmentConstraints } from '@/types/models'
import { formatFileSize } from '@/utils/format'
import { cn } from '@/utils/cn'

import { validateAttachment } from './createTicketValidation'

export interface SelectedFile {
  id: string
  file: File
  previewUrl: string | null
}

interface FileUploadProps {
  files: SelectedFile[]
  onChange: (files: SelectedFile[]) => void
  constraints: AttachmentConstraints
  disabled?: boolean
  error?: string
  progress?: number | null
}

const IMAGE_EXTENSIONS = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp'])

function fileExtension(name: string): string {
  return name.includes('.') ? (name.split('.').pop()?.toLowerCase() ?? '') : ''
}

function isImage(file: File): boolean {
  if (file.type.startsWith('image/')) return true

  return IMAGE_EXTENSIONS.has(fileExtension(file.name))
}

function nextFileId(file: File): string {
  const unique =
    typeof globalThis.crypto?.randomUUID === 'function'
      ? globalThis.crypto.randomUUID()
      : `${Date.now()}-${Math.random().toString(16).slice(2)}`

  return `${file.name}-${file.size}-${file.lastModified}-${unique}`
}

function toSelected(file: File): SelectedFile {
  return {
    id: nextFileId(file),
    file,
    previewUrl: isImage(file) ? URL.createObjectURL(file) : null,
  }
}

export function FileUpload({
  files,
  onChange,
  constraints,
  disabled = false,
  error,
  progress,
}: FileUploadProps) {
  const inputId = useId()
  const filesRef = useRef(files)
  filesRef.current = files
  const [isDragging, setIsDragging] = useState(false)
  const [localError, setLocalError] = useState<string | null>(null)

  useEffect(() => {
    return () => {
      filesRef.current.forEach((item) => {
        if (item.previewUrl) URL.revokeObjectURL(item.previewUrl)
      })
    }
  }, [])

  const addFiles = useCallback(
    (incoming: File[]) => {
      if (disabled || incoming.length === 0) return

      try {
        const next = [...files]
        let message: string | null = null

        for (const file of incoming) {
          const rejection = validateAttachment(file, constraints, next.length)

          if (rejection) {
            message = rejection
            continue
          }

          if (next.some((item) => item.file.name === file.name && item.file.size === file.size)) {
            continue
          }

          next.push(toSelected(file))
        }

        setLocalError(message)
        onChange(next)
      } catch {
        setLocalError('The file could not be added. Try another image or PDF.')
      }
    },
    [constraints, disabled, files, onChange],
  )

  function handleInput(event: ChangeEvent<HTMLInputElement>) {
    addFiles(Array.from(event.target.files ?? []))
    event.target.value = ''
  }

  function handleDrop(event: DragEvent<HTMLDivElement>) {
    event.preventDefault()
    setIsDragging(false)
    addFiles(Array.from(event.dataTransfer.files ?? []))
  }

  function removeFile(id: string) {
    const target = files.find((item) => item.id === id)
    if (target?.previewUrl) URL.revokeObjectURL(target.previewUrl)
    setLocalError(null)
    onChange(files.filter((item) => item.id !== id))
  }

  const accept = constraints.allowed_extensions.map((ext) => `.${ext}`).join(',')
  const shownError = error ?? localError

  return (
    <div className="space-y-3">
      <div
        onDragOver={(event) => {
          event.preventDefault()
          if (!disabled) setIsDragging(true)
        }}
        onDragLeave={() => setIsDragging(false)}
        onDrop={handleDrop}
        className={cn(
          'relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-8 text-center transition-colors',
          disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
          isDragging
            ? 'border-primary-400 bg-primary-50'
            : shownError
              ? 'border-danger-300 bg-danger-50/40'
              : 'border-surface-300 bg-surface-50 hover:border-primary-300 hover:bg-primary-50/40',
        )}
      >
        <input
          id={inputId}
          type="file"
          multiple
          accept={accept}
          className="absolute inset-0 z-10 cursor-pointer opacity-0 disabled:cursor-not-allowed"
          disabled={disabled}
          onChange={handleInput}
          aria-label="Attach files"
        />
        <span className="pointer-events-none flex size-11 items-center justify-center rounded-full bg-panel text-primary-600 shadow-sm">
          <Upload className="size-5" aria-hidden />
        </span>
        <span className="pointer-events-none mt-3 text-sm font-medium text-surface-800">
          Drop files here or click to browse
        </span>
        <span className="pointer-events-none mt-1 text-xs text-surface-500">
          Up to {constraints.max_per_ticket} files, {Math.round(constraints.max_kilobytes / 1024)} MB
          each. Allowed: {constraints.allowed_extensions.join(', ')}
        </span>
      </div>

      {shownError && <p className="text-xs text-danger-600">{shownError}</p>}

      {progress != null && progress < 100 && (
        <div className="space-y-1.5">
          <div className="flex items-center justify-between text-xs text-surface-500">
            <span>Uploading attachments…</span>
            <span>{progress}%</span>
          </div>
          <div className="h-1.5 overflow-hidden rounded-full bg-surface-200">
            <div
              className="h-full rounded-full bg-primary-600 transition-[width] duration-150"
              style={{ width: `${progress}%` }}
            />
          </div>
        </div>
      )}

      {files.length > 0 && (
        <ul className="space-y-2">
          {files.map((item) => (
            <li
              key={item.id}
              className="flex items-center gap-3 rounded-lg border border-surface-200 bg-panel px-3 py-2"
            >
              {item.previewUrl ? (
                <img
                  src={item.previewUrl}
                  alt=""
                  className="size-11 rounded-md object-cover"
                />
              ) : (
                <span className="flex size-11 items-center justify-center rounded-md bg-surface-100 text-surface-500">
                  {item.file.type.startsWith('image/') ? (
                    <ImageIcon className="size-4" />
                  ) : (
                    <FileText className="size-4" />
                  )}
                </span>
              )}

              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-surface-800">{item.file.name}</p>
                <p className="text-xs text-surface-500">{formatFileSize(item.file.size)}</p>
              </div>

              <button
                type="button"
                onClick={() => removeFile(item.id)}
                disabled={disabled}
                className="rounded-md p-1.5 text-surface-400 hover:bg-danger-50 hover:text-danger-600 disabled:opacity-50"
                aria-label={`Remove ${item.file.name}`}
              >
                <Trash2 className="size-4" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
