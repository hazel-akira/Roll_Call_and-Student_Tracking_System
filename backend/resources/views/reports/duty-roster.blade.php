<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Duty Roster {{ $roster['week_label'] ?? '' }}</title>
    <style>
        @include('reports.partials.report-styles')

        .section-title {
            margin: 16px 0 6px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #1e3a5f;
        }

        .status-pill {
            display: inline-block;
            padding: 2px 6px;
            border: 1px solid #94a3b8;
            background: #f1f5f9;
            color: #0f172a;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    @php
        $generatedAt = now()->format('l, F j, Y g:i A');
        $weekLabel = $roster['week_label'] ?? '';
        $status = strtoupper((string) ($roster['status'] ?? 'draft'));
    @endphp

    @include('reports.partials.school-header', [
        'schoolName' => $schoolName ?? 'School',
        'schoolLogo' => $schoolLogo ?? null,
        'reportTitle' => 'Weekly Duty Roster',
        'subtitle' => $weekLabel !== '' ? $weekLabel : null,
    ])

    <table class="meta-table">
        <tr>
            <td>
                <span class="meta-label">Week:</span> {{ $weekLabel !== '' ? $weekLabel : '—' }}
            </td>
            <td>
                <span class="meta-label">Status:</span>
                <span class="status-pill">{{ $status }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="meta-label">Published by:</span>
                {{ $roster['published_by_name'] ?? '—' }}
            </td>
            <td>
                <span class="meta-label">Generated:</span> {{ $generatedAt }}
            </td>
        </tr>
    </table>

    @forelse (($roster['sections'] ?? []) as $section)
        <div class="section-title">{{ $section['title'] }}</div>
        <table class="data-table" style="margin-top: 0;">
            <thead>
                <tr>
                    <th style="width: 28%;">Location</th>
                    <th style="width: 22%;">Time Slot</th>
                    <th style="width: 50%;">Staff</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($section['rows'] as $row)
                    <tr>
                        <td>{{ $row['location'] !== '' ? $row['location'] : 'General' }}</td>
                        <td>{{ ! empty($row['time_slot']) ? $row['time_slot'] : 'All day' }}</td>
                        <td>{{ $row['staff'] !== '' ? $row['staff'] : 'Unassigned' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" style="text-align: center; color: #64748b;">No assignments in this section.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @empty
        <p style="text-align: center; color: #64748b; margin-top: 24px;">No duty roster sections found.</p>
    @endforelse

    <div class="report-footer">
        <table class="report-footer-table">
            <tr>
                <td class="footer-left">DUTY ROSTER</td>
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
