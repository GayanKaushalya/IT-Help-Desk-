import type { ReactNode } from 'react'
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { FileBarChart2 } from 'lucide-react'

import { Card, CardHeader } from '@/components/ui/Card'
import { EmptyState } from '@/components/ui/EmptyState'
import type { ColorToken, AdmDashboardCategoryCount, AdmDashboardStatusCount } from '@/types/models'

const TOKEN_COLORS: Record<string, string> = {
  neutral: '#64748b',
  primary: '#d97706',
  success: '#16a34a',
  warning: '#d97706',
  danger: '#dc2626',
  info: '#2563eb',
}

const FALLBACK_COLORS = ['#d97706', '#0284c7', '#7c3aed', '#d97706', '#dc2626', '#334155', '#16a34a']

function colorFor(token: ColorToken | string | null | undefined, index: number): string {
  if (token && TOKEN_COLORS[token]) {
    return TOKEN_COLORS[token]
  }

  return FALLBACK_COLORS[index % FALLBACK_COLORS.length] ?? '#d97706'
}

function hasValues(rows: { count: number }[]): boolean {
  return rows.some((row) => row.count > 0)
}

function ChartCard({
  title,
  description,
  children,
  empty,
}: {
  title: string
  description: string
  children: ReactNode
  empty?: boolean
}) {
  return (
    <Card>
      <CardHeader title={title} description={description} />
      <div className="h-72 px-2 py-4 pr-4">
        {empty ? (
          <EmptyState
            icon={FileBarChart2}
            title="No data for this chart"
            description="Nothing to plot until Administration requests exist."
            className="h-full py-10"
          />
        ) : (
          children
        )}
      </div>
    </Card>
  )
}

/**
 * Administration admin dashboard charts — same Recharts style as the IT service desk.
 */
export function AdmAdminCharts({
  byStatus,
  byCategory,
}: {
  byStatus: AdmDashboardStatusCount[]
  byCategory: AdmDashboardCategoryCount[]
}) {
  return (
    <div className="grid gap-6 xl:grid-cols-2">
      <ChartCard
        title="Requests by status"
        description="Current volume across the Administration service-desk workflow."
        empty={!hasValues(byStatus)}
      >
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={byStatus}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
            <XAxis dataKey="name" tick={{ fontSize: 11 }} interval={0} angle={-20} textAnchor="end" height={64} />
            <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
            <Tooltip />
            <Bar dataKey="count" name="Requests" radius={[6, 6, 0, 0]}>
              {byStatus.map((bucket, index) => (
                <Cell key={bucket.id} fill={colorFor(bucket.color_token, index)} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </ChartCard>

      <ChartCard
        title="Requests by category"
        description="Demand across the Administration catalogue."
        empty={!hasValues(byCategory)}
      >
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={byCategory} layout="vertical" margin={{ left: 16 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
            <XAxis type="number" allowDecimals={false} tick={{ fontSize: 11 }} />
            <YAxis type="category" dataKey="name" width={130} tick={{ fontSize: 11 }} />
            <Tooltip />
            <Bar dataKey="count" name="Requests" fill="#d97706" radius={[0, 6, 6, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </ChartCard>
    </div>
  )
}
