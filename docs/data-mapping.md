# Data Mapping Pack

## Student Identity Mapping

- **Primary local key:** `students.id` (internal)
- **External key:** `students.external_reference` (Dynamics record reference)
- **Admission key:** `students.admission_number` (school-facing ID)
- **Dedup strategy:** upsert by `external_reference`, fallback `admission_number`

## Class and Teacher Mapping

- `classes.id` links to:
  - `students.class_id`
  - `attendance_sessions.class_id`
- `users.id` (teacher/admin/ict_staff) links to:
  - `attendance_sessions.teacher_id`
  - `attendance_records.marked_by`

## Attendance Mapping

- UI statuses (`present`, `missing`, `sick`, `on_leave`) are normalized to storage statuses:
  - `present -> present`
  - `missing -> absent`
  - `sick -> excused`
  - `on_leave -> late`

## Dynamics relationships (streams and students)

See [dynamics-relationships.md](./dynamics-relationships.md) for the official `ses_room` ↔ `ses_student` link via **Class Stream** (`cr0dc_classstream` / `_cr0dc_classstream_value`).

## Dynamics to Local Mapping (Student Sync)

- Dynamics `admission_number` -> `students.admission_number`
- Dynamics `external_reference` -> `students.external_reference`
- Dynamics `first_name`, `last_name`, `email`, `gender`, `dob` -> local equivalents
- Dynamics Form/Grade + Stream are used to resolve local `class_id` for attendance context

## Validation and Duplication Controls

- Unique indexes:
  - `students.admission_number`
  - `students.external_reference`
  - `attendance_records(attendance_session_id, student_id)`
- Sync ledger entries are tracked in `dynamics_syncs` for audit/retry visibility.
