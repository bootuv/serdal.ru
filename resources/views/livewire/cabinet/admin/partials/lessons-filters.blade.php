{{-- Панель фильтров проведённых занятий и записей: учитель, период, поиск. --}}
<div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
        <div class="lg:w-sidebar">
            <x-ui.select name="teacher" :options="$teacherOptions" wire:model.live="teacher" aria-label="Учитель" />
        </div>
        <x-ui.seg fit :items="$periodOptions" model="period" :active="$period" aria-label="Период" />
    </div>
    <x-ui.search wire:model.live.debounce.400ms="q" placeholder="Занятие, учитель или ученик" />
</div>
