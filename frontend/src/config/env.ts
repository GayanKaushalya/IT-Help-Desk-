/**
 * Typed access to the Vite environment.
 *
 * Reading `import.meta.env` in one place means a missing variable fails at
 * startup with a clear message rather than as a confusing runtime error.
 */

function required(name: string, value: string | undefined): string {
  if (!value) {
    throw new Error(`Missing environment variable: ${name}. Copy .env.example to .env.`)
  }

  return value
}

export const env = {
  appName: import.meta.env['VITE_APP_NAME'] ?? 'Indra Help Desk',
  apiBaseUrl: required('VITE_API_BASE_URL', import.meta.env['VITE_API_BASE_URL']),
  isDevelopment: import.meta.env.DEV,
} as const
