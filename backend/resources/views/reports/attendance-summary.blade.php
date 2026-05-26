<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Summary</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 18px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Attendance Summary</h1>
    <table>
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
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['session_date'] }}</td>
                    <td>{{ $row['class'] }}</td>
                    <td>{{ $row['student'] }}</td>
                    <td>{{ $row['admission_number'] }}</td>
                    <td>{{ $row['subject'] }}</td>
                    <td>{{ $row['teacher'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['remark'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
