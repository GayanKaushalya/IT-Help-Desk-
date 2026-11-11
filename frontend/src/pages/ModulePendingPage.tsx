import { Construction } from 'lucide-react'
import { useNavigate } from 'react-router-dom'

import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/Button'
import { Card, CardContent } from '@/components/ui/Card'
import { EmptyState } from '@/components/ui/EmptyState'
import { paths } from '@/routes/paths'

export interface ModulePendingPageProps {
  title: string
  description: string
  /** What this screen will do once its module is built. */
  plannedFor: string
  backTo?: string
}

/**
 * Stands in for routes that are reserved but not yet implemented, so the
 * navigation structure can be reviewed without shipping placeholder features.
 */
export function ModulePendingPage({
  title,
  description,
  plannedFor,
  backTo = paths.dashboard,
}: ModulePendingPageProps) {
  const navigate = useNavigate()

  return (
    <div className="space-y-6">
      <PageHeader title={title} description={description} />

      <Card>
        <CardContent className="py-6">
          <EmptyState
            icon={Construction}
            title="This module is reserved"
            description={plannedFor}
            action={
              <Button variant="outline" onClick={() => void navigate(backTo)}>
                Back to dashboard
              </Button>
            }
          />
        </CardContent>
      </Card>
    </div>
  )
}
