<x-filament-panels::page>
    <div class="max-w-4xl mx-auto space-y-8">
        <form wire:submit="submit" id="onboarding-form">
            {{ $this->form }}
        </form>

        {{ $this->table }}

        <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
            <x-filament::button wire:click="submit" size="lg">
                Завершить настройку
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>