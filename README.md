# IT Helpdesk

Enterprise IT service management for raising, triaging and resolving internal IT support tickets.

This repository currently contains **the project foundation only**. The architecture, database,
API structure, design system and application shell are in place; authentication, ticketing and the
administration modules are built on top of it next.

---

## Architecture

| Layer      | Technology                                                           |
| ---------- | -------------------------------------------------------------------- |
| Backend    | PHP 8.3, Laravel 13, Sanctum, RESTful JSON API                        |
| Database   | Microsoft SQL Server 2008 R2 (`192.168.20.5`), database `ITHelpdesk`  |
| Frontend   | React 19, Vite, TypeScript, Tailwind CSS v4, TanStack Query           |
| Auth model | Bearer tokens issued by Sanctum, role and permission based authorization |

```
IT-Helpdesk/
├── backend/     Laravel API
├── frontend/    React single-page application
└── scripts/     One-off environment and database setup helpers
```

### Why SQL Server instead of MySQL

The company database server hosting the other internal systems is SQL Server 2008 R2, so the
helpdesk uses the same instance and the same `ssipl` account rather than introducing a second
database engine.

That version predates `OFFSET ... FETCH NEXT`, which is what Laravel compiles paginated queries to.
`App\Database\Query\Grammars\SqlServerLegacyGrammar` rewrites those queries using `ROW_NUMBER()`
instead, and is switched on per connection via `DB_SQLSRV_LEGACY_PAGINATION`. Pagination therefore
works normally; no calling code has to know about it.

---

## Prerequisites

Already installed and configured on this machine:

- PHP 8.3 (ZTS x64) with `sqlsrv` and `pdo_sqlsrv`
- Composer 2.10
- Node.js 22 and npm 10
- Microsoft ODBC Driver 17/18 for SQL Server

If PHP is ever upgraded, winget replaces the package directory and discards `php.ini` along with the
SQL Server driver DLLs. Re-run the setup script to restore them:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup-php.ps1
```

---

## Database setup

The database already exists and is migrated. To recreate it from scratch on another machine:

```powershell
# 1. Create the database (connects to master, then creates ITHelpdesk)
php scripts\create-database.php

# 2. Create the tables
cd backend
php artisan migrate

# 3. Load roles, permissions, branches, departments, priorities, statuses and categories
php artisan db:seed
```

Connection settings live in `backend/.env` and are read from environment variables only; no
credentials are hard-coded anywhere in the source.

To verify connectivity independently of Laravel:

```powershell
php scripts\check-db.php
```

### Schema

| Table                  | Purpose                                                            |
| ---------------------- | ------------------------------------------------------------------ |
| `branches`             | Company locations                                                   |
| `departments`          | Organisational departments                                          |
| `roles`, `permissions` | Role-based access control, joined by `permission_role`              |
| `users`                | Accounts, with branch, department and a single role                 |
| `ticket_priorities`    | Urgency levels and their SLA targets                                |
| `ticket_statuses`      | Workflow states, with behavioural flags such as `requires_verification` |
| `ticket_categories`    | Service catalogue, optionally nested one level                      |
| `tickets`              | The ticket itself, with lifecycle timestamps                        |
| `ticket_comments`      | Conversation, including internal-only notes                         |
| `ticket_attachments`   | Uploaded files                                                      |
| `ticket_assignments`   | Full ownership history                                              |
| `ticket_verifications` | Resolution codes; only a hash is stored                             |
| `audit_logs`           | Immutable record of security and data changes                       |

### Seeded accounts

`SuperAdminSeeder` creates one account from `backend/.env`:

| Username     | Password        | Role        |
| ------------ | --------------- | ----------- |
| `superadmin` | `ChangeMe@2026` | Super Admin |

Change `SEED_SUPER_ADMIN_PASSWORD` before using this anywhere other than a development machine.

---

## Running the application

### Backend

```powershell
cd backend
php artisan serve
```

The API is served at `http://localhost:8000`. Useful checks:

