# API Reference (Core)

Base path: `/api/v1`

## Authentication

- `POST /auth/microsoft/exchange`
- `POST /auth/refresh`
- `GET /auth/me`
- `POST /auth/logout`

## Dashboards

- `GET /dashboard/teacher`
- `GET /dashboard/admin` (admin, ict_staff)

## Academic Data

- `GET /schools`
- `GET /classes`
- `GET /subjects`
- `GET /teachers` (admin, ict_staff)

## Students

- `GET /students`
- `GET /students/{student}`
- `GET /students/{student}/attendance-history`

## Attendance

- `GET /attendance-sessions`
- `POST /attendance-sessions`
- `GET /attendance-sessions/{attendanceSession}`
- `PUT /attendance-sessions/{attendanceSession}/records`
- `PATCH /attendance-sessions/{attendanceSession}/close`

## Reports

- `GET /reports/attendance-summary` (admin, ict_staff)
- `GET /reports/class-trends` (admin, ict_staff)
- `GET /reports/student-trends` (admin, ict_staff)
- `GET /reports/export` (admin, ict_staff)

## Dynamics Integration

- `GET /dynamics/syncs` (admin, ict_staff)
- `POST /dynamics/syncs/{dynamicsSync}/retry` (admin, ict_staff)
- `POST /dynamics/classes/{class}/students/sync` (admin, ict_staff)
- `GET /dynamics/attendance/form-streams`
- `GET /dynamics/attendance/students`

## Response Pattern

- Success responses return JSON with either:
  - resource payload (`data`, `stats`, etc.), or
  - operation metadata (`message`, queued status).
- Auth/role failures:
  - `401` for missing/invalid bearer token
  - `403` for role-restricted endpoints
