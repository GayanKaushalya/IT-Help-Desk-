import { assessPassword } from '@/features/auth/registrationValidation'
import { cn } from '@/utils/cn'

const CHECK_LABELS: { key: keyof ReturnType<typeof assessPassword>['checks']; label: string }[] = [
  { key: 'length', label: '10+ characters' },
  { key: 'lower', label: 'Lowercase' },
  { key: 'upper', label: 'Uppercase' },
  { key: 'number', label: 'Number' },
  { key: 'symbol', label: 'Symbol' },
]

const TONE_BY_SCORE = ['bg-surface-200', 'bg-danger-500', 'bg-danger-500', 'bg-warning-500', 'bg-warning-500', 'bg-success-500']
const TEXT_BY_SCORE = [
  'text-surface-400',
  'text-danger-600',
  'text-danger-600',
  'text-warning-700',
  'text-warning-700',
  'text-success-700',
]

export function PasswordStrength({ password }: { password: string }) {
  const { checks, score, label } = assessPassword(password)

  return (
    <div className="space-y-2">
      <div className="flex gap-1" aria-hidden>
        {Array.from({ length: 5 }).map((_, index) => (
          <span
            key={index}
            className={cn(
              'h-1.5 flex-1 rounded-full transition-colors',
              index < score ? TONE_BY_SCORE[score] : 'bg-surface-200',
            )}
          />
        ))}
      </div>

      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className={cn('text-xs font-medium', password ? TEXT_BY_SCORE[score] : 'text-surface-400')}>
          {password ? label : 'Password strength'}
        </p>
        <ul className="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-surface-500">
          {CHECK_LABELS.map((item) => (
            <li key={item.key} className={cn(checks[item.key] && 'text-success-700')}>
              {item.label}
            </li>
          ))}
        </ul>
      </div>
    </div>
  )
}
