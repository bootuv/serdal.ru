{{-- Окно «Создать занятие» за учителя (макет AdminLessons): учитель, название, ученики этого учителя, расписание как в кабинете учителя. --}}
@if ($createOpen)
    <x-ui.modal title="Создать занятие" sub="Занятие появится у учителя так, будто он создал его сам" close="closeCreate">
        <div class="flex flex-col gap-2">
            <x-ui.select label="Учитель" name="cTeacher" :options="$cTeacherOptions" placeholder="Выберите учителя" wire:model.live="cTeacher" />
            @unless ($errors->has('cTeacher'))<span class="text-t3 text-muted">В списке только учителя</span>@endunless
        </div>

        <x-ui.field label="Предмет и название" name="cName" wire:model="cName" placeholder="Например: Английский язык" maxlength="255" />

        <div class="flex flex-col gap-2">
            <span class="text-t2 font-medium">Ученики</span>
            @if ($cChosen)
                <div class="flex flex-wrap gap-2">
                    @foreach ($cChosen as $person)
                        <span class="inline-flex h-9 items-center gap-1 rounded-full bg-soft pl-3 pr-1 text-t2 font-medium" wire:key="c-st-{{ $person['id'] }}">
                            {{ $person['name'] }}
                            <button type="button" wire:click="removeCStudent({{ $person['id'] }})" class="flex size-8 items-center justify-center rounded-full text-muted hover:text-ink" aria-label="Убрать: {{ $person['name'] }}"><x-ui.icon name="x" size="s" /></button>
                        </span>
                    @endforeach
                </div>
            @endif
            @if ($cAddOptions)
                <x-ui.select name="cAdd" :options="$cAddOptions" placeholder="Добавить ученика" wire:model.live="cAdd" aria-label="Добавить ученика" />
            @endif
            @if ($errors->has('cStudents') || $errors->has('cStudents.*'))
                <span class="text-t2 font-medium text-danger-fg">{{ $errors->first('cStudents') ?: $errors->first('cStudents.*') }}</span>
            @else
                <span class="text-t3 text-muted">{{ $cWhoHint }}</span>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <x-ui.field :label="$cRepeat === 'weekly' ? 'Первое занятие' : 'Дата'" name="cDate" type="date" wire:model.live="cDate" />
            <x-ui.field label="Время" name="cTime" type="time" wire:model.live="cTime" />
            <x-ui.select label="Длительность" name="cDuration" :options="$cDurations" wire:model="cDuration" />
        </div>

        <div class="flex flex-col gap-2">
            <span class="text-t2 font-medium">Повтор</span>
            <x-ui.seg :items="['once' => 'Не повторять', 'weekly' => 'Каждую неделю']" model="cRepeat" :active="$cRepeat" aria-label="Повтор" />
        </div>

        @if ($cRepeat === 'weekly')
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
                <div class="flex flex-col gap-2">
                    <span class="text-t2 font-medium">По каким дням</span>
                    @include('livewire.cabinet.teacher.partials.lesson-day-chips', ['selected' => $cDays, 'action' => 'toggleCDay', 'label' => 'По каким дням'])
                    @error('cDays')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                </div>
                <div class="min-w-0 flex-1">
                    <x-ui.field label="До какого дня" optional name="cUntil" type="date" wire:model="cUntil" />
                </div>
            </div>
        @endif

        <x-slot:note>{{ $cFoot }}</x-slot:note>
        <x-slot:footer>
            <x-ui.btn wire:click="closeCreate">Отмена</x-ui.btn>
            <x-ui.btn variant="primary" wire:click="saveCreate" wire:loading.attr="disabled" wire:target="saveCreate">Создать занятие</x-ui.btn>
        </x-slot:footer>
    </x-ui.modal>
@endif
