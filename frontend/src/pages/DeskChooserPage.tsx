import { ArrowRight, Building2, Monitor, Users } from 'lucide-react'
import type { ComponentType } from 'react'
import { Link } from 'react-router-dom'

import { useAuth } from '@/hooks/useAuth'
import { paths } from '@/routes/paths'
import { cn } from '@/utils/cn'
import { isAdministrationDeskRole, isDeskRole, isHrDeskRole } from '@/utils/roles'

interface DeskCardProps {
  to: string
  title: string
  description: string
  highlights: string[]
  cta: string
  icon: ComponentType<{ className?: string }>
  tone: 'it' | 'hr' | 'administration'
}

const TONES = {
  it: {
    tile: 'bg-primary-600 text-white',
    ring: 'hover:border-primary-300 focus-visible:outline-primary-500',
    dot: 'bg-primary-500',
    cta: 'text-primary-700',
  },
  hr: {
    tile: 'bg-hr-600 text-white',
    ring: 'hover:border-hr-300 focus-visible:outline-hr-500',
    dot: 'bg-hr-500',
    cta: 'text-hr-700',
  },
  administration: {
    tile: 'bg-adm-600 text-white',
    ring: 'hover:border-adm-300 focus-visible:outline-adm-500',
    dot: 'bg-adm-500',
    cta: 'text-adm-700',
  },
} as const

function DeskCard({ to, title, description, highlights, cta, icon: Icon, tone }: DeskCardProps) {
  const classes = TONES[tone]

  return (
    <Link
      to={to}
      className={cn(
        'group flex flex-col rounded-card border border-surface-200 bg-panel p-6 shadow-card',
        'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-card-hover sm:p-8',
        classes.ring,
      )}
    >
      <span className={cn('flex size-14 items-center justify-center rounded-2xl shadow-sm', classes.tile)}>
        <Icon className="size-7" aria-hidden />
      </span>

      <h2 className="mt-6 text-xl font-semibold tracking-tight text-surface-900">{title}</h2>
      <p className="mt-2 text-sm leading-6 text-surface-600">{description}</p>

      <ul className="mt-5 space-y-2">
        {highlights.map((item) => (
          <li key={item} className="flex items-center gap-2.5 text-sm text-surface-700">
            <span className={cn('size-1.5 shrink-0 rounded-full', classes.dot)} aria-hidden />
            {item}
          </li>
        ))}
      </ul>

      <span className={cn('mt-8 inline-flex items-center gap-2 text-sm font-semibold', classes.cta)}>
        {cta}
        <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" aria-hidden />
      </span>
    </Link>
  )
}

/**
 * Landing screen that lets a signed-in user pick the IT, HR or Administration help desk.
 *
 * IT desk operators open the IT service desk for queue work. Administrators
 * also open the HR service desk as admins. HR officers and managers open the
 * HR service desk for queue work, and the employee IT area when they need to
 * raise or track their own IT tickets. Administration desk roles open the
 * Administration service desk for queue work.
 */
export function DeskChooserPage() {
  const { user } = useAuth()
  const slug = user?.role?.slug

  const opensItAdmin = isDeskRole(slug)
  const opensHrAdmin = isHrDeskRole(slug)
  const opensAdmAdmin = isAdministrationDeskRole(slug)

  const itPath = opensItAdmin ? paths.admin.index : paths.dashboard
  const hrPath = opensHrAdmin ? paths.hr.admin.index : paths.hr.index
  const admPath = opensAdmAdmin ? paths.administration.admin.index : paths.administration.index

  return (
    <div className="space-y-10">
      <div className="text-center">
        <h1 className="text-3xl font-semibold tracking-tight text-surface-900 sm:text-4xl">Indra Help Desk</h1>
        <p className="mt-3 text-base text-surface-600">Welcome — choose IT, HR or Administration support for today.</p>
      </div>

      <div className="grid gap-6 md:grid-cols-3">
        <DeskCard
          to={itPath}
          tone="it"
          icon={Monitor}
          title="IT Help Desk"
          description="Report hardware, software, network and access problems, or request IT equipment."
          highlights={['Raise and track IT tickets', 'Knowledge base articles', 'Equipment and access requests']}
          cta={opensItAdmin ? 'Open IT service desk' : 'Open IT Help Desk'}
        />

        <DeskCard
          to={hrPath}
          tone="hr"
          icon={Users}
          title="HR Help Desk"
          description="Get help with payroll, leave, HR letters, employee records and workplace concerns."
          highlights={['Submit and track HR requests', 'Payroll, leave and documents', 'Confidential complaints']}
          cta={opensHrAdmin ? 'Open HR service desk' : 'Open HR Help Desk'}
        />

        <DeskCard
          to={admPath}
          tone="administration"
          icon={Building2}
          title="Administration Help Desk"
          description="Arrange insurance, fleet, fuel cards, security, courier, maintenance and office facilities."
          highlights={[
            'Submit and track Administration requests',
            'Insurance, fleet and fuel cards',
            'Facilities, supplies and events',
          ]}
          cta={opensAdmAdmin ? 'Open Administration service desk' : 'Open Administration Help Desk'}
        />
      </div>
    </div>
  )
}
