import { Activity, Handshake, LifeBuoy, ShieldCheck, Ticket, Users, Zap } from 'lucide-react'
import { Outlet, useLocation } from 'react-router-dom'

import { ThemeToggle } from '@/components/layout/ThemeToggle'
import { env } from '@/config/env'
import { paths } from '@/routes/paths'
import { cn } from '@/utils/cn'

const LOGIN_VALUE_PROPS = [
  { icon: Zap, label: 'Fast Support' },
  { icon: ShieldCheck, label: 'Secure & Reliable' },
  { icon: Handshake, label: 'Always Here for You' },
] as const

const REGISTER_HIGHLIGHTS = [
  { icon: Ticket, title: 'Track every request', copy: 'Raise, follow and verify tickets in one place.' },
  { icon: Users, title: 'Built for teams', copy: 'Branch, department and role aware from day one.' },
  { icon: ShieldCheck, title: 'Secure by design', copy: 'Role-based access with a complete audit trail.' },
]

/**
 * Auth shell: Indra full-bleed login, classic split layout for registration.
 */
export function AuthLayout() {
  const { pathname } = useLocation()
  const isRegister = pathname === paths.auth.register

  if (isRegister) {
    return <RegisterAuthShell />
  }

  return <LoginAuthShell />
}

function LoginAuthShell() {
  return (
    <div className="auth-shell relative min-h-screen overflow-hidden bg-[#040b18] text-white">
      <div className="absolute inset-0" aria-hidden>
        <img
          src="/images/indra-bg-night.jpg"
          alt=""
          className="h-full w-full object-cover object-[52%_center] scale-105"
          draggable={false}
        />
        <div className="absolute inset-0 bg-[#040b18]/45" />
        <div className="absolute inset-0 bg-gradient-to-r from-[#040b18]/88 via-[#040b18]/55 to-[#040b18]/35" />
        <div className="absolute inset-0 bg-gradient-to-t from-[#040b18]/85 via-transparent to-[#040b18]/40" />
      </div>

      <div className="auth-swoosh auth-swoosh--top" aria-hidden />
      <div className="auth-swoosh auth-swoosh--bottom" aria-hidden />

      <ThemeToggle className="absolute top-4 right-4 z-20 border-white/15 bg-black/30 text-white hover:bg-black/45 hover:text-white" />

      <div className="relative z-10 mx-auto flex min-h-screen w-full max-w-7xl flex-col gap-10 px-5 py-10 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:gap-12 lg:px-12 lg:py-12">
        <section className="flex max-w-xl flex-1 flex-col justify-center">
          <p className="auth-fade-up text-sm font-medium tracking-[0.22em] text-white/70 uppercase">
            Welcome to
          </p>
          <h1 className="auth-fade-up auth-fade-up--delay-1 mt-3 font-auth-display text-4xl leading-[1.05] font-semibold tracking-tight sm:text-5xl lg:text-[3.4rem]">
            <span className="text-[#e11d2e]">Indra</span> <span className="text-white">Help Desk</span>
          </h1>
          <p className="auth-fade-up auth-fade-up--delay-2 mt-4 max-w-md text-base text-white/75 italic sm:text-lg">
            One Platform. Every Support Service.
          </p>
          <p className="auth-fade-up auth-fade-up--delay-3 mt-5 max-w-md text-sm leading-6 text-white/65">
            Sign in once for IT and HR support — raise tickets, track requests, and get help from the
            right team.
          </p>

          <ul className="auth-fade-up auth-fade-up--delay-4 mt-10 flex flex-wrap gap-3">
            {LOGIN_VALUE_PROPS.map(({ icon: Icon, label }) => (
              <li
                key={label}
                className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/30 px-3.5 py-2 text-xs font-medium tracking-wide text-white/90 backdrop-blur-md"
              >
                <Icon className="size-3.5 text-[#5ec8ff]" aria-hidden />
                {label}
              </li>
            ))}
          </ul>
        </section>

        <main id="main-content" className="auth-fade-up auth-fade-up--delay-2 mx-auto flex w-full max-w-md justify-center lg:mx-0 lg:justify-end">
          <div className="w-full">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  )
}

function RegisterAuthShell() {
  return (
    <div className="flex min-h-screen bg-surface-50">
      <aside className="relative hidden w-[48%] flex-col justify-between overflow-hidden p-12 xl:w-1/2 lg:flex">
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
            One place for every support request across the organisation.
          </h2>
          <p className="mt-3 max-w-md text-sm leading-6 text-white/75">
            Employees raise IT and HR requests, staff resolve them, and every change is recorded. Built
            for branches, departments and controlled access.
          </p>

          <ul className="mt-10 space-y-5">
            {REGISTER_HIGHLIGHTS.map((highlight) => (
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
          'relative flex w-full items-start justify-center px-4 py-10 sm:px-6 sm:py-12 lg:w-[52%] lg:px-16 xl:w-1/2',
        )}
      >
        <ThemeToggle className="absolute top-4 right-4" />
        <div className="w-full max-w-xl">
          <Outlet />
        </div>
      </main>
    </div>
  )
}
