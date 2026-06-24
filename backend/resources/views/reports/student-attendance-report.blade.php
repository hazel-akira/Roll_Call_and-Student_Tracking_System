<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Attendance Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { margin-bottom: 16px; color: #4b5563; }
        .meta p { margin: 2px 0; }
        .summary { width: 100%; margin-bottom: 16px; border-collapse: collapse; }
        .summary td { border: 1px solid #d1d5db; padding: 6px 8px; }
        .summary .label { background: #f3f4f6; font-weight: bold; width: 18%; }
        table.records { width: 100%; border-collapse: collapse; }
        table.records th, table.records td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; vertical-align: top; }
        table.records th { background: #f3f4f6; }
        .empty { padding: 12px; border: 1px dashed #d1d5db; color: #6b7280; }
    </style>
</head>
<body>
    <h1>Student Attendance Report</h1>
    <div class="meta">
        <p><strong>{{ $report['student']['full_name'] ?? 'Student' }}</strong></p>
        <p>Admission #: {{ $report['student']['admission_number'] ?? '—' }}</p>
        <p>Class: {{ $report['student']['class'] ?? '—' }}</p>
        <p>School: {{ $report['student']['school'] ?? '—' }}</p>
        <p>Period:
            @if (!empty($report['filters']['from']) || !empty($report['filters']['to']))
                {{ $report['filters']['from'] ?? 'Start' }} to {{ $report['filters']['to'] ?? 'Present' }}
            @else
                All recorded sessions
            @endif
        </p>
        <p>Generated: {{ $generated_at }}</p>
    </div>

    <table class="summary">
        <tr>
            <td class="label">Total records</td>
            <td>{{ $report['summary']['records'] ?? 0 }}</td>
            <td class="label">Present</td>
            <td>{{ $report['summary']['present'] ?? 0 }}</td>
            <td class="label">Attendance rate</td>
            <td>{{ $report['summary']['attendance_rate'] ?? 0 }}%</td>
        </tr>
        <tr>
            <td class="label">Absent</td>
            <td>{{ $report['summary']['absent'] ?? 0 }}</td>
            <td class="label">Missing</td>
            <td>{{ $report['summary']['missing'] ?? 0 }}</td>
            <td class="label">Late</td>
            <td>{{ $report['summary']['late'] ?? 0 }}</td>
        </tr>
        <tr>
            <td class="label">Sick</td>
            <td>{{ $report['summary']['sick'] ?? 0 }}</td>
            <td class="label">On leave</td>
            <td>{{ $report['summary']['on_leave'] ?? 0 }}</td>
            <td class="label">Excused</td>
            <td>{{ $report['summary']['excused'] ?? 0 }}</td>
        </tr>
    </table>

    @if (($report['rows'] ?? collect())->isEmpty())
        <p class="empty">No attendance records found for this student in the selected period.</p>
    @else
        <table class="records">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Session</th>
                    <th>Class</th>
                    <th>Subject</th>
                    <th>Teacher</th>
                    <th>Status</th>
                    <th>Remark</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['rows'] as $row)
                    <tr>
                        <td>{{ $row['session_date'] }}</td>
                        <td>{{ $row['session_title'] }}</td>
                        <td>{{ $row['class'] }}</td>
                        <td>{{ $row['subject'] }}</td>
                        <td>{{ $row['teacher'] }}</td>
                        <td>{{ $row['status'] }}</td>
                        <td>{{ $row['remark'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
