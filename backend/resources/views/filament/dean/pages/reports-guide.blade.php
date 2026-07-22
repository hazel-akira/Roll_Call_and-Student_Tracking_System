<x-filament-panels::page>
    <div class="space-y-4 text-sm leading-relaxed text-gray-700 dark:text-gray-200">
        <p>
            Reports live in the main Roll Call app under a Reports submenu:
            Attendance Reports for weekly roll call history and exports, and Duty Roster Reports for published weekly duty history.
            Day-to-day duty assignment still happens in the Duty roster module.
        </p>

        <div class="flex flex-wrap gap-3">
            <x-filament::button
                tag="a"
                href="{{ $this->getFrontendReportsUrl() }}"
                target="_blank"
                icon="heroicon-o-arrow-top-right-on-square"
            >
                Attendance Reports
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ $this->getFrontendDutyRosterReportsUrl() }}"
                target="_blank"
                color="gray"
                icon="heroicon-o-clipboard-document-list"
            >
                Duty Roster Reports
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ $this->getFrontendDutyRosterUrl() }}"
                target="_blank"
                color="gray"
                icon="heroicon-o-pencil-square"
            >
                Manage Duty Roster
            </x-filament::button>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">
            Sign in with the same Microsoft account if prompted. Use the school selector in the header to switch between assigned schools.
        </p>
    </div>
</x-filament-panels::page>
