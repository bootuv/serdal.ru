<x-filament-panels::page>
    <div class="max-w-4xl mx-auto">
        <form wire:submit="submit">
            {{ $this->form }}

            <div class="mt-6 flex justify-end">
                <x-filament::button type="submit" size="lg">
                    Завершить настройку
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>