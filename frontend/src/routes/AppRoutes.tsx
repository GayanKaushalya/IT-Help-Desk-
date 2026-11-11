import { lazy, Suspense } from 'react'
import { Route, Routes } from 'react-router-dom'

import { LoadingPanel } from '@/components/ui/Spinner'
import { AdminLayout } from '@/layouts/AdminLayout'
import { AppLayout } from '@/layouts/AppLayout'
import { AuthLayout } from '@/layouts/AuthLayout'
import { DeskLayout } from '@/layouts/DeskLayout'
import { HrAdminLayout } from '@/layouts/HrAdminLayout'
import { HrEmployeeLayout } from '@/layouts/HrEmployeeLayout'
import { LoginPage } from '@/pages/LoginPage'
import { RegisterPage } from '@/pages/RegisterPage'
import { GuestRoute } from '@/routes/GuestRoute'
import { ProtectedRoute } from '@/routes/ProtectedRoute'
import { paths } from '@/routes/paths'
import { DESK_ROLES, HR_DESK_ROLES } from '@/utils/roles'

const RootEntry = lazy(() => import('@/routes/RootEntry').then((module) => ({ default: module.RootEntry })))
const DashboardPage = lazy(() => import('@/pages/DashboardPage').then((module) => ({ default: module.DashboardPage })))
const DesignSystemPage = lazy(() =>
  import('@/pages/DesignSystemPage').then((module) => ({ default: module.DesignSystemPage })),
)
const ProfilePage = lazy(() => import('@/pages/ProfilePage').then((module) => ({ default: module.ProfilePage })))
const MyTicketsPage = lazy(() => import('@/pages/MyTicketsPage').then((module) => ({ default: module.MyTicketsPage })))
const CreateTicketPage = lazy(() =>
  import('@/pages/CreateTicketPage').then((module) => ({ default: module.CreateTicketPage })),
)
const TicketDetailPage = lazy(() =>
  import('@/pages/TicketDetailPage').then((module) => ({ default: module.TicketDetailPage })),
)
const KnowledgeBasePage = lazy(() =>
  import('@/pages/KnowledgeBasePage').then((module) => ({ default: module.KnowledgeBasePage })),
)
const KnowledgeArticlePage = lazy(() =>
  import('@/pages/KnowledgeArticlePage').then((module) => ({ default: module.KnowledgeArticlePage })),
)
const AdminDashboardPage = lazy(() =>
  import('@/pages/admin/AdminDashboardPage').then((module) => ({ default: module.AdminDashboardPage })),
)
const AdminTicketsPage = lazy(() =>
  import('@/pages/admin/AdminTicketsPage').then((module) => ({ default: module.AdminTicketsPage })),
)
const AdminTicketWorkspacePage = lazy(() =>
  import('@/pages/admin/AdminTicketWorkspacePage').then((module) => ({ default: module.AdminTicketWorkspacePage })),
)
const AdminUsersPage = lazy(() =>
  import('@/pages/admin/AdminUsersPage').then((module) => ({ default: module.AdminUsersPage })),
)
const AdminUserDetailPage = lazy(() =>
  import('@/pages/admin/AdminUserDetailPage').then((module) => ({ default: module.AdminUserDetailPage })),
)
const AdminBranchesPage = lazy(() =>
  import('@/pages/admin/AdminBranchesPage').then((module) => ({ default: module.AdminBranchesPage })),
)
const AdminCategoriesPage = lazy(() =>
  import('@/pages/admin/AdminCategoriesPage').then((module) => ({ default: module.AdminCategoriesPage })),
)
const AdminReportsPage = lazy(() =>
  import('@/pages/admin/AdminReportsPage').then((module) => ({ default: module.AdminReportsPage })),
)
const AdminAuditLogsPage = lazy(() =>
  import('@/pages/admin/AdminAuditLogsPage').then((module) => ({ default: module.AdminAuditLogsPage })),
)
const AdminSettingsPage = lazy(() =>
  import('@/pages/admin/AdminSettingsPage').then((module) => ({ default: module.AdminSettingsPage })),
)
const DeskChooserPage = lazy(() =>
  import('@/pages/DeskChooserPage').then((module) => ({ default: module.DeskChooserPage })),
)
const HrHomePage = lazy(() => import('@/pages/hr/HrHomePage').then((module) => ({ default: module.HrHomePage })))
const CreateHrRequestPage = lazy(() =>
  import('@/pages/hr/CreateHrRequestPage').then((module) => ({ default: module.CreateHrRequestPage })),
)
const MyHrRequestsPage = lazy(() =>
  import('@/pages/hr/MyHrRequestsPage').then((module) => ({ default: module.MyHrRequestsPage })),
)
const HrRequestDetailPage = lazy(() =>
  import('@/pages/hr/HrRequestDetailPage').then((module) => ({ default: module.HrRequestDetailPage })),
)
const TrackHrRequestPage = lazy(() =>
  import('@/pages/hr/TrackHrRequestPage').then((module) => ({ default: module.TrackHrRequestPage })),
)
const HrAdminDashboardPage = lazy(() =>
  import('@/pages/hr/admin/HrAdminDashboardPage').then((module) => ({ default: module.HrAdminDashboardPage })),
)
const HrAdminRequestsPage = lazy(() =>
  import('@/pages/hr/admin/HrAdminRequestsPage').then((module) => ({ default: module.HrAdminRequestsPage })),
)
const HrAdminRequestWorkspacePage = lazy(() =>
  import('@/pages/hr/admin/HrAdminRequestWorkspacePage').then((module) => ({
    default: module.HrAdminRequestWorkspacePage,
  })),
)
const HrAdminReportsPage = lazy(() =>
  import('@/pages/hr/admin/HrAdminReportsPage').then((module) => ({ default: module.HrAdminReportsPage })),
)
const HrAdminAuditLogsPage = lazy(() =>
  import('@/pages/hr/admin/HrAdminAuditLogsPage').then((module) => ({ default: module.HrAdminAuditLogsPage })),
)
const HrAdminSlaPage = lazy(() =>
  import('@/pages/hr/admin/HrAdminSlaPage').then((module) => ({ default: module.HrAdminSlaPage })),
)
const HrProfilePage = lazy(() =>
  import('@/pages/hr/HrProfilePage').then((module) => ({ default: module.HrProfilePage })),
)
const NotFoundPage = lazy(() => import('@/pages/NotFoundPage').then((module) => ({ default: module.NotFoundPage })))

