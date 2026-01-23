<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Left column (wider - 2 cols) --}}
        <div class="md:col-span-2 space-y-6">
            {{-- Welcome Widget --}}
            @livewire(\App\Filament\Widgets\DashboardWelcomeOverview::class)

            {{-- Upcoming Sessions Widget --}}
            @livewire(\App\Filament\Student\Widgets\UpcomingSessionsWidget::class)

            {{-- Pending Homework Widget --}}
            @livewire(\App\Filament\Student\Widgets\PendingHomeworkWidget::class)
        </div>

        {{-- Right column (narrower - 1 col) --}}
        <div class="md:col-span-1">
            {{-- Performance Widget --}}
            @livewire(\App\Filament\Student\Widgets\MyPerformanceWidget::class)
        </div>
    </div>

    {{-- Full width widgets below --}}
    <div class="mt-6 space-y-6">
        @livewire(\App\Filament\Student\Widgets\StudentTeachersWidget::class)
        @if(\App\Filament\Student\Widgets\StudentFormerTeachersWidget::canView())
            @livewire(\App\Filament\Student\Widgets\StudentFormerTeachersWidget::class)
        @endif
    </div>
</x-filament-panels::page>