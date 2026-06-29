<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Roll Call Memo</title>
    <style>
        @page {
            margin: 24px 28px 40px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #000;
            margin: 0;
        }

        .memo-page {
            page-break-after: always;
        }

        .memo-page:last-child {
            page-break-after: auto;
        }

        .school-name {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 6px;
        }

        .memo-title {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            margin: 0 0 8px;
        }

        .term-line {
            text-align: center;
            font-size: 10px;
            margin: 0 0 10px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .meta-table td {
            border: 1px solid #000;
            padding: 5px 8px;
            vertical-align: top;
            width: 50%;
        }

        .meta-label {
            font-weight: bold;
        }

        .roll-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .roll-table th,
        .roll-table td {
            border: 1px solid #000;
            padding: 4px 5px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        .roll-table thead th {
            text-align: center;
            font-weight: bold;
            background: #fff;
        }

        .col-student {
            width: 36%;
        }

        .col-remark {
            width: 12%;
        }

        .col-far {
            width: 8%;
        }

        .subhead {
            font-size: 9px;
            font-weight: normal;
        }

        .status-cell {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
        }

        .time-in {
            font-size: 8px;
            font-weight: normal;
            font-style: italic;
            margin-top: 2px;
        }

        .footer {
            position: fixed;
            bottom: 10px;
            left: 28px;
            right: 28px;
            font-size: 9px;
            border-top: 1px solid #000;
            padding-top: 4px;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td {
            border: none;
            padding: 0;
        }

        .footer-left {
            text-align: left;
            font-weight: bold;
        }

        .footer-center {
            text-align: center;
        }

        .footer-right {
            text-align: right;
        }
    </style>
</head>
<body>
    @php
        $generatedAt = now()->format('l, F j, Y g:i:s A');
    @endphp

    @forelse ($memos as $memo)
        @foreach ($memo['pages'] as $pageIndex => $page)
            <div class="memo-page">
                <p class="school-name">{{ $memo['school_name'] }}</p>
                <p class="memo-title">{{ $memo['title'] }}</p>
                <p class="term-line">{{ $memo['term_line'] }}</p>

                <table class="meta-table">
                    <tr>
                        <td>
                            <span class="meta-label">NO OF STUDENTS:</span> {{ $memo['student_count'] }}
                        </td>
                        <td>
                            <span class="meta-label">Stream/Class:</span> {{ $memo['stream_class'] }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="meta-label">Department/Module:</span> {{ $memo['department'] }}
                        </td>
                        <td>
                            <span class="meta-label">Date:</span> {{ $memo['date_formatted'] }}
                        </td>
                    </tr>
                </table>

                <table class="roll-table">
                    <thead>
                        <tr>
                            <th class="col-student">Students</th>
                            <th colspan="4">Remarks</th>
                            <th class="col-far">FAR</th>
                        </tr>
                        <tr>
                            <th></th>
                            <th class="col-remark">Present</th>
                            <th class="col-remark">
                                Absent
                                <div class="subhead">comment: Time In</div>
                            </th>
                            <th class="col-remark">
                                Late
                                <div class="subhead">comment: Time In</div>
                            </th>
                            <th class="col-remark">Excused</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($page['rows'] as $row)
                            <tr>
                                <td>{{ $row['student_name'] }}</td>
                                <td class="status-cell">{{ $row['present'] }}</td>
                                <td class="status-cell">
                                    {{ $row['absent'] }}
                                    @if ($row['absent'] !== '' && $row['time_in'] !== '')
                                        <div class="time-in">{{ $row['time_in'] }}</div>
                                    @endif
                                </td>
                                <td class="status-cell">
                                    {{ $row['late'] }}
                                    @if ($row['late'] !== '' && $row['time_in'] !== '')
                                        <div class="time-in">{{ $row['time_in'] }}</div>
                                    @endif
                                </td>
                                <td class="status-cell">{{ $row['excused'] }}</td>
                                <td>{{ $row['far'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @empty
        <div class="memo-page">
            <p class="memo-title">ROLL CALL MEMO</p>
            <p class="term-line">No attendance sessions matched the selected filters.</p>
        </div>
    @endforelse

    <div class="footer">
        <table class="footer-table">
            <tr>
                <td class="footer-left">ROLL CALL MEMO</td>
                <td class="footer-center">{{ $generatedAt }}</td>
                <td class="footer-right">
                    <script type="text/php">
                        if (isset($pdf)) {
                            $text = "Page {PAGE_NUM} of {PAGE_COUNT}";
                            $font = $fontMetrics->getFont("DejaVu Sans");
                            $pdf->page_text(500, 820, $text, $font, 9, [0, 0, 0]);
                        }
                    </script>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