function RouteFallback() {
  return <LoadingPanel label="Loading page…" />
}

export function AppRoutes() {
  return (
    <Suspense fallback={<RouteFallback />}>
      <Routes>
        {/* Opening the app always starts here: guests → login, users → role home. */}
        <Route path={paths.root} element={<RootEntry />} />

        <Route
          element={
            <GuestRoute>
              <AuthLayout />
            </GuestRoute>
          }
        >
          <Route path={paths.auth.login} element={<LoginPage />} />
          <Route path={paths.auth.register} element={<RegisterPage />} />
        </Route>

        <Route
          element={
            <ProtectedRoute>
              <DeskLayout />
            </ProtectedRoute>
          }
        >
          <Route path={paths.desks} element={<DeskChooserPage />} />
        </Route>

        <Route
          element={
            <ProtectedRoute>
              <HrEmployeeLayout />
            </ProtectedRoute>
          }
        >
          <Route path={paths.hr.index} element={<HrHomePage />} />
          <Route
            path={paths.hr.submit}
            element={
              <ProtectedRoute permission="hr.requests.create">
                <CreateHrRequestPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={paths.hr.requests}
            element={
              <ProtectedRoute permission="hr.requests.view_own">
                <MyHrRequestsPage />
              </ProtectedRoute>
            }
          />
          <Route path={paths.hr.track} element={<TrackHrRequestPage />} />
          <Route path={paths.hr.detail()} element={<HrRequestDetailPage />} />
          <Route path={paths.hr.profile} element={<HrProfilePage />} />
        </Route>

        <Route
          element={
            <ProtectedRoute roles={HR_DESK_ROLES} permission="hr.requests.view_any">
              <HrAdminLayout />
            </ProtectedRoute>
          }
        >
          <Route path={paths.hr.admin.index} element={<HrAdminDashboardPage />} />
          <Route path={paths.hr.admin.requests} element={<HrAdminRequestsPage />} />
          <Route path={paths.hr.admin.assigned} element={<HrAdminRequestsPage assignedOnly />} />
          <Route
            path={paths.hr.admin.complaints}
            element={
              <ProtectedRoute permission="hr.requests.view_confidential">
                <HrAdminRequestsPage complaintsOnly />
              </ProtectedRoute>
            }
          />
          <Route
            path={paths.hr.admin.referrals}
            element={
              <ProtectedRoute permission="hr.referrals.manage">
                <HrAdminRequestsPage referralsOnly />
              </ProtectedRoute>
            }
          />
          <Route
            path={paths.hr.admin.reports}
            element={
              <ProtectedRoute permission="hr.reports.view">
                <HrAdminReportsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={paths.hr.admin.auditLogs}
            element={
              <ProtectedRoute permission="hr.reports.view">
                <HrAdminAuditLogsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={paths.hr.admin.sla}
            element={
              <ProtectedRoute permission="hr.sla.manage">
                <HrAdminSlaPage />
              </ProtectedRoute>
            }
          />
          <Route path={paths.hr.admin.requestDetail()} element={<HrAdminRequestWorkspacePage />} />
        </Route>
        <Route
          element={
            <ProtectedRoute>
              <AppLayout />
            </ProtectedRoute>
          }
        >
          <Route path={paths.dashboard} element={<DashboardPage />} />
          <Route path={paths.designSystem} element={<DesignSystemPage />} />
          <Route path={paths.profile} element={<ProfilePage />} />
          <Route
            path={paths.tickets.index}
            element={
              <ProtectedRoute permission="tickets.view_own">
                <MyTicketsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={paths.tickets.create}
            element={
              <ProtectedRoute permission="tickets.create">
                <CreateTicketPage />
              </ProtectedRoute>
            }
          />
          <Route path={paths.tickets.detail()} element={<TicketDetailPage />} />
          <Route path={paths.knowledgeBase} element={<KnowledgeBasePage />} />
          <Route path={paths.knowledgeArticle()} element={<KnowledgeArticlePage />} />
          <Route path="*" element={<NotFoundPage />} />
        </Route>

        <Route
          element={
            <ProtectedRoute roles={DESK_ROLES} permission="tickets.view_any">
              <AdminLayout />
            </ProtectedRoute>
          }
        >
          <Route path={paths.admin.index} element={<AdminDashboardPage />} />
          <Route path={paths.admin.tickets} element={<AdminTicketsPage />} />
          <Route path={paths.admin.assigned} element={<AdminTicketsPage assignedOnly />} />
          <Route path={paths.admin.ticketDetail()} element={<AdminTicketWorkspacePage />} />
          <Route
            path={paths.admin.users}
            element={
              <ProtectedRoute permission="users.view">
                <AdminUsersPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={paths.admin.userDetail()}
            element={
              <ProtectedRoute permission="users.view">
                <AdminUserDetailPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={paths.admin.branches}
            element={
              <ProtectedRoute permission="branches.manage">
                <AdminBranchesPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={paths.admin.categories}
            element={
              <ProtectedRoute permission="categories.manage">
                <AdminCategoriesPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={paths.admin.reports}
            element={
              <ProtectedRoute permission="reports.view">
                <AdminReportsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={paths.admin.auditLogs}
            element={
              <ProtectedRoute permission="audit.view">
                <AdminAuditLogsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={paths.admin.settings}
            element={
              <ProtectedRoute permission="settings.manage">
                <AdminSettingsPage />
              </ProtectedRoute>
            }
          />
          <Route path="*" element={<NotFoundPage />} />
        </Route>
      </Routes>
    </Suspense>
  )
}
