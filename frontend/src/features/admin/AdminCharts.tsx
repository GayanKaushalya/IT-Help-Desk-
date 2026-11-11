import type { ReactNode } from 'react'
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'

import { Card, CardHeader } from '@/components/ui/Card'
import type { ChartBucket, StaffWorkload, TimeSeriesPoint } from '@/types/models'

const TOKEN_COLORS: Record<string, string> = {
  neutral: '#64748b',
  primary: '#3366ff',
  success: '#16a34a',
  warning: '#d97706',
  danger: '#dc2626',
  info: '#2563eb',
}

const FALLBACK_COLORS = ['#3366ff', '#0f766e', '#7c3aed', '#d97706', '#dc2626', '#334155', '#16a34a']

function colorFor(bucket: ChartBucket, index: number): string {
  const tokenColor = bucket.color_token ? TOKEN_COLORS[bucket.color_token] : undefined
  if (tokenColor) {
    return tokenColor
  }

  return FALLBACK_COLORS[index % FALLBACK_COLORS.length] ?? '#0284c7'
}

function ChartCard({
  title,
  description,
  children,
}: {
  title: string
  description: string
  children: ReactNode
}) {
  return (
    <Card>
      <CardHeader title={title} description={description} />
      <div className="h-72 px-2 py-4 pr-4">{children}</div>
    </Card>
  )
}

export function AdminCharts({
  byStatus,
  byPriority,
  byCategory,
  byBranch,
  overTime,
  workload,
}: {
  byStatus: ChartBucket[]
  byPriority: ChartBucket[]
  byCategory: ChartBucket[]
  byBranch: ChartBucket[]
  overTime: TimeSeriesPoint[]
  workload: StaffWorkload[]
}) {
  return (
    <div className="grid gap-6 xl:grid-cols-2">
      <ChartCard title="Tickets by status" description="Current volume across the service-desk workflow.">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={byStatus}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
            <XAxis dataKey="name" tick={{ fontSize: 11 }} interval={0} angle={-20} textAnchor="end" height={56} />
            <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
            <Tooltip />
            <Bar dataKey="count" radius={[6, 6, 0, 0]}>
              {byStatus.map((bucket, index) => (
                <Cell key={bucket.id} fill={colorFor(bucket, index)} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </ChartCard>

      <ChartCard title="Tickets by priority" description="How urgently the open queue needs attention.">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={byPriority}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
            <XAxis dataKey="name" tick={{ fontSize: 11 }} />
            <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
            <Tooltip />
            <Bar dataKey="count" radius={[6, 6, 0, 0]}>
              {byPriority.map((bucket, index) => (
                <Cell key={bucket.id} fill={colorFor(bucket, index)} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </ChartCard>

      <ChartCard title="Tickets by category" description="Demand across the service catalogue.">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={byCategory} layout="vertical" margin={{ left: 24 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
            <XAxis type="number" allowDecimals={false} tick={{ fontSize: 11 }} />
            <YAxis type="category" dataKey="name" width={110} tick={{ fontSize: 11 }} />
            <Tooltip />
            <Bar dataKey="count" fill="#0284c7" radius={[0, 6, 6, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </ChartCard>

      <ChartCard title="Tickets over time" description="New tickets raised in the last 30 days.">
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={overTime}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
            <XAxis dataKey="date" tick={{ fontSize: 10 }} minTickGap={24} />
            <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
            <Tooltip />
            <Line type="monotone" dataKey="count" stroke="#0284c7" strokeWidth={2} dot={false} />
          </LineChart>
        </ResponsiveContainer>
      </ChartCard>

      <ChartCard title="Tickets by branch" description="Where requests are coming from.">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={byBranch}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
            <XAxis dataKey="name" tick={{ fontSize: 11 }} interval={0} angle={-20} textAnchor="end" height={56} />
            <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
            <Tooltip />
            <Bar dataKey="count" fill="#0f766e" radius={[6, 6, 0, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </ChartCard>

      <ChartCard title="IT staff workload" description="Active assignments on each support agent.">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={workload} layout="vertical" margin={{ left: 16 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
            <XAxis type="number" allowDecimals={false} tick={{ fontSize: 11 }} />
            <YAxis type="category" dataKey="full_name" width={120} tick={{ fontSize: 11 }} />
            <Tooltip />
            <Legend />
            <Bar dataKey="open_tickets" name="Open" fill="#0284c7" radius={[0, 6, 6, 0]} />
            <Bar dataKey="total_assigned" name="Assigned" fill="#94a3b8" radius={[0, 6, 6, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </ChartCard>
    </div>
  )
}
