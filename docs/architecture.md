# Architecture

## Monorepo Layout

- `backend/`
  - `app/Http/Controllers` transport layer for auth, attendance, students, reporting, notifications, and Dynamics
  - `app/Services` business logic for auth, attendance workflows, dashboards, reporting, audit, and notifications
  - `app/Integrations/Dynamics` external client and payload mapper for Microsoft Dynamics
  - `app/Jobs` queued exports and Dynamics sync workers
  - `database/migrations` schema for users, roles, academics, attendance, logs, notifications, refresh tokens, and sync ledgers
- `frontend/`
  - `src/app/(auth)` Microsoft login and callback routes
  - `src/app/(dashboard)` role-protected pages for teacher, admin, attendance, students, and reports
  - `src/components` reusable layout, dashboard, attendance, student, report, and UI building blocks
  - `src/lib` API client, MSAL helpers, auth context, and utilities

## Authentication Flow

1. Next.js starts Microsoft sign-in through MSAL using a multi-tenant Entra application.
2. After Microsoft redirects back, the frontend sends the `id_token` and nonce to `POST /api/v1/auth/microsoft/exchange`.
3. Laravel validates the Microsoft token against Microsoft JWKS and configured audience, issuer, tenant, and domain constraints.
4. Laravel resolves or provisions the local user in `users` and `user_identities`.
5. Laravel issues signed JWT access and refresh tokens.
6. The frontend stores the session and calls protected APIs with the access token.

## Domain Modules

### Attendance

- `attendance_sessions` stores class, subject, teacher, schedule, and sync state
- `attendance_records` stores per-student roll call status and remarks
- `AttendanceSessionService` orchestrates session creation, record upserts, and session closure

### Reporting

- `AttendanceReportService` generates summary totals, class trends, student trends, and export rows
- `GenerateAttendanceExport` creates queued PDF/XLSX artifacts and raises notifications when ready

### Audit and Notifications

- `AuditLogger` records sign-in, attendance changes, and operational actions
- `NotificationService` creates user-targeted or role-targeted notifications for reports and integration events

### Dynamics Integration

- `AttendanceSessionClosed` triggers a queued sync listener
- `DynamicsSyncService` writes sync ledger entries, calls the external API, retries on failure, and updates sync state
- `dynamics_syncs` preserves payloads, responses, retry counts, and error messages
