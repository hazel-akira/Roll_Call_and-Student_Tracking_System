{{-- Shared report header with school logo --}}
@php
    $reportTitle = $reportTitle ?? 'Report';
    $schoolName = $schoolName ?? 'School';
    $schoolLogo = $schoolLogo ?? null;
    $subtitle = $subtitle ?? null;
@endphp
<table class="report-header">
    <tr>
        <td class="report-header-logo">
            @if ($schoolLogo)
                <img src="{{ $schoolLogo }}" alt="School logo" class="school-logo">
            @endif
        </td>
        <td class="report-header-text">
            <div class="report-school-name">{{ $schoolName }}</div>
            <div class="report-doc-title">{{ $reportTitle }}</div>
            @if ($subtitle)
                <div class="report-subtitle">{{ $subtitle }}</div>
            @endif
        </td>
        <td class="report-header-logo report-header-logo-right">
            {{-- Balances the left logo column for centered text --}}
        </td>
    </tr>
</table>
<div class="report-header-rule"></div>
