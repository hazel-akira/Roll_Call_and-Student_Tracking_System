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
