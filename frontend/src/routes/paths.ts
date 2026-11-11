/**
 * Every route in the application. Components link through these helpers
 * instead of hard-coded strings so a path can be changed in one place.
 */
export const paths = {
  root: '/',

  auth: {
    login: '/login',
    register: '/register',
  },

  /** Help desk chooser shown after sign-in for everyone except IT desk operators. */
  desks: '/desks',

  dashboard: '/dashboard',
  profile: '/profile',
  knowledgeBase: '/knowledge-base',
  knowledgeArticle: (id: number | string = ':id') => `/knowledge-base/${id}`,

  tickets: {
    index: '/tickets',
    create: '/tickets/create',
    detail: (id: number | string = ':id') => `/tickets/${id}`,
  },

  admin: {
    index: '/admin',
    tickets: '/admin/tickets',
    assigned: '/admin/tickets/assigned',
    ticketDetail: (id: number | string = ':id') => `/admin/tickets/${id}`,
    users: '/admin/users',
    userDetail: (id: number | string = ':id') => `/admin/users/${id}`,
    branches: '/admin/branches',
    categories: '/admin/categories',
    reports: '/admin/reports',
    settings: '/admin/settings',
    auditLogs: '/admin/audit-logs',
  },

  hr: {
    desks: '/desks',
    index: '/hr',
    submit: '/hr/requests/create',
    requests: '/hr/requests',
    track: '/hr/track',
    detail: (id: number | string = ':id') => `/hr/requests/${id}`,
    profile: '/hr/profile',
    admin: {
      index: '/hr/admin',
      requests: '/hr/admin/requests',
      assigned: '/hr/admin/requests/assigned',
      complaints: '/hr/admin/complaints',
      referrals: '/hr/admin/referrals',
      reports: '/hr/admin/reports',
      auditLogs: '/hr/admin/audit-logs',
      sla: '/hr/admin/sla',
      requestDetail: (id: number | string = ':id') => `/hr/admin/requests/${id}`,
    },
  },

  designSystem: '/design-system',
} as const
