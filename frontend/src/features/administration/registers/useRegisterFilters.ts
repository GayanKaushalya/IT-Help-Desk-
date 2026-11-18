import { useEffect, useState } from 'react'

import { useDebouncedValue } from '@/hooks/useDebouncedValue'

/**
 * Filter, search and page state shared by the register list screens. Changing a
 * filter or the (debounced) search returns to page one.
 */
export function useRegisterFilters<T extends Record<string, string>>(defaults: T, perPage = 15) {
  const [filters, setFilters] = useState<T>(defaults)
  const [page, setPage] = useState(1)
  const [searchDraft, setSearchDraft] = useState('')
  const search = useDebouncedValue(searchDraft)

  useEffect(() => {
    setPage(1)
  }, [search])

  return {
    filters,
    setFilter: (name: keyof T & string, value: string) => {
      setFilters((current) => ({ ...current, [name]: value }))
      setPage(1)
    },
    searchDraft,
    setSearchDraft,
    search,
    page,
    setPage,
    perPage,
  }
}
