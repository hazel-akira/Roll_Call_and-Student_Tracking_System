@php
    $records = $session->records;
    $totalMarked = $records->count();

    $statusCounts = [
        'present' => $records->where('status', 'present')->count(),
        'missing' => $records->whereIn('status', ['absent', 'missing'])->count(),
        'sick' => $records->whereIn('status', ['excused', 'sick'])->count(),
        'on_leave' => $records->whereIn('status', ['late', 'on_leave'])->count(),
    ];

    $className = $session->classRoom?->name ?? 'Class';
    $schoolName = $session->classRoom?->school?->name ?? '—';
    $subjectName = $session->subject?->name ?? 'Roll Call';
    $teacherName = $session->teacher?->name ?? '—';
    $sessionDate = $session->session_date?->format('l, F j, Y') ?? '—';
    $sessionTitle = $session->title ?: 'Roll Call Session';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roll Call Report</title>
</head>
<body style="margin: 0; padding: 0; background-color: #eef2f6; font-family: Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.5; -webkit-text-size-adjust: 100%;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #eef2f6; padding: 24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(13, 49, 88, 0.08);">
                    {{-- Header --}}
                    <tr>
                        <td style="background-color: #0d3158; padding: 28px 32px 24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 6px; font-size: 11px; font-weight: bold; letter-spacing: 0.12em; text-transform: uppercase; color: #df8811;">
                                            Pioneer Group of Schools
                                        </p>
                                        <h1 style="margin: 0; font-size: 24px; font-weight: bold; line-height: 1.25; color: #ffffff;">
                                            Roll Call Report
                                        </h1>
                                        <p style="margin: 10px 0 0; font-size: 14px; color: rgba(255, 255, 255, 0.85);">
                                            {{ $sessionTitle }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Intro --}}
                    <tr>
                        <td style="padding: 28px 32px 8px;">
                            <p style="margin: 0; font-size: 15px; color: #374151;">
                                A roll call session has been completed. The full attendance memo is attached as a PDF for your records.
                            </p>
                        </td>
                    </tr>

                    {{-- Attendance summary --}}
                    <tr>
                        <td style="padding: 20px 32px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td width="25%" style="padding: 4px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #ecfdf5; border-radius: 8px; border: 1px solid #a7f3d0;">
                                            <tr>
                                                <td align="center" style="padding: 14px 8px;">
                                                    <p style="margin: 0; font-size: 22px; font-weight: bold; color: #047857;">{{ $statusCounts['present'] }}</p>
                                                    <p style="margin: 4px 0 0; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em; color: #065f46;">Present</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td width="25%" style="padding: 4px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #fef2f2; border-radius: 8px; border: 1px solid #fecaca;">
                                            <tr>
                                                <td align="center" style="padding: 14px 8px;">
                                                    <p style="margin: 0; font-size: 22px; font-weight: bold; color: #b91c1c;">{{ $statusCounts['missing'] }}</p>
                                                    <p style="margin: 4px 0 0; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em; color: #991b1b;">Missing</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td width="25%" style="padding: 4px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #fffbeb; border-radius: 8px; border: 1px solid #fde68a;">
                                            <tr>
                                                <td align="center" style="padding: 14px 8px;">
                                                    <p style="margin: 0; font-size: 22px; font-weight: bold; color: #b45309;">{{ $statusCounts['sick'] }}</p>
                                                    <p style="margin: 4px 0 0; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em; color: #92400e;">Sick</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td width="25%" style="padding: 4px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #fff7ed; border-radius: 8px; border: 1px solid #fed7aa;">
                                            <tr>
                                                <td align="center" style="padding: 14px 8px;">
                                                    <p style="margin: 0; font-size: 22px; font-weight: bold; color: #c2410c;">{{ $statusCounts['on_leave'] }}</p>
                                                    <p style="margin: 4px 0 0; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em; color: #9a3412;">On Leave</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Session details --}}
                    <tr>
                        <td style="padding: 16px 32px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
                                <tr>
                                    <td style="padding: 18px 20px;">
                                        <p style="margin: 0 0 14px; font-size: 12px; font-weight: bold; letter-spacing: 0.08em; text-transform: uppercase; color: #0d3158;">
                                            Session Details
                                        </p>
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding: 8px 0; width: 38%; font-size: 13px; font-weight: bold; color: #64748b; vertical-align: top;">School</td>
                                                <td style="padding: 8px 0; font-size: 14px; color: #1f2937; vertical-align: top;">{{ $schoolName }}</td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" style="padding: 0; border-top: 1px solid #e2e8f0;"></td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 13px; font-weight: bold; color: #64748b; vertical-align: top;">Class / Stream</td>
                                                <td style="padding: 8px 0; font-size: 14px; color: #1f2937; vertical-align: top;">{{ $className }}</td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" style="padding: 0; border-top: 1px solid #e2e8f0;"></td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 13px; font-weight: bold; color: #64748b; vertical-align: top;">Subject</td>
                                                <td style="padding: 8px 0; font-size: 14px; color: #1f2937; vertical-align: top;">{{ $subjectName }}</td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" style="padding: 0; border-top: 1px solid #e2e8f0;"></td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 13px; font-weight: bold; color: #64748b; vertical-align: top;">Teacher on Duty</td>
                                                <td style="padding: 8px 0; font-size: 14px; color: #1f2937; vertical-align: top;">{{ $teacherName }}</td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" style="padding: 0; border-top: 1px solid #e2e8f0;"></td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 13px; font-weight: bold; color: #64748b; vertical-align: top;">Session Date</td>
                                                <td style="padding: 8px 0; font-size: 14px; color: #1f2937; vertical-align: top;">{{ $sessionDate }}</td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" style="padding: 0; border-top: 1px solid #e2e8f0;"></td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 13px; font-weight: bold; color: #64748b; vertical-align: top;">Records Marked</td>
                                                <td style="padding: 8px 0; font-size: 14px; color: #1f2937; vertical-align: top;">
                                                    <span style="display: inline-block; background-color: #0d3158; color: #ffffff; font-size: 12px; font-weight: bold; padding: 3px 10px; border-radius: 999px;">
                                                        {{ $totalMarked }} student{{ $totalMarked === 1 ? '' : 's' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Attachment callout --}}
                    <tr>
                        <td style="padding: 16px 32px 28px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #fff8ef; border-radius: 10px; border-left: 4px solid #df8811;">
                                <tr>
                                    <td style="padding: 16px 18px;">
                                        <p style="margin: 0 0 4px; font-size: 14px; font-weight: bold; color: #0d3158;">
                                            PDF attachment included
                                        </p>
                                        <p style="margin: 0; font-size: 13px; color: #64748b;">
                                            Open the attached roll call memo for the full student list, signatures, and institutional formatting.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #f8fafc; padding: 20px 32px; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0 0 6px; font-size: 12px; color: #64748b;">
                                This is an automated message from the <strong style="color: #0d3158;">PGoS Roll Call System</strong>.
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #94a3b8;">
                                Please do not reply to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
