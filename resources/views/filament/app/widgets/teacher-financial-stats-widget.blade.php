<x-filament-widgets::widget>
    <x-filament::section heading="Финансовая статистика"
        description='Расчет ведется с учетом типа оплаты урока. Для "Помесячной оплаты" учитываются все проведенные уроки, для "Поурочной" – только посещенные учениками. Комиссия платформы составляет 10%.'>
        {{ $this->form }}

        <div class="mt-6 grid gap-4 grid-cols-1 md:grid-cols-3">
            <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Стоимость занятий
                </div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                    {{ number_format($totalEarnings, 0, '.', ' ') }} ₽
                </div>
            </div>

            <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Комиссия Serdal (10%)
                </div>
                <div class="mt-2 text-3xl font-bold text-danger-600 dark:text-danger-500">
                    -{{ number_format($commission, 0, '.', ' ') }} ₽
                </div>
            </div>

            <div class="p-4 rounded-xl border"
                style="background-color: rgba(var(--primary-50), 1); border-color: rgba(var(--primary-600), 1); color: rgba(var(--primary-600), 1);">
                <div class="text-sm font-medium" style="color: rgba(var(--primary-600), 1);">
                    Ваш доход
                </div>
                <div class="mt-2 text-3xl font-bold" style="color: rgba(var(--primary-600), 1);">
                    {{ number_format($payable, 0, '.', ' ') }} ₽
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>