```powershell
php artisan db:show          # confirm the database connection
php artisan route:list       # list the API surface
php artisan test             # run the test suite
```

### Frontend

```powershell
cd frontend
npm run dev
```

The application is served at `http://localhost:5173`. Both servers must be running: the SPA reads
`VITE_API_BASE_URL` from `frontend/.env` and calls the Laravel API directly, with CORS configured on
the backend for the Vite origin.

---

## API

All endpoints are versioned under `/api/v1` and return a single envelope:

```jsonc
// Success
{ "success": true, "message": null, "data": { }, "meta": { } }

// Failure
{ "success": false, "message": "…", "errors": { "field": ["…"] }, "code": "VALIDATION_FAILED" }
```

| Method | Endpoint              | Access        | Purpose                                     |
| ------ | --------------------- | ------------- | ------------------------------------------- |
| GET    | `/api/v1/health`      | Public        | Service and database health                 |
| GET    | `/api/v1/lookups/public` | Public     | Branches and departments for registration   |
| GET    | `/api/v1/lookups`     | Authenticated | Full reference catalogue                    |

Route groups are already defined and reserved for the modules that follow: `auth`, the authenticated
employee area, `staff` for IT support, and `admin` for configuration.

### Middleware aliases

| Alias        | Class                     | Example                            |
| ------------ | ------------------------- | ---------------------------------- |
| `active`     | `EnsureUserIsActive`      | blocks deactivated accounts        |
| `role`       | `EnsureUserHasRole`       | `role:administrator,super-admin`   |
| `permission` | `EnsureUserHasPermission` | `permission:tickets.assign`        |

Rate limiters: `api` (120/min), `auth` (5/min per IP and per username), `uploads` (30/min).

---

## Frontend structure

```
src/
├── assets/       Static files
├── components/
│   ├── common/   Composed application pieces (PageHeader, StatCard)
│   ├── layout/   Sidebar, Topbar, navigation definition
│   └── ui/       Design system primitives
├── config/       Typed environment access
├── contexts/     AuthContext, ToastContext
├── features/     Feature-scoped hooks and components
├── hooks/        Reusable hooks (useAuth, useToast)
├── layouts/      AppLayout, AuthLayout
├── pages/        Route-level screens
├── routes/       Route tree, path constants, ProtectedRoute
├── services/     API client and endpoint wrappers
├── types/        Shared API and domain types
└── utils/        Formatting and class-name helpers
```

### Design system

Colour, typography, elevation and animation tokens are declared once in `src/index.css` under
`@theme`. Components reference semantic names (`primary`, `success`, `warning`, `danger`, `info`,
`surface`) rather than raw values, and the `color_token` values seeded in the database map directly
onto the same names, so a status badge is always the same colour on every screen.

Visit `/design-system` in the running application for a live reference of every component, including
loading, empty and error states.

---

## Development standards

1. Credentials come from environment variables; `.env` files are never committed.
2. Passwords are hashed by the `hashed` cast and are excluded from every API resource.
3. Controllers stay thin. Business logic belongs in `app/Services`, authorization in `app/Policies`.
4. Every request is validated through a Form Request extending `ApiFormRequest`.
5. All queries go through Eloquent or the query builder, which parameterises input.
6. Error responses never expose SQL, file paths or stack traces outside debug mode.
7. React components render; data fetching lives in feature hooks built on TanStack Query.
8. UI is assembled from `components/ui`; new one-off styles should become a component instead.
9. Routes are referenced through `routes/paths.ts`, never as string literals.
10. Backend formatting is enforced with `./vendor/bin/pint`; the frontend is typechecked with `npm run typecheck`.

---

## Not built yet

Deliberately out of scope for the foundation:

- Login, registration, logout and session restoration
- Ticket creation, listing, detail, comments and attachments
- Assignment, status transitions, resolution and verification codes
- User, branch, category and settings administration
- Reporting and audit log browsing

Reserved routes render a "module not built" placeholder so the navigation structure can be reviewed
without shipping stubbed features.
