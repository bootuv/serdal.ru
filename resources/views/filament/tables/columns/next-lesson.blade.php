<div x-data="{
        target: '{{ $getRecord()->next_start?->toIso8601String() }}',
        now: new Date(),
        get diff() { return this.target ? new Date(this.target) - this.now : -1; },
        get days() { return Math.floor(this.diff / (1000 * 60 * 60 * 24)); },
        get hours() { return Math.floor((this.diff / (1000 * 60 * 60)) % 24); },
        get totalHours() { return Math.floor(this.diff / (1000 * 60 * 60)); },
        get minutes() { return Math.floor((this.diff / 1000 / 60) % 60); },
        get seconds() { return Math.floor((this.diff / 1000) % 60); },
        get isTime() { return this.target && this.diff <= 0; },
        get isNear() { return this.target && this.diff > 0 && this.diff < 30 * 60 * 1000; },
        get isToday() { return this.target && this.diff > 0 && this.totalHours < 24 && this.totalHours >= 0; },
        plural(number, titles) {
            const cases = [2, 0, 1, 1, 1, 2];
            return titles[(number % 100 > 4 && number % 100 < 20) ? 2 : cases[(number % 10 < 5) ? number % 10 : 5]];
        },
        updateStartButton() {
            if (!this.target) return;
            const row = this.$el.closest('tr');
            if (!row) return;

            // Robustly find Start button: check <a> and <button>, look for text 'Начать'
            const buttons = Array.from(row.querySelectorAll('a, button'));
            const startBtn = buttons.find(el => el.textContent.includes('Начать'));
            
            if (startBtn) {
                if (this.diff <= 0) {
                    // FORCE Green Style (Success)
                    startBtn.style.backgroundColor = '#16a34a'; // green-600
                    startBtn.style.borderColor = '#16a34a';
                    startBtn.style.color = '#ffffff';
                    startBtn.style.boxShadow = 'none'; // removing potential rings
                    startBtn.style.outline = 'none';
                    
                    // Force Icon Color
                    const icon = startBtn.querySelector('svg');
                    if (icon) icon.style.color = '#ffffff';
                    
                } else {
                    // Revert inline styles to allow default classes to take over
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
    " class="flex flex-col justify-center h-full">
    <!-- No scheduled date -->
    <template x-if="!target">
        <div class="text-xs text-gray-400">
            Нет расписания
        </div>
    </template>

    <template x-if="target">
        <div>
            <!-- Future: Countdowns -->
            <template x-if="diff > 0">
                <div class="flex flex-col">
                    <span class="text-gray-500 dark:text-gray-400 text-xs text-left">Старт через:</span>

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
                        <span class="font-medium text-sm tabular-nums" style="color: #ea580c;">
                            <span x-text="String(totalHours).padStart(2, '0')"></span>:<span
                                x-text="String(minutes).padStart(2, '0')"></span>:<span
                                x-text="String(seconds).padStart(2, '0')"></span>
                        </span>
                    </template>
                </div>
            </template>

            <!-- Time to start (diff <= 0) -->
            <!-- Time to start (diff <= 0) -->
            <template x-if="isTime">
                <div class="flex flex-col gap-2 justify-center h-full">
                    <div class="w-max">
                        <span
                            class="inline-flex items-center justify-center rounded-md px-2.5 py-0.5 text-xs font-medium"
                            style="background-color: #dcfce7; color: #166534; border: 1px solid #86efac;">
                            Пора начинать
                        </span>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>