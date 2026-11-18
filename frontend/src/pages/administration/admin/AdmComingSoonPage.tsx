import { Hammer } from 'lucide-react'
import type { ComponentType } from 'react'

import { PageHeader } from '@/components/common/PageHeader'
import { Card } from '@/components/ui/Card'
import { EmptyState } from '@/components/ui/EmptyState'

export interface AdmComingSoonPageProps {
  title: string
  description: string
  icon?: ComponentType<{ className?: string }>
}

/** Placeholder for Administration registers, reports and settings that ship in a later phase. */
export function AdmComingSoonPage({ title, description, icon = Hammer }: AdmComingSoonPageProps) {
  return (
    <div className="space-y-6">
      <PageHeader title={title} description={description} />
      <Card>
        <EmptyState
          icon={icon}
          title="Coming soon"
          description="This part of the Administration service desk is planned for a later phase. Requests are fully available today."
        />
      </Card>
    </div>
  )
}
