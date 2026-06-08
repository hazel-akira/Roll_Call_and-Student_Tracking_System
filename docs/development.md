# Development Guide

## Monorepo Shape

This repository keeps deployable applications isolated while documenting shared platform conventions at the root:

- `backend/` contains the Laravel API, queued jobs, domain services, and persistence layer
- `frontend/` contains the Next.js dashboard, route groups, UI components, and browser auth flow
- `docs/` contains cross-cutting guidance that applies to both applications
- `.github/workflows/` contains CI definitions for repository-wide validation

Recommended code organization inside each app:

- `backend/app/Http/Controllers`, `backend/app/Http/Requests`, and `backend/app/Http/Middleware` stay transport-focused
- `backend/app/Actions`, `backend/app/Services`, and `backend/app/Integrations` hold orchestration and business logic
- `backend/app/Jobs` and `backend/app/Policies` are reserved for async workflows and authorization rules
- `frontend/src/app` holds route groups and pages
- `frontend/src/components` holds reusable UI grouped by domain or layout area
- `frontend/src/lib` holds auth, API, and generic utility code
- `docs/contracts` is reserved for API payload, auth, and integration contract notes

## Environment Conventions

Environment files stay local to each deployable app:

- Copy `backend/.env.example` to `backend/.env`
- Copy `frontend/.env.example` to `frontend/.env.local`
- Do not introduce a shared root `.env`; backend and frontend should remain independently deployable

Local defaults assume:

- PHP `8.3`
- Node `20` from `.nvmrc`
- SQLite for backend local bootstrap unless a different database connection is configured

The root `Makefile` provides a shared entry point for common setup and validation commands.

## Filament panels (backend)

Administrators manage schools and user school access at `/admin`. Teachers manage attendance sessions at `/teacher`. See [filament-panels.md](./filament-panels.md).

## Local Workflow

Use the shared targets when you want one command surface at the repository root:

```bash
make setup
make backend-test
make frontend-lint
make frontend-build
make check
```

Equivalent app-local commands remain valid when you are working within a single app.

## CI Conventions

The repository CI workflow keeps backend and frontend validation separate so each app can evolve independently:

1. Backend CI installs Composer dependencies, prepares `backend/.env`, creates `backend/database/database.sqlite`, runs migrations and seeders, then runs `php artisan test`
2. Frontend CI installs dependencies with Node `20`, prepares `frontend/.env.local`, then runs lint and production build checks
3. Shared root files such as `.editorconfig`, `.nvmrc`, `Makefile`, `README.md`, and workflow definitions are treated as monorepo contract files and should stay in sync with both apps

## Documentation Expectations

Add cross-app guidance under `docs/` when a convention affects more than one application. Keep implementation-specific details inside the relevant app unless another app or deployment process depends on them.
