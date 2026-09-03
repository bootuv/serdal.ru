{{--
    Выбор тарифа карточками (шаг онбординга).
    Состояние — скрытый radio + peer-стили; тарифы приходят через viewData('tariffs').
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="grid gap-2 sm:grid-cols-2">
        @foreach ($tariffs as $value => $tariff)
            <label class="relative block cursor-pointer">
                <input
                    type="radio"
                    name="{{ $getId() }}"
                    value="{{ $value }}"
                    wire:model.live="{{ $getStatePath() }}"
                    class="peer sr-only"
                />
                <span
                    class="flex h-full flex-col gap-1 rounded-xl border border-gray-200 bg-white p-4 pe-10 transition duration-75 hover:bg-gray-50 peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:ring-1 peer-checked:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10 dark:peer-checked:border-primary-400 dark:peer-checked:bg-primary-500/10 dark:peer-checked:ring-primary-400"
                >
                    <span class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $tariff['title'] }}</span>
                        @if (!empty($tariff['popular']))
                            <span class="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-600 ring-1 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-400">Популярный</span>
                        @endif
                    </span>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $tariff['price'] }}</span>
                    @if (!empty($tariff['subtitle']))
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $tariff['subtitle'] }}</span>
                    @endif
                </span>
                <x-heroicon-m-check-circle
                    class="absolute end-3 top-4 h-5 w-5 text-primary-600 opacity-0 transition duration-75 peer-checked:opacity-100 dark:text-primary-400"
                />
            </label>
        @endforeach
    </div>
</x-dynamic-component>
