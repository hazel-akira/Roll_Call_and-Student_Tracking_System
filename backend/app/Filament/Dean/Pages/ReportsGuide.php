<?php

namespace App\Filament\Dean\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use UnitEnum;

class ReportsGuide extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $title = 'Reports';

    protected static string|UnitEnum|null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.dean.pages.reports-guide';

    public function getFrontendDutyRosterUrl(): string
    {
        $frontend = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');

        return "{$frontend}/duty-roster";
    }

    public function getFrontendReportsUrl(): string
    {
        $frontend = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');

        return "{$frontend}/reports/attendance";
    }

    public function getFrontendDutyRosterReportsUrl(): string
    {
        $frontend = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');

        return "{$frontend}/reports/duty-roster";
    }
}
