import { useEffect } from 'react'

/**
 * Marks the document with the active help desk (e.g. "hr") so the accent
 * tokens in index.css apply to portalled overlays such as modals and drawers
 * as well as the layout wrapper. Cleared again when the layout unmounts.
 */
export function useDeskTheme(desk: 'hr'): void {
  useEffect(() => {
    const root = document.documentElement
    const previous = root.dataset.desk
    root.dataset.desk = desk

    return () => {
      if (previous) {
        root.dataset.desk = previous
      } else {
        delete root.dataset.desk
      }
    }
  }, [desk])
}
