# Teacher onboarding rollout

Use this checklist when enabling login-first teacher onboarding in production.

## Before go-live

1. Set backend env:
   - `AUTH_AUTO_ACTIVATE_SSO_USERS=true`
   - `AUTH_DEFAULT_ROLE_SLUG=teacher`
   - `MICROSOFT_ALLOWED_TENANT_IDS=<your-azure-tenant-id>`
   - `MICROSOFT_ALLOWED_DOMAINS=<comma-separated staff email domains>`
2. Confirm frontend env matches the same tenant:
   - `NEXT_PUBLIC_MICROSOFT_AUTHORITY=https://login.microsoftonline.com/<tenant-id>`
   - `NEXT_PUBLIC_MICROSOFT_CLIENT_ID=<entra-app-id>`
3. Deploy backend and frontend, then run migrations if needed.

## Pilot (5–10 teachers)

1. Ask a small group of teachers to sign in with Microsoft on the Next.js app.
2. Confirm each teacher lands on **Choose your school** (`/onboarding/schools`) on first login.
3. Confirm they reach `/teacher` after selecting a school and can open a roll call session.
4. In Filament **Users**, verify new accounts show role `teacher`, status `active`, and the expected school assignment.

## ICT review after pilot

1. Open **Admin → Users** and scan for unexpected accounts created during the pilot.
2. **Deactivate** any non-staff accounts that passed tenant/domain filters.
3. Manually assign schools for any teacher who picked the wrong school.
4. Keep dean/admin/ICT roles manual — do not enable self-service elevation.

## Rollback

If you need to revert to admin-approved onboarding:

```env
AUTH_AUTO_ACTIVATE_SSO_USERS=false
```

New sign-ins will return to `pending` until an admin grants access in Filament.
