# Filament admin panels

The Laravel backend includes two [Filament](https://filamentphp.com) panels for operational management alongside the Next.js app.

## Panels

| Panel | URL | Who can sign in |
|-------|-----|-----------------|
| **Admin** | `/admin` | Users with role `admin` or `ict_staff` |
| **Teacher** | `/teacher` | Users with role `teacher`, `admin`, or `ict_staff` |

Users must be `active` and have a **password** set to use panel login (Microsoft SSO users created without a password need one assigned in the admin panel).

## Admin panel

- **Schools** — create and edit schools (name, code, level, Dynamics ID, active flag).
- **Users** — manage everyone who signs in to Roll Call:
  - **Awaiting access** tab — users who tried Microsoft sign-in but are not approved yet (`pending`).
  - **Grant access** — assign role + schools and activate the account (one-click from the list or edit screen).
  - **Revoke access** — set account to inactive.
  - **School access** — multi-select on the form or the School access relation tab.
  - **Microsoft sign-in** tab — see when they last attempted SSO.

New Microsoft users are created as **pending** by default (`AUTH_AUTO_ACTIVATE_SSO_USERS=false`). They cannot get a JWT until an admin grants access and (for teachers) assigns at least one school.

Administrators and ICT staff can access all schools in the API without explicit assignments. Teachers must be linked to at least one school before they can sign in.

## Teacher panel

- **Attendance sessions** — list and create roll-call sessions for classes the user can access; close open sessions from the edit screen.

Teachers only see their own sessions. Admins and ICT staff see all sessions (or those in assigned schools if school links exist).

## First-time setup

From `backend/`:

```bash
composer install
php artisan migrate --seed
php artisan make:filament-user
```

Choose the **admin** panel when prompted, or create a user in **Users** with role Administrator and a password, then open `/admin`.

For local development, run the API server:

```bash
php artisan serve
```

Visit `http://127.0.0.1:8000/admin` or `/teacher`.

## Environment

Filament uses the default `web` session guard (`config/auth.php`). No extra env keys are required beyond a working database and `APP_URL`.

Optional seed admin (API/SSO) without a panel password:

```env
SEED_ADMIN_EMAIL=admin@example.com
SEED_ADMIN_NAME="Platform Administrator"
```

Set a password for that user in **Users** before using Filament login.
