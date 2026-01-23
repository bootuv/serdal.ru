@php
    use Filament\Support\Facades\FilamentView;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $teachers = $this->getTeachers();
    $selectedTeacherId = $this->selectedTeacherId ?? $teachers->first()?->id;
    $avatarUrl = auth()->user()?->avatar_url;
    
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
    <x-filament::section :heading="$heading">
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

        {{-- Teacher Selector --}}
        @if($teachers->count() > 1)
            @php
                $selectedTeacher = $teachers->firstWhere('id', $selectedTeacherId) ?? $teachers->first();
                
                // Format name as "И.О. Фамилия"
                $formatShortName = function($name) {
                    $parts = explode(' ', trim($name));
                    if (count($parts) >= 3) {
                        // Фамилия Имя Отчество -> И.О. Фамилия
                        return mb_substr($parts[1], 0, 1) . '.' . mb_substr($parts[2], 0, 1) . '. ' . $parts[0];
                    } elseif (count($parts) === 2) {
                        // Фамилия Имя -> И. Фамилия
                        return mb_substr($parts[1], 0, 1) . '. ' . $parts[0];
                    }
                    return $name;
                };
            @endphp
            <div class="mb-4">
                <div class="relative" x-data="{ open: false }" @click.away="open = false">
                    {{-- Selected teacher display --}}
                    <button 
                        type="button"
                        @click="open = !open"
                        class="w-full flex items-center gap-3 py-2 px-3 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition-colors ring-1 ring-gray-200 dark:ring-gray-700"
                    >
                        <img src="{{ $selectedTeacher->avatar_url }}" class="w-10 h-10 rounded-full object-cover flex-shrink-0" alt="">
                        <div class="flex flex-col items-start min-w-0 flex-1">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Учитель</span>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate w-full text-left">{{ $formatShortName($selectedTeacher->name) }}</span>
                        </div>
                        <svg class="w-4 h-4 text-gray-400 transition-transform flex-shrink-0 ml-auto" :class="{ 'rotate-180': open }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    
                    {{-- Dropdown --}}
                    <div 
                        x-show="open" 
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute left-0 right-0 z-10 mt-1 bg-white dark:bg-gray-900 rounded-lg shadow-lg ring-1 ring-gray-200 dark:ring-gray-700 py-1 max-h-60 overflow-y-auto"
                        style="display: none;"
                    >
                        @foreach($teachers as $teacher)
                            <button
                                type="button"
                                wire:click="$set('selectedTeacherId', {{ $teacher->id }})"
                                @click="open = false"
                                class="w-full flex items-center gap-3 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors {{ $teacher->id === $selectedTeacherId ? 'bg-primary-50 dark:bg-primary-900/20' : '' }}"
                            >
                                <img src="{{ $teacher->avatar_url }}" class="w-7 h-7 rounded-full object-cover flex-shrink-0" alt="">
                                <span class="text-sm text-gray-700 dark:text-gray-200 truncate">{{ $formatShortName($teacher->name) }}</span>
                                @if($teacher->id === $selectedTeacherId)
                                    <svg class="w-4 h-4 text-primary-500 ml-auto flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                    </svg>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @elseif($teachers->count() === 1)
            <div class="mb-4 flex items-center gap-3 py-2 px-1">
                <img src="{{ $teachers->first()->avatar_url }}" class="w-8 h-8 rounded-full object-cover" alt="">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $teachers->first()->name }}</span>
            </div>
        @endif

        @if($teachers->isEmpty())
            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <x-heroicon-o-academic-cap class="w-12 h-12 mx-auto mb-2 opacity-50" />
                <p>У вас пока нет учителей</p>
            </div>
        @else
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
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
