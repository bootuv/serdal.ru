<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}

        <div style="margin-top: 2rem;">
            <x-filament::button type="submit">
                Сохранить
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>