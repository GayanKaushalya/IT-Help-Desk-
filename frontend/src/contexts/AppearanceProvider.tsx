import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'

import { AppearanceContext, type Theme } from '@/contexts/AppearanceContext'

const THEME_KEY = 'it-helpdesk.theme'
const SIDEBAR_KEY = 'it-helpdesk.sidebar-collapsed'

function readTheme(): Theme {
  try {
    const stored = localStorage.getItem(THEME_KEY)
    if (stored === 'dark' || stored === 'light') return stored
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
  } catch {
    return 'light'
  }
}

function readSidebarCollapsed(): boolean {
  try {
    return localStorage.getItem(SIDEBAR_KEY) === '1'
  } catch {
    return false
  }
}

function applyTheme(theme: Theme): void {
  document.documentElement.dataset.theme = theme
  document.documentElement.style.colorScheme = theme
}

export function AppearanceProvider({ children }: { children: ReactNode }) {
  const [theme, setThemeState] = useState<Theme>(() =>
    typeof document === 'undefined' ? 'light' : readTheme(),
  )
  const [sidebarCollapsed, setSidebarState] = useState<boolean>(() =>
    typeof document === 'undefined' ? false : readSidebarCollapsed(),
  )

  useEffect(() => {
    applyTheme(theme)
    try {
      localStorage.setItem(THEME_KEY, theme)
    } catch {
      /* ignore quota / private-mode failures */
    }
  }, [theme])

  useEffect(() => {
    try {
      localStorage.setItem(SIDEBAR_KEY, sidebarCollapsed ? '1' : '0')
    } catch {
      /* ignore quota / private-mode failures */
    }
  }, [sidebarCollapsed])

  const setTheme = useCallback((next: Theme) => {
    setThemeState(next)
  }, [])

  const toggleTheme = useCallback(() => {
    setThemeState((current) => (current === 'dark' ? 'light' : 'dark'))
  }, [])

  const setSidebarCollapsed = useCallback((collapsed: boolean) => {
    setSidebarState(collapsed)
  }, [])

  const toggleSidebar = useCallback(() => {
    setSidebarState((current) => !current)
  }, [])

  const value = useMemo(
    () => ({
      theme,
      setTheme,
      toggleTheme,
      sidebarCollapsed,
      setSidebarCollapsed,
      toggleSidebar,
    }),
    [theme, setTheme, toggleTheme, sidebarCollapsed, setSidebarCollapsed, toggleSidebar],
  )

  return <AppearanceContext.Provider value={value}>{children}</AppearanceContext.Provider>
}
