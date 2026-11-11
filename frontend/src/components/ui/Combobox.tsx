import { Check, ChevronsUpDown } from 'lucide-react'
import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent } from 'react'

import { cn } from '@/utils/cn'

export interface ComboboxOption {
  value: string
  label: string
}

export interface ComboboxProps {
  id?: string
  value: string
  onChange: (value: string) => void
  options: ComboboxOption[]
  placeholder?: string
  emptyLabel?: string
  allowEmpty?: boolean
  disabled?: boolean
  hasError?: boolean
  className?: string
}

export function Combobox({
  id,
  value,
  onChange,
  options,
  placeholder = 'Select…',
  emptyLabel = 'No matches',
  allowEmpty = true,
  disabled = false,
  hasError = false,
  className,
}: ComboboxProps) {
  const generatedId = useId()
  const fieldId = id ?? generatedId
  const listId = `${fieldId}-list`
  const container = useRef<HTMLDivElement>(null)
  const searchRef = useRef<HTMLInputElement>(null)
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [activeIndex, setActiveIndex] = useState(0)

  const allOptions = useMemo(() => {
    if (!allowEmpty || options.some((option) => option.value === '')) return options
    return [{ value: '', label: placeholder }, ...options]
  }, [allowEmpty, options, placeholder])

  const selected = allOptions.find((option) => option.value === value)

  const filtered = useMemo(() => {
    const term = query.trim().toLowerCase()
    if (!term) return allOptions
    return allOptions.filter((option) => option.label.toLowerCase().includes(term))
  }, [allOptions, query])

  useEffect(() => {
    if (!open) return

    searchRef.current?.focus()
    setActiveIndex(0)

    function handlePointer(event: MouseEvent) {
      if (!container.current?.contains(event.target as Node)) {
        setOpen(false)
        setQuery('')
      }
    }

    document.addEventListener('mousedown', handlePointer)
    return () => document.removeEventListener('mousedown', handlePointer)
  }, [open])

  function choose(next: string): void {
    onChange(next)
    setOpen(false)
    setQuery('')
  }

  function handleKey(event: KeyboardEvent<HTMLElement>): void {
    if (event.key === 'Escape') {
      event.preventDefault()
      setOpen(false)
      setQuery('')
      return
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault()
      if (!open) {
        setOpen(true)
        return
      }
      setActiveIndex((current) => Math.min(current + 1, Math.max(filtered.length - 1, 0)))
      return
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault()
      setActiveIndex((current) => Math.max(current - 1, 0))
      return
    }

    if (event.key === 'Enter' && open) {
      event.preventDefault()
      const option = filtered[activeIndex]
      if (option) choose(option.value)
    }
  }

  return (
    <div ref={container} className={cn('relative', className)}>
      <button
        id={fieldId}
        type="button"
        disabled={disabled}
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={listId}
        aria-invalid={hasError || undefined}
        onClick={() => {
          if (disabled) return
          setOpen((current) => !current)
          setQuery('')
        }}
        onKeyDown={handleKey}
        className={cn(
          'flex h-10 w-full items-center justify-between gap-2 rounded-lg border bg-panel px-3 text-left text-sm shadow-sm',
          'transition-colors duration-150',
          'focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none',
          'disabled:cursor-not-allowed disabled:bg-surface-50 disabled:text-surface-400',
          hasError ? 'border-danger-500' : 'border-surface-300',
          selected ? 'text-surface-900' : 'text-surface-400',
        )}
      >
        <span className="truncate">{selected?.label ?? placeholder}</span>
        <ChevronsUpDown className="size-4 shrink-0 text-surface-400" aria-hidden />
      </button>

      {open && (
        <div className="absolute z-40 mt-1 w-full overflow-hidden rounded-lg border border-surface-200 bg-panel shadow-overlay">
          <div className="border-b border-surface-200 p-2">
            <input
              ref={searchRef}
              type="search"
              value={query}
              onChange={(event) => {
                setQuery(event.target.value)
                setActiveIndex(0)
              }}
              onKeyDown={handleKey}
              placeholder="Search…"
              className="h-8 w-full rounded-md border border-surface-200 bg-panel px-2 text-sm text-surface-900 outline-none focus:border-primary-500"
            />
          </div>
          <ul id={listId} role="listbox" className="max-h-56 overflow-auto py-1">
            {filtered.length === 0 ? (
              <li className="px-3 py-2 text-sm text-surface-500">{emptyLabel}</li>
            ) : (
              filtered.map((option, index) => {
                const isSelected = option.value === value
                const isActive = index === activeIndex

                return (
                  <li key={option.value} role="option" aria-selected={isSelected}>
                    <button
                      type="button"
                      onMouseEnter={() => setActiveIndex(index)}
                      onClick={() => choose(option.value)}
                      className={cn(
                        'flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm',
                        isActive ? 'bg-primary-50 text-primary-800' : 'text-surface-700',
                      )}
                    >
                      <span className="truncate">{option.label}</span>
                      {isSelected && <Check className="size-4 shrink-0 text-primary-600" />}
                    </button>
                  </li>
                )
              })
            )}
          </ul>
        </div>
      )}
    </div>
  )
}
