<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Weekly Roll Call Sheet</title>
    <style>
        @page {
            margin: 20px 24px;
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

        .school-header {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 4px;
        }

        .year-header {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            margin: 0 0 10px;
        }

        .week-line {
            margin-bottom: 10px;
            font-size: 10px;
        }

        .week-line span {
            display: inline-block;
            min-width: 80px;
            margin-right: 18px;
        }

        .sheet-layout {
            width: 100%;
            border-collapse: collapse;
        }

        .sheet-layout > tbody > tr > td {
            vertical-align: top;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-table th,
        .attendance-table td {
            border: 1px solid #000;
            padding: 5px 6px;
            text-align: center;
        }

        .attendance-table th {
            font-weight: bold;
            background: #fff;
        }

        .attendance-table .class-col {
            text-align: left;
        }

        .attendance-table .total-row td {
            font-weight: bold;
        }

        .duty-panel {
            width: 170px;
            border: 1px solid #000;
            padding: 8px;
            font-size: 8px;
            min-height: 220px;
        }

        .duty-week-label {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 8px;
            font-size: 9px;
        }

        .duty-section-title {
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 8px;
            margin-bottom: 2px;
        }

        .duty-row {
            margin-bottom: 4px;
            line-height: 1.35;
        }

        .duty-line {
            margin-bottom: 14px;
            min-height: 14px;
        }

        .signature-block {
            margin-top: 18px;
        }

        .signature-line {
            margin-top: 24px;
            border-top: 1px solid #000;
            padding-top: 4px;
        }

        .absentee-section {
            margin-top: 14px;
        }

        .absentee-title {
            font-weight: bold;
            margin-bottom: 6px;
            font-size: 10px;
        }

        .absentee-table {
            width: 100%;
            border-collapse: collapse;
        }

        .absentee-table th,
        .absentee-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: left;
        }

        .absentee-table th {
            font-weight: bold;
        }

        .na-row td {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            height: 48px;
        }
    </style>
</head>
<body>
    <p class="school-header">{{ $sheet['school_name'] }}</p>
    <p class="year-header">YEAR {{ $sheet['year'] }}</p>

    <div class="week-line">
        <span><strong>WEEK</strong> {{ $sheet['week_number'] }}</span>
        <span><strong>FROM</strong> {{ $sheet['from_date'] }}</span>
        <span><strong>TO</strong> {{ $sheet['to_date'] }}</span>
    </div>

    <table class="sheet-layout">
        <tr>
            <td>
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>DAY</th>
                            <th>PERIOD</th>
                            <th>CLASS</th>
                            <th>NO. EXPECTED</th>
                            <th>NO. PRESENT</th>
                            <th>NO. ABSENT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sheet['period_groups'] as $dayGroup)
                            @php
                                $dayRowCount = 0;
                                foreach ($dayGroup['periods'] as $period) {
                                    $dayRowCount += count($period['classes']) + 1;
                                }
                            @endphp
                            @foreach ($dayGroup['periods'] as $periodIndex => $periodGroup)
                                @foreach ($periodGroup['classes'] as $classIndex => $classRow)
                                    <tr>
                                        @if ($periodIndex === 0 && $classIndex === 0)
                                            <td rowspan="{{ $dayRowCount }}">
                                                {{ strtoupper($dayGroup['day']) }}
                                            </td>
                                        @endif
                                        @if ($classIndex === 0)
                                            <td rowspan="{{ count($periodGroup['classes']) + 1 }}">
                                                {{ strtoupper($periodGroup['period']) }}
                                            </td>
                                        @endif
                                        <td class="class-col">{{ strtoupper($classRow['class']) }}</td>
                                        <td>{{ str_pad((string) $classRow['expected'], 2, '0', STR_PAD_LEFT) }}</td>
                                        <td>{{ str_pad((string) $classRow['present'], 2, '0', STR_PAD_LEFT) }}</td>
                                        <td>{{ $classRow['absent'] }}</td>
                                    </tr>
                                @endforeach
                                <tr class="total-row">
                                    <td class="class-col">TOTAL</td>
                                    <td>{{ str_pad((string) $periodGroup['total']['expected'], 2, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ str_pad((string) $periodGroup['total']['present'], 2, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ $periodGroup['total']['absent'] }}</td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="6">No attendance sessions matched the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td style="width: 180px; padding-left: 8px;">
                <div class="duty-panel">
                    @if (! empty($duty_roster))
                        <div class="duty-week-label">Weekly Duty: {{ $duty_roster['week_label'] }}</div>
                        @foreach ($duty_roster['sections'] as $section)
                            <div class="duty-section-title">{{ $section['title'] }}</div>
                            @foreach ($section['rows'] as $row)
                                <div class="duty-row">
                                    @if ($row['location'])
                                        <strong>{{ $row['location'] }}:</strong>
                                    @endif
                                    @if ($row['time_slot'])
                                        <em>{{ $row['time_slot'] }}</em>
                                    @endif
                                    {{ $row['staff'] ?: '—' }}
                                </div>
                            @endforeach
                        @endforeach
                    @else
                        <div class="duty-section-title">Teachers on Duty:</div>
                        @php($dutyTeachers = $duty_teachers ?? [])
                        @foreach ($dutyTeachers as $index => $teacherName)
                            <div class="duty-line">{{ $index + 1 }}. {{ $teacherName }}</div>
                        @endforeach
                        @for ($slot = count($dutyTeachers); $slot < 2; $slot++)
                            <div class="duty-line">{{ $slot + 1 }}.</div>
                        @endfor
                    @endif

                    <div class="signature-block">
                        <strong>ROLL CALL BY:</strong>
                        <div class="signature-line"></div>
                    </div>

                    <div class="signature-block">
                        <strong>Checked by:</strong>
                        <div class="signature-line"></div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="absentee-section">
        <div class="absentee-title">REASON FOR ABSENTEEISM</div>
        <table class="absentee-table">
            <thead>
                <tr>
                    <th style="width: 35%;">NAME</th>
                    <th>REASON</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sheet['absentees'] as $absentee)
                    <tr>
                        <td>{{ $absentee['name'] }}</td>
                        <td>{{ $absentee['reason'] }}</td>
                    </tr>
                @empty
                    <tr class="na-row">
                        <td colspan="2">N/A</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
