# Roll Call and Student Tracking System

Enterprise-grade attendance and student tracking platform organized as a monorepo with:

- `backend`: Laravel 13 API for auth, attendance, reporting, audit logs, notifications, and Dynamics sync jobs
- `frontend`: Next.js 16 dashboard for Microsoft sign-in, role-aware navigation, and teacher/admin workflows
- `docs`: architecture, development, deployment, and integration guidance

## Monorepo Layout

- `backend/app`: controllers, requests, actions, services, jobs, integrations, policies, and Eloquent models
- `backend/database`: migrations, seeders, and the local SQLite database used by the default scaffold
- `frontend/src/app`: authenticated dashboard routes and auth routes
- `frontend/src/components`: reusable layout, attendance, reports, students, dashboard, and UI components
- `frontend/src/lib`: API client, auth helpers, storage, and shared utilities
- `docs`: cross-cutting project guidance
- `docs/contracts`: API and integration contract notes that apply across apps

See `docs/architecture.md`, `docs/development.md`, and `docs/deployment.md` for the full project conventions.

## Environment Conventions

- Backend secrets live in `backend/.env` and should be bootstrapped from `backend/.env.example`
- Frontend browser-exposed variables live in `frontend/.env.local` and should be bootstrapped from `frontend/.env.example`
- Shared developer tooling targets PHP `8.3` and Node `20` to match CI
- Keep environment files app-local so frontend and backend can deploy independently

## Quick Start

### Repository-Wide

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env.local
make setup
```

### Manual Backend Setup

```bash
cd backend
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Manual Frontend Setup

```bash
cd frontend
npm install
npm run dev
```

## Default Roles

- `admin`
- `teacher`
- `ict_staff`

Seed these roles with:

```bash
cd backend
php artisan db:seed
```

## Validation Commands

```bash
make check
```

Equivalent app-specific commands:

```bash
cd backend && php artisan migrate:fresh --seed && php artisan test
cd frontend && npm run lint && npm run build
```

## Key Flows

1. User signs in with Microsoft Entra from the Next.js app.
2. Frontend posts the Microsoft ID token to the Laravel API.
3. Laravel validates the token, resolves the local user, and issues access and refresh JWTs.
4. Teachers create attendance sessions and submit attendance records.
5. Closing a session queues Microsoft Dynamics synchronization.
6. Admin and ICT users review reports, audit logs, notifications, and sync status.
