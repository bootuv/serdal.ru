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
                <label wire:key="{{ $roomsModel }}-{{ $room['id'] }}" class="flex min-h-11 cursor-pointer items-center gap-3 rounded px-4 py-2 shadow-outline hover:shadow-outline-ink">
                    <input type="checkbox" value="{{ $room['id'] }}" wire:model="{{ $roomsModel }}" class="size-5 shrink-0 accent-ink">
                    <span class="min-w-0 truncate text-t1-s">{{ $room['label'] }}</span>
                </label>
            @endforeach
        </div>
        @error($roomsModel)<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
    @endif
@else
    <p class="text-t2 text-muted">{{ $visibility === \App\Models\TeacherMaterial::VISIBILITY_ALL ? 'Увидят все ваши ученики.' : 'Видите только вы — ученикам не видно.' }}</p>
@endif
