@php
    $record = $getRecord();
    $isRunning = $record->is_running;
    $nextStart = $record->next_start;
    $duration = ((int) $record->duration > 0 ? (int) $record->duration : 45) * 60 * 1000;
@endphp

@if($isRunning)
    {{-- Running lesson - use Blade for Livewire/Pusher updates --}}
    <div class="flex flex-col">
        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">Статус</span>
        <div class="w-max mt-1">
            <x-filament::badge color="success" icon="heroicon-m-video-camera"
                style="background-color: rgb(220 252 231); color: rgb(22 101 52);">
                Идет урок
            </x-filament::badge>
        </div>
    </div>
@elseif(!$nextStart)
    {{-- No scheduled date --}}
    <div class="text-sm text-gray-400">
        Нет расписания
    </div>
@else
    {{-- Timer and countdown - use Alpine.js for dynamic updates --}}
    <div x-data="{
                                        target: '{{ $nextStart->toIso8601String() }}',
                                        duration: {{ $duration }},
                                        now: new Date(),
                                        get diff() { return new Date(this.target) - this.now; },
                                        get days() { return Math.floor(this.diff / (1000 * 60 * 60 * 24)); },
                                        get hours() { return Math.floor((this.diff / (1000 * 60 * 60)) % 24); },
                                        get totalHours() { return Math.floor(this.diff / (1000 * 60 * 60)); },
                                        get minutes() { return Math.floor((this.diff / 1000 / 60) % 60); },
                                        get seconds() { return Math.floor((this.diff / 1000) % 60); },
                                        get isTime() { return this.diff <= 0 && this.diff > -(this.duration); },
                                        get isNear() { return this.diff > 0 && this.diff < 30 * 60 * 1000; },
                                        get isToday() { return this.diff > 0 && this.totalHours < 24 && this.totalHours >= 0; },
                                        plural(number, titles) {
                                            const cases = [2, 0, 1, 1, 1, 2];
                                            return titles[(number % 100 > 4 && number % 100 < 20) ? 2 : cases[(number % 10 < 5) ? number % 10 : 5]];
                                        },
                                        updateStartButton() {
                                            const header = document.querySelector('.fi-header-actions');
                                            if (!header) return;

                                            const buttons = Array.from(header.querySelectorAll('a, button'));
                                            const startBtn = buttons.find(el => el.textContent.includes('Начать'));

                                            if (startBtn) {
                                                if (this.isTime) {
                                                    startBtn.style.backgroundColor = '#16a34a';
                                                    startBtn.style.borderColor = '#16a34a';
                                                    startBtn.style.color = '#ffffff';
                                                    startBtn.style.boxShadow = 'none';
                                                    startBtn.style.outline = 'none';
                                                    const icon = startBtn.querySelector('svg');
                                                    if (icon) icon.style.color = '#ffffff';
                                                } else {
                                                    startBtn.style.backgroundColor = '';
                                                    startBtn.style.borderColor = '';
                                                    startBtn.style.color = '';
                                                    startBtn.style.boxShadow = '';
                                                    startBtn.style.outline = '';
                                                    const icon = startBtn.querySelector('svg');
                                                    if (icon) icon.style.color = '';
                                                }
                                            }
                                        }
                                    }" x-init="
                                        updateStartButton();
                                        setInterval(() => {
                                            now = new Date();
                                            updateStartButton();
                                        }, 1000)
                                    " class="flex flex-col justify-center">
        <!-- Future: Countdowns -->
        <template x-if="diff > 0">
            <div class="flex flex-col">
                <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">Старт через</span>

                <div class="mt-1 text-left">
                    <!-- Days > 24h -->
                    <template x-if="!isToday && !isNear">
                        <span class="font-medium text-gray-700 dark:text-gray-200 text-sm">
                            <span x-text="days"></span>
                            <span x-text="plural(days, ['день', 'дня', 'дней'])"></span>
                        </span>
                    </template>

                    <!-- Hours < 24h -->
                    <template x-if="isToday && !isNear">
                        <span class="font-medium text-gray-700 dark:text-gray-200 text-sm">
                            <span x-text="totalHours"></span>
                            <span x-text="plural(totalHours, ['час', 'часа', 'часов'])"></span>
                        </span>
                    </template>

                    <!-- Near < 30m -->
                    <template x-if="isNear">
                        <span class="font-medium text-sm tabular-nums" style="color: rgb(251 146 60) !important;">
                            <span x-text="String(totalHours).padStart(2, '0')"></span>:<span
                                x-text="String(minutes).padStart(2, '0')"></span>:<span
                                x-text="String(seconds).padStart(2, '0')"></span>
                        </span>
                    </template>
                </div>
            </div>
        </template>

        <!-- Time to start -->
        <template x-if="isTime">
            <div class="flex flex-col">
                <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">Статус</span>
                <div class="w-max mt-1">
                    <span
                        class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1"
                        style="background-color: rgb(220 252 231); color: rgb(22 101 52); --tw-ring-color: rgb(134 239 172 / 0.2);">
                        Скоро начнется
                    </span>
                </div>
            </div>
        </template>

        <!-- Expired -->
        <template x-if="!isTime && diff <= 0">
            <div class="flex flex-col">
                <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">Статус</span>
                <div class="w-max mt-1">
                    <span
                        class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1"
                        style="background-color: rgb(243 244 246); color: rgb(75 85 99); --tw-ring-color: rgb(156 163 175 / 0.2);">
                        Занятие завершено
                    </span>
                </div>
            </div>
        </template>
    </div>
@endif