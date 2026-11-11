import { Input } from '@/components/ui/Input'
import { cn } from '@/utils/cn'

export function DateRangeField({
  from,
  to,
  onFromChange,
  onToChange,
  fromId,
  toId,
  disabled = false,
  className,
}: {
  from: string
  to: string
  onFromChange: (value: string) => void
  onToChange: (value: string) => void
  fromId?: string
  toId?: string
  disabled?: boolean
  className?: string
}) {
  return (
    <div className={cn('grid grid-cols-2 gap-2', className)}>
      <Input
        id={fromId}
        type="date"
        value={from}
        max={to || undefined}
        disabled={disabled}
        aria-label="From date"
        onChange={(event) => onFromChange(event.target.value)}
      />
      <Input
        id={toId}
        type="date"
        value={to}
        min={from || undefined}
        disabled={disabled}
        aria-label="To date"
        onChange={(event) => onToChange(event.target.value)}
      />
    </div>
  )
}
