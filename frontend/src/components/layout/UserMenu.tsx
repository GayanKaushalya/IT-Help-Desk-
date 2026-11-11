import { ChevronDown, LogOut, UserRound } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { Avatar } from '@/components/ui/Avatar'
import { useAuth } from '@/hooks/useAuth'
import { paths } from '@/routes/paths'

export interface UserMenuProps {
  /** Profile route for the active help desk shell. */
  profilePath?: string
}

export function UserMenu({ profilePath = paths.profile }: UserMenuProps) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [isOpen, setOpen] = useState(false)
  const [isSigningOut, setSigningOut] = useState(false)
  const container = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!isOpen) return

    function handlePointer(event: MouseEvent) {
      if (!container.current?.contains(event.target as Node)) {
        setOpen(false)
      }
    }

    function handleKey(event: KeyboardEvent) {
      if (event.key === 'Escape') setOpen(false)
    }

    document.addEventListener('mousedown', handlePointer)
    document.addEventListener('keydown', handleKey)

    return () => {
      document.removeEventListener('mousedown', handlePointer)
      document.removeEventListener('keydown', handleKey)
    }
  }, [isOpen])

  if (!user) return null

  async function handleSignOut(): Promise<void> {
    setSigningOut(true)
    await logout()
    navigate(paths.auth.login, { replace: true })
  }

  return (
    <div ref={container} className="relative">
      <button
        type="button"
        onClick={() => setOpen((current) => !current)}
        aria-label={`Account menu for ${user.full_name}`}
        aria-expanded={isOpen}
        aria-haspopup="menu"
        className="flex items-center gap-2 rounded-lg px-1.5 py-1 transition-colors hover:bg-surface-100"
      >
        <Avatar name={user.full_name} size="sm" />
        <div className="hidden text-left leading-tight sm:block">
          <p className="text-sm font-medium text-surface-900">{user.full_name}</p>
          <p className="text-xs text-surface-500">{user.role?.name ?? 'Employee'}</p>
        </div>
        <ChevronDown className="hidden size-4 text-surface-400 sm:block" />
      </button>

      {isOpen && (
        <div
          role="menu"
          className="absolute top-full right-0 z-30 mt-2 w-56 animate-scale-in rounded-lg border border-surface-200 bg-panel py-1 shadow-overlay"
        >
          <div className="border-b border-surface-100 px-3 py-2">
            <p className="truncate text-sm font-medium text-surface-900">{user.full_name}</p>
            <p className="truncate text-xs text-surface-500">@{user.username}</p>
          </div>

          <button
            type="button"
            role="menuitem"
            onClick={() => {
              setOpen(false)
              void navigate(profilePath)
            }}
            className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-surface-700 transition-colors hover:bg-surface-50"
          >
            <UserRound className="size-4 text-surface-400" />
            My profile
          </button>

          <button
            type="button"
            role="menuitem"
            disabled={isSigningOut}
            onClick={() => void handleSignOut()}
            className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-danger-700 transition-colors hover:bg-danger-50 disabled:opacity-60"
          >
            <LogOut className="size-4" />
            {isSigningOut ? 'Signing out…' : 'Sign out'}
          </button>
        </div>
      )}
    </div>
  )
}
