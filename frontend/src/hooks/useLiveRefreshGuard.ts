import { useCallback, useEffect, useSyncExternalStore } from 'react'

/**
 * Pauses background ticket/dashboard polling while someone is typing or has
 * unsaved draft text / selected files, so a refetch cannot remount UI and
 * wipe what they were entering.
 */

let pauseCount = 0
const listeners = new Set<() => void>()

function emit(): void {
  listeners.forEach((listener) => listener())
}

function subscribe(listener: () => void): () => void {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

function getPausedSnapshot(): boolean {
  return pauseCount > 0 || isEditingField(document.activeElement)
}

function getServerSnapshot(): boolean {
  return false
}

function isEditingField(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false

  const tag = target.tagName
  if (tag === 'TEXTAREA' || tag === 'SELECT') return true
  if (tag === 'INPUT') {
    const type = (target as HTMLInputElement).type
    return !['button', 'submit', 'reset', 'checkbox', 'radio', 'file', 'hidden'].includes(type)
  }

  return target.isContentEditable
}

/** Hold a pause while a modal/drawer is open or a draft is non-empty. */
export function acquireLiveRefreshPause(): () => void {
  pauseCount += 1
  emit()

  return () => {
    pauseCount = Math.max(0, pauseCount - 1)
    emit()
  }
}

export function useLiveRefreshPaused(): boolean {
  return useSyncExternalStore(subscribe, getPausedSnapshot, getServerSnapshot)
}

/** Keep live refresh paused for the lifetime of a condition (open modal, draft, files). */
export function usePauseLiveRefresh(active: boolean): void {
  useEffect(() => {
    if (!active) return
    return acquireLiveRefreshPause()
  }, [active])
}

/** Wire focus tracking once near the app root so typing pauses polling. */
export function useLiveRefreshFocusTracking(): void {
  const notify = useCallback(() => emit(), [])

  useEffect(() => {
    document.addEventListener('focusin', notify)
    document.addEventListener('focusout', notify)

    return () => {
      document.removeEventListener('focusin', notify)
      document.removeEventListener('focusout', notify)
    }
  }, [notify])
}
