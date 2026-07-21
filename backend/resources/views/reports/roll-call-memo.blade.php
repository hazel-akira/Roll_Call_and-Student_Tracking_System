<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Roll Call Memo</title>
    <style>
        @include('reports.partials.report-styles')

        .memo-page + .memo-page {
            page-break-before: always;
        }

        .col-student { width: 36%; }
        .col-remark { width: 12%; }
        .col-far { width: 8%; }
    </style>
</head>
<body>
    @php
        $generatedAt = now()->format('l, F j, Y g:i:s A');
    @endphp

    @forelse ($memos as $memo)
        @foreach ($memo['pages'] as $pageIndex => $page)
            <div class="memo-page">
                @include('reports.partials.school-header', [
                    'schoolName' => $memo['school_name'],
                    'schoolLogo' => $memo['school_logo'] ?? null,
                    'reportTitle' => $memo['title'],
                    'subtitle' => $memo['term_line'],
                ])

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

                <table class="data-table">
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

                @if ($pageIndex === count($memo['pages']) - 1)
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th colspan="2">Summary</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Total Students</td>
                                <td class="summary-value">{{ $memo['student_count'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <td>Total Present</td>
                                <td class="summary-value">{{ $memo['summary']['total_present'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <td>Total Absent</td>
                                <td class="summary-value">{{ $memo['summary']['total_absent'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <td>Total Late</td>
                                <td class="summary-value">{{ $memo['summary']['total_late'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <td>Total Excused</td>
                                <td class="summary-value">{{ $memo['summary']['total_excused'] ?? 0 }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            </div>
        @endforeach
    @empty
        <div class="memo-page">
            @include('reports.partials.school-header', [
                'schoolName' => 'School',
                'schoolLogo' => null,
                'reportTitle' => 'ROLL CALL MEMO',
                'subtitle' => null,
            ])
            <p style="text-align: center; color: #64748b;">No attendance sessions matched the selected filters.</p>
        </div>
    @endforelse

    <div class="report-footer">
        <table class="report-footer-table">
            <tr>
                <td class="footer-left">ROLL CALL MEMO</td>
                <td class="footer-center">{{ $generatedAt }}</td>
                <td class="footer-right">
                    <script type="text/php">
                        if (isset($pdf)) {
                            $font = $fontMetrics->getFont('DejaVu Sans');
                            $size = 8;
                            $color = [100 / 255, 116 / 255, 139 / 255];
                            $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';
                            $x = $pdf->get_width() - 95;
                            $y = $pdf->get_height() - 20;
                            $pdf->page_text($x, $y, $text, $font, $size, $color);
                        }
                    </script>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
