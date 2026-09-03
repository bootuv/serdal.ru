{{--
    Выбор способа оплаты карточками вместо радио-кружков.
    Состояние — скрытый radio + peer-стили; методы приходят через viewData('methods').
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="grid gap-2">
        @foreach ($methods as $value => $method)
            <label class="relative block cursor-pointer">
                <input
                    type="radio"
                    name="{{ $getId() }}"
                    value="{{ $value }}"
                    wire:model.live="{{ $getStatePath() }}"
                    class="peer sr-only"
                />
                <span
                    class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 pe-10 transition duration-75 hover:bg-gray-50 peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:ring-1 peer-checked:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10 dark:peer-checked:border-primary-400 dark:peer-checked:bg-primary-500/10 dark:peer-checked:ring-primary-400"
                >
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-gray-950/10">
                        <img src="{{ asset('images/payment/' . $method['icon']) }}" alt="" class="h-5 w-5 object-contain" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-gray-950 dark:text-white">{{ $method['title'] }}</span>
                        @if (!empty($method['subtitle']))
                            <span class="block text-sm text-gray-500 dark:text-gray-400">{{ $method['subtitle'] }}</span>
                        @endif
                    </span>
                </span>
                <x-heroicon-m-check-circle
                    class="absolute end-3 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-600 opacity-0 transition duration-75 peer-checked:opacity-100 dark:text-primary-400"
                />
            </label>
        @endforeach
    </div>
</x-dynamic-component>
