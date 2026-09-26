{{-- Кому открыть материал: сегменты (только вам / выбранным / всем ученикам) и список занятий для «выбранным».
     $visibility — текущее значение, $model — свойство Livewire, $roomsModel — свойство со списком занятий. --}}
<div class="flex flex-col gap-2">
    <span class="text-t2 font-medium">Кому открыть</span>
    <x-ui.seg :items="$visibilityItems" :model="$model" :active="$visibility" aria-label="Кому открыть" />
</div>
@if ($visibility === \App\Models\TeacherMaterial::VISIBILITY_ROOMS)
    @if ($roomOptions->isEmpty())
        <p class="text-t2 text-muted">Пока нет занятий — сначала создайте занятие в расписании.</p>
    @else
        <div class="flex flex-col gap-2" role="group" aria-label="Ученики и группы">
            @foreach ($roomOptions as $room)
                <x-ui.option value="{{ $room['id'] }}" wire:model="{{ $roomsModel }}" wire:key="{{ $roomsModel }}-{{ $room['id'] }}" :title="$room['label']" />
            @endforeach
        </div>
        @error($roomsModel)<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
    @endif
@else
    <p class="text-t2 text-muted">{{ $visibility === \App\Models\TeacherMaterial::VISIBILITY_ALL ? 'Увидят все ваши ученики.' : 'Видите только вы — ученикам не видно.' }}</p>
@endif
