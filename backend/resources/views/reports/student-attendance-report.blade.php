<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Attendance Report</title>
    <style>
        @include('reports.partials.report-styles')

        .meta {
          
            color: #475569;
        }

        .meta p {
            margin: 2px 0;
        }

        .empty {
            padding: 12px;
            border: 1px dashed #cbd5e1;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    @include('reports.partials.school-header', [
        'schoolName' => $report['student']['school'] ?? 'School',
        'schoolLogo' => $report['school_logo'] ?? null,
        'reportTitle' => 'Student Attendance Report',
        'subtitle' => $report['student']['full_name'] ?? null,
    ])

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

    <table class="summary-table" style="width: 100%; margin: 0 0 14px;">
        <thead>
            <tr>
                <th colspan="6">Attendance Summary</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="meta-label">Total records</td>
                <td class="summary-value">{{ $report['summary']['records'] ?? 0 }}</td>
                <td class="meta-label">Present</td>
                <td class="summary-value">{{ $report['summary']['present'] ?? 0 }}</td>
                <td class="meta-label">Attendance rate</td>
                <td class="summary-value">{{ $report['summary']['attendance_rate'] ?? 0 }}%</td>
            </tr>
            <tr>
                <td class="meta-label">Absent</td>
                <td class="summary-value">{{ $report['summary']['absent'] ?? 0 }}</td>
                <td class="meta-label">Missing</td>
                <td class="summary-value">{{ $report['summary']['missing'] ?? 0 }}</td>
                <td class="meta-label">Late</td>
                <td class="summary-value">{{ $report['summary']['late'] ?? 0 }}</td>
            </tr>
            <tr>
                <td class="meta-label">Sick</td>
                <td class="summary-value">{{ $report['summary']['sick'] ?? 0 }}</td>
                <td class="meta-label">On leave</td>
                <td class="summary-value">{{ $report['summary']['on_leave'] ?? 0 }}</td>
                <td class="meta-label">Excused</td>
                <td class="summary-value">{{ $report['summary']['excused'] ?? 0 }}</td>
            </tr>
        </tbody>
    </table>

    @if (($report['rows'] ?? collect())->isEmpty())
        <p class="empty">No attendance records found for this student in the selected period.</p>
    @else
        <table class="data-table">
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
                        <td class="status-cell">{{ $row['status'] }}</td>
                        <td>{{ $row['remark'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="report-footer">
        <table class="report-footer-table">
            <tr>
                <td class="footer-left">STUDENT ATTENDANCE REPORT</td>
                <td class="footer-center">{{ $generated_at }}</td>
                <td class="footer-right">{{ $report['student']['admission_number'] ?? '' }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
