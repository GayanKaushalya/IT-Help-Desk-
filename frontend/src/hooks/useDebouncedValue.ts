import { useEffect, useRef, useState } from 'react'

export function useDebouncedValue<T>(value: T, delay = 350): T {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const timer = window.setTimeout(() => setDebounced(value), delay)
    return () => window.clearTimeout(timer)
  }, [value, delay])

  return debounced
}

/**
 * Local draft for a search box that commits to URL/query filters after pause.
 */
export function useDebouncedSearch(committed: string, onCommit: (value: string) => void, delay = 350) {
  const [draft, setDraft] = useState(committed)
  const onCommitRef = useRef(onCommit)
  const committedRef = useRef(committed)

  useEffect(() => {
    onCommitRef.current = onCommit
    committedRef.current = committed
  })

  useEffect(() => {
    setDraft(committed)
  }, [committed])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      if (draft !== committedRef.current) {
        onCommitRef.current(draft)
      }
    }, delay)

    return () => window.clearTimeout(timer)
  }, [draft, delay])

  return [draft, setDraft] as const
}
