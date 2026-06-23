# Coolify deployment

Deploy as **three Coolify applications** (recommended) or one **Docker Compose** resource.

## What was wrong locally

1. **Database never migrated** — `backend/database/database.sqlite` did not exist, so API calls failed.
2. **Microsoft auth misconfigured in frontend** — `NEXT_PUBLIC_MICROSOFT_AUTHORITY` still had the placeholder `YOUR_TENANT_ID`. It must match your Azure tenant ID (same value as `MICROSOFT_ALLOWED_TENANT_IDS` in the backend).
3. **Missing `FRONTEND_URL` in backend** — CORS defaults to `http://localhost:3000`, but production needs the real frontend URL.

## Option A — Separate Coolify apps (recommended)

### 1. MySQL database

In Coolify: **New Resource → Database → MySQL 8**

Note the internal hostname (e.g. `mysql`) and credentials.

### 2. Backend API

| Setting | Value |
|---------|-------|
| Build pack | Dockerfile |
| Base directory | `backend` |
| Dockerfile | `Dockerfile` |
| Port | `80` |
| Health check | `GET /up` |
| Persistent storage | `/var/www/html/storage` |

**Required environment variables** (from `backend/.env.example`):

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=<php artisan key:generate --show>
APP_URL=https://api.yourdomain.com
FRONTEND_URL=https://app.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=<coolify-mysql-host>
DB_PORT=3306
DB_DATABASE=roll_call
DB_USERNAME=roll_call
DB_PASSWORD=<secret>

JWT_SECRET=<random-base64-secret>
JWT_ISSUER=https://api.yourdomain.com

MICROSOFT_CLIENT_ID=<entra-app-id>
MICROSOFT_ALLOWED_TENANT_IDS=<azure-tenant-id>
MICROSOFT_JWKS_URL=https://login.microsoftonline.com/<tenant-id>/discovery/v2.0/keys

DYNAMICS_ENABLED=true
DYNAMICS_BASE_URL=...
DYNAMICS_TENANT_ID=...
DYNAMICS_CLIENT_ID=...
DYNAMICS_CLIENT_SECRET=...
DYNAMICS_SCOPE=...
```

After first deploy, open the Coolify terminal and run:

```bash
php artisan make:filament-user
```

### 3. Queue worker

Duplicate the backend app (or add a worker service):

| Setting | Value |
|---------|-------|
| Same image/build as backend | yes |
| Start command | `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` |
| HTTP port | none |
| Persistent storage | `/var/www/html/storage` (same as backend) |

### 4. Frontend

| Setting | Value |
|---------|-------|
| Build pack | Dockerfile |
| Base directory | `frontend` |
| Dockerfile | `Dockerfile` |
| Port | `3000` |

**Build arguments** (required — baked in at build time):

```
NEXT_PUBLIC_APP_URL=https://app.yourdomain.com
NEXT_PUBLIC_API_URL=https://api.yourdomain.com/api/v1
NEXT_PUBLIC_MICROSOFT_CLIENT_ID=<entra-app-id>
NEXT_PUBLIC_MICROSOFT_TENANT_ID=<azure-tenant-id>
NEXT_PUBLIC_MICROSOFT_AUTHORITY=https://login.microsoftonline.com/<tenant-id>
NEXT_PUBLIC_MICROSOFT_REDIRECT_URI=https://app.yourdomain.com/callback
```

Rebuild the frontend whenever any `NEXT_PUBLIC_*` value changes.

### 5. Microsoft Entra

In your app registration, add redirect URI:

```
https://app.yourdomain.com/callback
```

## Option B — Docker Compose on Coolify

1. **New Resource → Docker Compose**
2. Point at this repo; Coolify uses root `docker-compose.yml`
3. Copy `.env.docker.example` to `.env` on the server and fill in secrets
4. **Do not set `BACKEND_PORT` / `FRONTEND_PORT`** — Coolify routes via its proxy to container ports internally (backend **80**, frontend **3000**).
5. Map domains in Coolify:
   - `backend` → `api.yourdomain.com` (port 80)
   - `frontend` → `app.yourdomain.com` (port 3000)

### Compose env checklist

Set these in Coolify (Compose environment / `.env` on the server):

| Variable | Notes |
|----------|-------|
| `APP_KEY` | `php artisan key:generate --show` — must start with `base64:` |
| `APP_URL` | Public API URL, e.g. `https://api.yourdomain.com` |
| `FRONTEND_URL` | Public app URL, e.g. `https://app.yourdomain.com` |
| `DB_PASSWORD` | Must match for both `mysql` and `backend` services |
| `DB_ROOT_PASSWORD` | MySQL root password |
| `JWT_SECRET` | Random secret string |
| `NEXT_PUBLIC_*` | Required frontend build args (see `.env.docker.example`) |

