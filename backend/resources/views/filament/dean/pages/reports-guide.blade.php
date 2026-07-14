<x-filament-panels::page>
    <div class="space-y-4 text-sm leading-relaxed text-gray-700 dark:text-gray-200">
        <p>
            Open the main Roll Call dashboard to view attendance summaries, class trends, student trends, and export reports.
            Your access is limited to schools assigned to your dean account.
        </p>

        <x-filament::button
            tag="a"
            href="{{ $this->getFrontendReportsUrl() }}"
            target="_blank"
            icon="heroicon-o-arrow-top-right-on-square"
        >
            Open reports dashboard
        </x-filament::button>

        <p class="text-xs text-gray-500 dark:text-gray-400">
            Sign in with the same Microsoft account if prompted. Use the school selector in the header to switch between assigned schools.
        </p>
    </div>
</x-filament-panels::page>
