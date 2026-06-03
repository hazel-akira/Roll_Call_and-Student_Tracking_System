# Dynamics Sync Validation

## Scope

- Validate student sync from Dynamics -> local database
- Validate no duplicate student creation on repeated sync
- Validate attendance-form stream retrieval behavior

## Automated Validation

- Backend test added:
  - `tests/Feature/Integrations/DynamicsStudentSyncServiceTest.php`
- Coverage:
  - existing student is updated (not duplicated)
  - new student is inserted
  - fetched/synced counts are tracked

## Manual Validation Checklist

- [ ] Run class sync for sample class with known students
- [ ] Re-run sync for same class
- [ ] Confirm local `students` count does not duplicate
- [ ] Confirm updated profile fields are refreshed
- [ ] Confirm attendance page stream options load for selected school
- [ ] Confirm fallback behavior when Dynamics endpoint is unavailable

## Duplicate Controls

- Unique keys on `students.admission_number` and `students.external_reference`
- Upsert logic in `DynamicsStudentSyncService`
- Sync execution metadata in `dynamics_syncs`
