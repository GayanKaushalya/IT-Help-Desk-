import type { ReactNode } from 'react'
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { FileBarChart2 } from 'lucide-react'

import { Card, CardHeader } from '@/components/ui/Card'
import { EmptyState } from '@/components/ui/EmptyState'
import type { ChartBucket, ReportVolumePoint } from '@/types/models'

const TOKEN_COLORS: Record<string, string> = {
  neutral: '#64748b',
  primary: '#0f766e',
  success: '#16a34a',
  warning: '#d97706',
  danger: '#dc2626',
  info: '#2563eb',
}

const FALLBACK_COLORS = ['#0f766e', '#0284c7', '#7c3aed', '#d97706', '#dc2626', '#334155', '#16a34a']

function colorFor(bucket: ChartBucket, index: number): string {
  return (bucket.color_token ? TOKEN_COLORS[bucket.color_token] : undefined) ?? FALLBACK_COLORS[index % FALLBACK_COLORS.length] ?? '#0f766e'
}

function hasValues(buckets: ChartBucket[]): boolean {
  return buckets.some((bucket) => bucket.count > 0)
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
      <div className="h-80 px-2 py-4 pr-4">
        {empty ? (
          <EmptyState
            icon={FileBarChart2}
            title="No data for this chart"
            description="Nothing matches the current filters."
            className="h-full py-10"
          />
        ) : (
          children
        )}
      </div>
    </Card>
  )
}

function DonutChart({ data }: { data: ChartBucket[] }) {
  return (
    <ResponsiveContainer width="100%" height="100%">
      <PieChart>
        <Pie data={data} dataKey="count" nameKey="name" innerRadius={58} outerRadius={92} paddingAngle={2}>
          {data.map((bucket, index) => (
            <Cell key={bucket.id} fill={colorFor(bucket, index)} />
          ))}
        </Pie>
        <Tooltip />
        <Legend />
      </PieChart>
    </ResponsiveContainer>
  )
}

export function HrReportCharts({
  volume,
  complaintTrends = [],
  byBranch,
  byCategory,
  byRequestType,
  byStatus,
  byStaff,
  aging,
  referrals,
}: {
  volume: ReportVolumePoint[]
  complaintTrends?: ReportVolumePoint[]
  byBranch: ChartBucket[]
  byCategory: ChartBucket[]
  byRequestType: ChartBucket[]
  byStatus: ChartBucket[]
  byStaff: ChartBucket[]
  aging: ChartBucket[]
  referrals: ChartBucket[]
}) {
  const volumeEmpty = volume.every((point) => point.created === 0 && point.closed === 0)
  const complaintsEmpty = complaintTrends.every((point) => point.created === 0 && point.closed === 0)

  return (
    <div className="grid gap-6 xl:grid-cols-2">
      <ChartCard
        title="Request volume"
        description="HR requests created and completed over the selected period."
        empty={volumeEmpty}
      >
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={volume}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
            <XAxis dataKey="date" tick={{ fontSize: 10 }} minTickGap={24} />
            <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
            <Tooltip />
            <Legend />
            <Line type="monotone" dataKey="created" name="Created" stroke="#0f766e" strokeWidth={2} dot={false} />
            <Line type="monotone" dataKey="closed" name="Completed" stroke="#16a34a" strokeWidth={2} dot={false} />
          </LineChart>
        </ResponsiveContainer>
      </ChartCard>

      <ChartCard
        title="Employee complaint trends"
        description="Complaint volume and completions over the selected period."
        empty={complaintsEmpty}
      >
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={complaintTrends}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
            <XAxis dataKey="date" tick={{ fontSize: 10 }} minTickGap={24} />
            <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
            <Tooltip />
            <Legend />
            <Line type="monotone" dataKey="created" name="Complaints opened" stroke="#dc2626" strokeWidth={2} dot={false} />
            <Line type="monotone" dataKey="closed" name="Complaints closed" stroke="#ea580c" strokeWidth={2} dot={false} />
          </LineChart>
        </ResponsiveContainer>
      </ChartCard>

      <ChartCard title="Requests by branch" description="Where HR requests originated." empty={!hasValues(byBranch)}>
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={byBranch}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
            <XAxis dataKey="name" tick={{ fontSize: 11 }} interval={0} angle={-20} textAnchor="end" height={56} />
            <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
            <Tooltip />
            <Bar dataKey="count" name="Requests" fill="#0f766e" radius={[6, 6, 0, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </ChartCard>

      <ChartCard title="Requests by category" description="Demand across the HR catalogue." empty={!hasValues(byCategory)}>
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={byCategory} layout="vertical" margin={{ left: 16 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
            <XAxis type="number" allowDecimals={false} tick={{ fontSize: 11 }} />
            <YAxis type="category" dataKey="name" width={130} tick={{ fontSize: 11 }} />
            <Tooltip />
            <Bar dataKey="count" name="Requests" fill="#0284c7" radius={[0, 6, 6, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </ChartCard>

      <ChartCard title="Requests by type" description="Request types raised in this period." empty={!hasValues(byRequestType)}>
        <DonutChart data={byRequestType} />
      </ChartCard>

      <ChartCard title="Requests by status" description="Workflow distribution." empty={!hasValues(byStatus)}>
        <DonutChart data={byStatus} />
      </ChartCard>

      <ChartCard title="Requests by HR staff" description="Current assignee on requests in this period." empty={!hasValues(byStaff)}>
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={byStaff} layout="vertical" margin={{ left: 16 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
            <XAxis type="number" allowDecimals={false} tick={{ fontSize: 11 }} />
            <YAxis type="category" dataKey="name" width={130} tick={{ fontSize: 11 }} />
            <Tooltip />
            <Bar dataKey="count" name="Requests" fill="#7c3aed" radius={[0, 6, 6, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </ChartCard>

      <ChartCard title="Open request aging" description="How long currently open HR requests have been waiting." empty={!hasValues(aging)}>
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={aging}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
            <XAxis dataKey="name" tick={{ fontSize: 11 }} />
            <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
            <Tooltip />
            <Bar dataKey="count" name="Open requests" radius={[6, 6, 0, 0]}>
              {aging.map((bucket, index) => (
                <Cell key={bucket.id} fill={colorFor(bucket, index)} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </ChartCard>

      <ChartCard
        title="Department referrals"
        description="Referrals raised from HR requests in this period."
        empty={!hasValues(referrals)}
      >
        <DonutChart data={referrals} />
      </ChartCard>
    </div>
  )
}