If you change `DB_PASSWORD` after the first deploy, the existing MySQL volume keeps the old password. Either restore the original password or delete the `mysql_data` volume and redeploy (this wipes the database).

### Build fails with exit code 255 (timeout during backend build)

Coolify logs may show `Added 111 ARG declarations to Dockerfile for service backend` and the build dies while compiling PHP extensions.

**Cause:** First backend image build is heavy (PHP extensions + Composer + Vite assets). Small VPS instances often hit a **build timeout** or **out of memory** around 4–8 minutes.

**Fixes (try in order):**

1. **Redeploy with Rebuild** — frontend may already be cached; backend may complete on retry.
2. **Coolify → your resource → Settings** — disable **Inject Build Args to Dockerfile** for the backend service if available (v4.0.450+). Backend only needs runtime env vars, not build args. Only `frontend` needs `NEXT_PUBLIC_*` at build time.
3. **Increase server RAM** — 4 GB minimum recommended for first compose build.
4. **Pull latest `main`** — backend Dockerfile uses `install-php-extensions` (pre-built binaries) for faster builds.
5. Wait **10–15 minutes** on first deploy before assuming failure.

**URL env vars:** Every `APP_URL`, `FRONTEND_URL`, and `NEXT_PUBLIC_*` value must include `https://` (e.g. `https://your-domain.sandbox...`, not bare hostname). Missing `https://` causes CORS "Network Error" on login.

### Backend unhealthy on deploy

The backend entrypoint runs migrations in the background while nginx starts. First deploy can take 30–90 seconds before `/up` returns 200. The compose health check allows a **180s** start period and treats bootstrap as healthy until migrations finish.

If deploy still fails:

1. Open the **backend container logs** in Coolify (or `docker logs <backend-container>` on the server).
2. Look for `ERROR:` lines from the entrypoint — common causes:
   - `APP_KEY is not set`
   - `Database not reachable` — wrong `DB_PASSWORD` or MySQL volume out of sync
   - `Database migration failed` — inspect the SQL error above it
3. Confirm `DB_PASSWORD` is identical for the mysql and backend services (compose uses the same variable for both).
4. Redeploy after fixing env vars; no need to change compose port mappings.

### Microsoft login: "no such table: cache" (sqlite)

If sign-in shows an error mentioning `Connection: sqlite` and `database/database.sqlite`, the API is **not using MySQL** and migrations have not created the `cache` table.

Fix:

1. In Coolify env, set `DB_CONNECTION=mysql` (and `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` for standalone backend apps).
2. For Docker Compose, confirm the backend service is healthy — its entrypoint runs `php artisan migrate --force` on start.
3. Open the backend terminal and run: `php artisan config:clear && php artisan migrate --force && php artisan config:cache`
4. Redeploy the backend so cached config is rebuilt with MySQL settings (Laravel ignores `.env` changes while `bootstrap/cache/config.php` exists).

## Local Docker test

```bash
cp .env.docker.example .env
# Edit .env with your values
docker compose -f docker-compose.yml -f docker-compose.local.yml up --build
```

API: http://localhost:8000/up  
App: http://localhost:3000

## Local dev (without Docker)

```bash
# Backend
cd backend
touch database/database.sqlite
php artisan migrate --seed
php artisan serve

# Queue (separate terminal)
php artisan queue:work

# Frontend — ensure .env has correct tenant ID, not YOUR_TENANT_ID
cd frontend
npm run dev
```
