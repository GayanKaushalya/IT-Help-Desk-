import { Activity, LifeBuoy, ShieldCheck, Ticket, Users } from 'lucide-react'
import { Outlet, useLocation } from 'react-router-dom'

import { ThemeToggle } from '@/components/layout/ThemeToggle'
import { env } from '@/config/env'
import { paths } from '@/routes/paths'
import { cn } from '@/utils/cn'

const HIGHLIGHTS = [
  { icon: Ticket, title: 'Track every request', copy: 'Raise, follow and verify tickets in one place.' },
  { icon: Users, title: 'Built for teams', copy: 'Branch, department and role aware from day one.' },
  { icon: ShieldCheck, title: 'Secure by design', copy: 'Role-based access with a complete audit trail.' },
]

/**
 * Split layout for unauthenticated screens: branded panel on the left and
 * the form on the right, collapsing to a single column on small screens.
 */
export function AuthLayout() {
  const { pathname } = useLocation()
  const isRegister = pathname === paths.auth.register

  return (
    <div className="flex min-h-screen bg-surface-50">
      <aside className="relative hidden w-[48%] flex-col justify-between overflow-hidden p-12 xl:w-1/2 lg:flex">
        {/* Indra night exterior as left-panel background */}
        <div className="absolute inset-0" aria-hidden>
          <img
            src="/images/indra-bg-night.jpg"
            alt=""
            className="h-full w-full object-cover object-[28%_center]"
            draggable={false}
          />
          <div className="absolute inset-0 bg-[#061224]/72" />
          <div className="absolute inset-0 bg-gradient-to-t from-[#040b18]/90 via-[#061224]/45 to-[#061224]/55" />
        </div>

        <div className="relative flex items-center gap-2.5">
          <span className="flex size-9 items-center justify-center rounded-lg bg-primary-600 shadow-card">
            <LifeBuoy className="size-5 text-white" />
          </span>
          <div>
            <p className="text-base font-semibold tracking-tight text-white">{env.appName}</p>
            <p className="text-[11px] tracking-wide text-white/60 uppercase">Service management</p>
          </div>
        </div>

        <div className="relative">
          <p className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/25 px-3 py-1 text-[11px] font-medium tracking-wide text-primary-200 uppercase backdrop-blur-sm">
            <Activity className="size-3.5" />
            Enterprise platform
          </p>

          <h2 className="mt-5 max-w-md text-3xl leading-tight font-semibold tracking-tight text-white drop-shadow">
            One place for every IT request across the organisation.
          </h2>
          <p className="mt-3 max-w-md text-sm leading-6 text-white/75">
            Employees raise tickets, IT staff resolve them, and every change is recorded. Built for
            branches, departments and controlled access.
          </p>

          <ul className="mt-10 space-y-5">
            {HIGHLIGHTS.map((highlight) => (
              <li key={highlight.title} className="flex gap-3.5">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl border border-white/15 bg-black/30 backdrop-blur-sm">
                  <highlight.icon className="size-4.5 text-primary-200" />
                </span>
                <div>
                  <p className="text-sm font-medium text-white">{highlight.title}</p>
                  <p className="mt-0.5 text-sm text-white/70">{highlight.copy}</p>
                </div>
              </li>
            ))}
          </ul>
        </div>

        <p className="relative text-xs text-white/55">
          &copy; {new Date().getFullYear()} {env.appName}. Internal use only.
        </p>
      </aside>

      <main
        id="main-content"
        className={cn(
          'relative flex w-full justify-center px-4 py-10 sm:px-6 sm:py-12 lg:w-[52%] lg:px-16 xl:w-1/2',
          isRegister ? 'items-start' : 'items-center',
        )}
      >
        <ThemeToggle className="absolute top-4 right-4" />
        <div className={cn('w-full', isRegister ? 'max-w-xl' : 'max-w-md')}>
          <Outlet />
        </div>
      </main>
    </div>
  )
}
