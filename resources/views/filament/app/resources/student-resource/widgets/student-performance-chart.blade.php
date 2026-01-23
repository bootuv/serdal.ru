@php
    use Filament\Support\Facades\FilamentView;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $filters = $this->getFilters();
    $avatarUrl = $this->record?->avatar_url;
    
    // Get stats for legend
    $chartData = $this->getCachedData();
    $offset = 40;
    $minVisible = 70;
    $rawData = $chartData['datasets'][0]['data'] ?? [0, 0, 0];
    
    // Convert back to actual percentages
    $attendance = $rawData[0] == $minVisible ? 0 : max(0, $rawData[0] - $offset);
    $discipline = $rawData[1] == $minVisible ? 0 : max(0, $rawData[1] - $offset);
    $knowledge = $rawData[2] == $minVisible ? 0 : max(0, $rawData[2] - $offset);
    
    // Overall score (average of three metrics)
    // Overall score (average of non-zero metrics)
    $metrics = array_filter([$attendance, $discipline, $knowledge], fn($val) => $val > 0);
    $count = count($metrics);
    $overallScore = $count > 0 ? round(array_sum($metrics) / $count) : null;
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section :description="$description" :heading="$heading">
        <x-slot name="headerEnd">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold
                @if($overallScore === null) bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100
                @elseif($overallScore >= 80) bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100
                @elseif($overallScore >= 60) bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100
                @elseif($overallScore >= 40) bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-100
                @else bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100
                @endif
            ">
                {{ $overallScore ?? '—' }}{{ $overallScore !== null ? '%' : '' }}
            </span>
        </x-slot>

        <div @if ($pollingInterval = $this->getPollingInterval()) wire:poll.{{ $pollingInterval }}="updateChartData" @endif
            class="flex justify-center items-center">
            {{-- Chart wrapper with relative positioning for avatar overlay --}}
            <div class="relative w-full max-w-[300px]">
                <div
                    @if (FilamentView::hasSpaMode()) x-load="visible" @else x-load @endif
                    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                    wire:ignore
                    x-data="chart({
                        cachedData: @js($this->getCachedData()),
                        options: @js($this->getOptions()),
                        type: @js($this->getType()),
                    })"
                    @class([
                        match ($color) {
                            'gray' => null,
                            default => 'fi-color-custom',
                        },
                        is_string($color) ? "fi-color-{$color}" : null,
                        'w-full',
                    ])
                >
                    <canvas
                        x-ref="canvas"
                        @if ($maxHeight = $this->getMaxHeight())
                            style="max-height: {{ $maxHeight }}"
                        @endif
                    ></canvas>

                    <span x-ref="backgroundColorElement" @class([
                        match ($color) {
                            'gray' => 'text-gray-100 dark:text-gray-800',
                            default => 'text-custom-50 dark:text-custom-400/10',
                        },
                    ]) @style([
                        \Filament\Support\get_color_css_variables(
                            $color,
                            shades: [50, 400],
                            alias: 'widgets::chart-widget.background',
                        ) => $color !== 'gray',
                    ])></span>

                    <span x-ref="borderColorElement" @class([
                        match ($color) {
                            'gray' => 'text-gray-400',
                            default => 'text-custom-500 dark:text-custom-400',
                        },
                    ]) @style([
                        \Filament\Support\get_color_css_variables(
                            $color,
                            shades: [400, 500],
                            alias: 'widgets::chart-widget.border',
                        ) => $color !== 'gray',
                    ])></span>

                    <span x-ref="gridColorElement" class="text-gray-200 dark:text-gray-800"></span>

                    <span x-ref="textColorElement" class="text-gray-500 dark:text-gray-400"></span>
                </div>

                {{-- White Circle Overlay with Avatar inside --}}
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div 
                        class="bg-white dark:bg-gray-900 rounded-full shadow-sm flex items-center justify-center"
                        style="width: 26%; aspect-ratio: 1/1;"
                    >
                        @if($avatarUrl)
                            <img
                                src="{{ $avatarUrl }}"
                                class="w-[85%] h-[85%] rounded-full object-cover bg-gray-100"
                            >
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Custom Legend with percentages --}}
        <div class="mt-4 text-sm text-gray-600 dark:text-gray-400 divide-y divide-gray-100 dark:divide-gray-700">
            <div class="flex items-center justify-between py-2">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full" style="background-color: rgb(59, 130, 246);"></span>
                    <span>Посещаемость</span>
                </div>
                <strong class="text-gray-900 dark:text-gray-100">{{ $attendance > 0 ? $attendance . '%' : '—' }}</strong>
            </div>
            <div class="flex items-center justify-between py-2">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full" style="background-color: rgb(16, 185, 129);"></span>
                    <span>Дисциплина (ДЗ)</span>
                </div>
                <strong class="text-gray-900 dark:text-gray-100">{{ $discipline > 0 ? $discipline . '%' : '—' }}</strong>
            </div>
            <div class="flex items-center justify-between py-2">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full" style="background-color: rgb(245, 158, 11);"></span>
                    <span>Качество знаний</span>
                </div>
                <strong class="text-gray-900 dark:text-gray-100">{{ $knowledge > 0 ? $knowledge . '%' : '—' }}</strong>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>