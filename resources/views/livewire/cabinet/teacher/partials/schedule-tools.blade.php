{{-- Расписание учителя: фильтр по ученику или группе и вид (список, неделя, месяц). --}}
<div class="flex flex-col gap-2 lg:flex-row lg:items-center">
    @if (count($whoOptions) > 2)
        <div class="lg:w-sidebar">
            <x-ui.select name="who" :options="$whoOptions" wire:model.live="who" aria-label="Чьи занятия показать" />
        </div>
    @endif
    <x-ui.seg fit :items="['list' => 'Список', 'week' => 'Неделя', 'month' => 'Месяц']" model="view" :active="$view" aria-label="Вид" />
</div>
