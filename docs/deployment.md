# Deployment Guide

For Docker and Coolify deployment, see [coolify.md](./coolify.md).

## Recommended Topology

### Backend

Deploy Laravel behind Nginx or Apache to a managed PHP runtime with:

- PHP 8.3+
- queue worker process for `SyncAttendanceToDynamics` and `GenerateAttendanceExport`
- scheduler for future maintenance tasks
- shared writable storage for report outputs
- managed MySQL in production

### Frontend

Deploy Next.js to a Node-compatible platform such as Vercel, Azure App Service, or a containerized Node runtime.

### Supporting Services

- MySQL for the primary relational store
- Redis for queues and cache in production-scale environments
- object storage or persistent disk for generated exports
- Microsoft Entra multi-tenant app registration for sign-in
- Microsoft Dynamics credentials for server-to-server synchronization

## Environment Variables

### Backend

Required in `backend/.env`:

- `APP_URL`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `JWT_SECRET`, `JWT_TTL`, `JWT_REFRESH_TTL`
- `MICROSOFT_CLIENT_ID`
- `MICROSOFT_ALLOWED_TENANT_IDS`
- `MICROSOFT_ALLOWED_DOMAINS`
- `AUTH_AUTO_ACTIVATE_SSO_USERS=true` (teachers auto-activate on first Microsoft sign-in; restrict tenant/domain first)
- `DYNAMICS_BASE_URL`, `DYNAMICS_TENANT_ID`, `DYNAMICS_CLIENT_ID`, `DYNAMICS_CLIENT_SECRET`, `DYNAMICS_SCOPE`

### Frontend

Required in `frontend/.env.local`:

- `NEXT_PUBLIC_APP_URL`
- `NEXT_PUBLIC_API_URL`
- `NEXT_PUBLIC_MICROSOFT_CLIENT_ID`
- `NEXT_PUBLIC_MICROSOFT_AUTHORITY`
- `NEXT_PUBLIC_MICROSOFT_REDIRECT_URI`

## CI/CD Checks

Recommended pipeline stages:

1. Install backend and frontend dependencies
2. Run `php artisan migrate:fresh --seed`
3. Run `php artisan test`
4. Run `npm run lint`
5. Run `npm run build`
6. Deploy backend and frontend independently

## Operational Notes

- Run Laravel queue workers continuously in production
- Monitor failed jobs, Dynamics sync failures, and audit log volume
- Rotate Microsoft and JWT secrets through your secret manager
- Use HTTPS everywhere and secure CORS settings between frontend and backend origins
- Back up MySQL and generated export files on a schedule
