import { createContext } from 'react'

export type Theme = 'light' | 'dark'

export interface AppearanceContextValue {
  theme: Theme
  setTheme: (theme: Theme) => void
  toggleTheme: () => void
  sidebarCollapsed: boolean
  setSidebarCollapsed: (collapsed: boolean) => void
  toggleSidebar: () => void
}

export const AppearanceContext = createContext<AppearanceContextValue | null>(null)
