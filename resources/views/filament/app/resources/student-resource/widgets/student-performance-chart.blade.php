@php
    use Filament\Support\Facades\FilamentView;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $filters = $this->getFilters();
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section :description="$description" :heading="$heading">
        @if ($filters)
            <x-slot name="headerEnd">
                <x-filament::input.wrapper inline-prefix wire:target="filter" class="w-max sm:-my-2">
                    <x-filament::input.select inline-prefix wire:model.live="filter">
                        @foreach ($filters as $value => $label)
                            <option value="{{ $value }}">
                                {{ $label }}
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </x-slot>
        @endif

        <div @if ($pollingInterval = $this->getPollingInterval()) wire:poll.{{ $pollingInterval }}="updateChartData"
        @endif class="relative flex justify-center items-center">
            <div @if (FilamentView::hasSpaMode()) x-load="visible" @else x-load @endif
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                wire:ignore x-data="chart({
                            cachedData: @js($this->getCachedData()),
                            options: @js($this->getOptions()),
                            type: @js($this->getType()),
                        })" @class([
                            match ($color) {
                                'gray' => null,
                                default => 'fi-color-custom',
                            },
                            is_string($color) ? "fi-color-{$color}" : null,
                            'w-full max-w-[300px]', // Constrain width to ensure it stays a managed square
                        ])>
                <canvas x-ref="canvas" @if ($maxHeight = $this->getMaxHeight()) style="max-height: {{ $maxHeight }}"
                @endif></canvas>

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

            {{-- Avatar Overlay --}}
            @if($this->record)
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    {{-- Adjust size based on chart size if possible, or fixed size. The design shows small avatar in
                    center. --}}
                    <img src="{{ $this->record->avatar_url }}"
                        class="w-16 h-16 rounded-full border-2 border-white dark:border-gray-800 shadow-md object-cover bg-white">
                </div>
            @endif
        </div>

        {{-- Custom Legend --}}
        <div class="flex flex-wrap justify-center gap-4 mt-4 text-sm text-gray-500 dark:text-gray-400">
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full" style="background-color: rgb(59, 130, 246);"></span>
                <span>Посещаемость</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full" style="background-color: rgb(16, 185, 129);"></span>
                <span>Дисциплина (ДЗ)</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full" style="background-color: rgb(245, 158, 11);"></span>
                <span>Качество знаний</span>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>