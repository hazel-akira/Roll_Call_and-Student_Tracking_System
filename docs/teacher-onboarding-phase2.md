# Teacher onboarding — Phase 2 (Dataverse)

Deferred until faculty email data in Dataverse reliably matches Microsoft login emails.

## Goal

Use Dataverse (`ses_faculties`) as a **staff directory** for duty roster and attendance linking — not as the primary login account source.

## Planned work

1. Add `external_reference` (nullable, unique) to `users` to link local accounts to Dataverse faculty rows.
2. Implement `DynamicsTeacherSyncService` to bulk fetch `ses_faculties` (extend beyond the existing name search in `DynamicsService`).
3. Filament admin action: **Import faculty from Dataverse** with manual link/unlink when email does not match.
4. Duty roster UI: include synced faculty who have not signed in yet.
5. Attendance export: bind `ses_faculties` lookup in `DynamicsAttendanceWriter` instead of plain-text `ses_facultyname`.

## Prerequisites

- Populate `School.dynamics_id` for all tenant schools.
- Confirm `ses_faculties.emailaddress1` (or linked contact email) matches Entra UPN for most staff.

## Current state (Phase 1)

- Teachers sign in with Microsoft SSO.
- New teachers are auto-created as `active` with role `teacher` when `AUTH_AUTO_ACTIVATE_SSO_USERS=true`.
- Teachers self-select schools on first login at `/onboarding/schools`.
