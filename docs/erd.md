# Entity Relationship Diagram (ERD)

```mermaid
erDiagram
    ROLES ||--o{ USERS : assigns
    USERS ||--o{ USER_IDENTITIES : has
    USERS ||--o{ REFRESH_TOKENS : owns
    USERS ||--o{ ATTENDANCE_SESSIONS : teaches
    USERS ||--o{ ATTENDANCE_RECORDS : marks
    USERS ||--o{ AUDIT_LOGS : performs
    USERS ||--o{ NOTIFICATIONS : receives

    SCHOOLS ||--o{ CLASSES : contains
    SCHOOLS }o--o{ USERS : "school_user"

    CLASSES ||--o{ STUDENTS : enrolls
    CLASSES ||--o{ ATTENDANCE_SESSIONS : schedules

    SUBJECTS ||--o{ ATTENDANCE_SESSIONS : used_in
    USERS }o--o{ SUBJECTS : "teacher_subjects"

    ATTENDANCE_SESSIONS ||--o{ ATTENDANCE_RECORDS : records
    ATTENDANCE_SESSIONS ||--o{ DYNAMICS_SYNCS : sync_ledger

    STUDENTS ||--o{ ATTENDANCE_RECORDS : has
```

## Core tables

- `users`, `roles`, `user_identities`, `refresh_tokens`
- `schools`, `classes`, `school_user`, `teacher_subjects`
- `students`, `subjects`
- `attendance_sessions`, `attendance_records`
- `dynamics_syncs`
- `audit_logs`, `notifications`
