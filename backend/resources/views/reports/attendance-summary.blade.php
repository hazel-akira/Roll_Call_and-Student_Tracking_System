<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Summary</title>
    <style>
        @include('reports.partials.report-styles')
    </style>
</head>
<body>
    @include('reports.partials.school-header', [
        'schoolName' => $school_name ?? 'School',
        'schoolLogo' => $school_logo ?? null,
        'reportTitle' => 'Attendance Summary',
        'subtitle' => $subtitle ?? null,
    ])

    <table class="data-table">
        <thead>
            <tr>
                <th>Session Date</th>
                <th>Class</th>
                <th>Student</th>
                <th>Admission Number</th>
                <th>Subject</th>
                <th>Teacher</th>
                <th>Status</th>
                <th>Remark</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['session_date'] }}</td>
                    <td>{{ $row['class'] }}</td>
                    <td>{{ $row['student'] }}</td>
                    <td>{{ $row['admission_number'] }}</td>
                    <td>{{ $row['subject'] }}</td>
                    <td>{{ $row['teacher'] }}</td>
                    <td class="status-cell">{{ $row['status'] }}</td>
                    <td>{{ $row['remark'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; color: #64748b;">No records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="report-footer">
        <table class="report-footer-table">
            <tr>
                <td class="footer-left">ATTENDANCE SUMMARY</td>
                <td class="footer-center">{{ now()->format('l, F j, Y g:i A') }}</td>
                <td class="footer-right"></td>
            </tr>
        </table>
    </div>
</body>
</html>
