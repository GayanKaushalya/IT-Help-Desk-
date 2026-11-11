import { Eye, EyeOff } from 'lucide-react'
import { forwardRef, useState } from 'react'

import { Input, type InputProps } from '@/components/ui/Input'

export type PasswordInputProps = Omit<InputProps, 'type' | 'trailingElement'>

/**
 * Password field with a show/hide control.
 *
 * Visibility is local to the input so the plain value never has to travel
 * through form state just to toggle the type.
 */
export const PasswordInput = forwardRef<HTMLInputElement, PasswordInputProps>(
  function PasswordInput(props, ref) {
    const [visible, setVisible] = useState(false)

    return (
      <Input
        ref={ref}
        type={visible ? 'text' : 'password'}
        autoComplete={props.autoComplete ?? 'current-password'}
        trailingElement={
          <button
            type="button"
            onClick={() => setVisible((current) => !current)}
            aria-label={visible ? 'Hide password' : 'Show password'}
            className="rounded-md p-1 text-surface-400 transition-colors hover:bg-surface-100 hover:text-surface-700"
          >
            {visible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
          </button>
        }
        {...props}
      />
    )
  },
)
