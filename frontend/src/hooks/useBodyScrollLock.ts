import { useEffect } from 'react'

let lockCount = 0
let previousOverflow: string | null = null

/**
 * Locks document scrolling while any modal or drawer is open.
 * Nested overlays share one lock so closing the top one cannot leave
 * overflow:hidden behind.
 */
export function useBodyScrollLock(locked: boolean): void {
  useEffect(() => {
    if (!locked) return

    if (lockCount === 0) {
      previousOverflow = document.body.style.overflow
      document.body.style.overflow = 'hidden'
    }

    lockCount += 1

    return () => {
      lockCount = Math.max(0, lockCount - 1)

      if (lockCount === 0) {
        document.body.style.overflow = previousOverflow ?? ''
        previousOverflow = null
      }
    }
  }, [locked])
}
