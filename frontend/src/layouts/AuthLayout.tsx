import { Handshake, ShieldCheck, Zap } from 'lucide-react'
import { Outlet, useLocation } from 'react-router-dom'

import { ThemeToggle } from '@/components/layout/ThemeToggle'
import { paths } from '@/routes/paths'
import { cn } from '@/utils/cn'

const VALUE_PROPS = [
  { icon: Zap, label: 'Fast Support' },
  { icon: ShieldCheck, label: 'Secure & Reliable' },
  { icon: Handshake, label: 'Always Here for You' },
] as const

/**
 * Full-bleed Indra Help Desk shell for sign-in and registration.
 * One entrance for IT and HR — desk choice happens after authentication.
 */
export function AuthLayout() {
  const { pathname } = useLocation()
  const isRegister = pathname === paths.auth.register

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

      <div
        className={cn(
          'relative z-10 mx-auto flex min-h-screen w-full max-w-7xl flex-col gap-10 px-5 py-10 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:gap-12 lg:px-12 lg:py-12',
          isRegister && 'lg:items-start lg:py-16',
        )}
      >
        <section className={cn('flex max-w-xl flex-1 flex-col justify-center', isRegister && 'lg:sticky lg:top-16')}>
          <p className="auth-fade-up text-sm font-medium tracking-[0.22em] text-white/70 uppercase">
            Welcome to
          </p>
          <h1 className="auth-fade-up auth-fade-up--delay-1 mt-3 font-auth-display text-4xl leading-[1.05] font-semibold tracking-tight sm:text-5xl lg:text-[3.4rem]">
            <span className="text-[#e11d2e]">Indra</span>{' '}
            <span className="text-white">Help Desk</span>
          </h1>
          <p className="auth-fade-up auth-fade-up--delay-2 mt-4 max-w-md text-base text-white/75 italic sm:text-lg">
            One Platform. Every Support Service.
          </p>
          <p className="auth-fade-up auth-fade-up--delay-3 mt-5 max-w-md text-sm leading-6 text-white/65">
            Sign in once for IT and HR support — raise tickets, track requests, and get help from the
            right team.
          </p>

          <ul className="auth-fade-up auth-fade-up--delay-4 mt-10 flex flex-wrap gap-3">
            {VALUE_PROPS.map(({ icon: Icon, label }) => (
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

        <main
          id="main-content"
          className={cn(
            'auth-fade-up auth-fade-up--delay-2 flex w-full justify-center lg:justify-end',
            isRegister ? 'max-w-xl lg:max-w-xl' : 'max-w-md',
            'mx-auto lg:mx-0',
          )}
        >
          <div className="w-full">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  )
}
