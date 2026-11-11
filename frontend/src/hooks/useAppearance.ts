import { useContext } from 'react'

import { AppearanceContext } from '@/contexts/AppearanceContext'

export function useAppearance() {
  const context = useContext(AppearanceContext)

  if (!context) {
    throw new Error('useAppearance must be used inside an AppearanceProvider.')
  }

  return context
}
