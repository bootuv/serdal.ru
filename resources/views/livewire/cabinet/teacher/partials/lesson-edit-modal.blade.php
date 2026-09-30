{{-- Окно «Ученики и название»: тип, ученики и название занятия (вместо формы занятия старого кабинета).
     Состояние и сохранение — Lesson::openEdit/saveEdit, логика — TeacherLessonService::updateLesson. --}}
@if ($editOpen)
    <x-ui.modal title="Ученики и название" :sub="$room->name" close="closeEdit">
        <div class="flex flex-col gap-2">
            <span class="text-t2 font-medium">Тип занятия</span>
            <x-ui.seg :items="['individual' => 'Индивидуальное', 'group' => 'Групповое']" model="editKind" :active="$editKind" aria-label="Тип занятия" />
        </div>

        <fieldset class="flex flex-col gap-3">
            <legend class="pb-2 text-t2 font-medium">{{ $editKind === 'group' ? 'Ученики' : 'Ученик' }}</legend>
            @if ($editPeople)
                <x-ui.person-select name="editStudents" :people="$editPeople" :checked="$editStudents" action="pickEditStudent" :keep="$editKind === 'group'"
                                    :placeholder="$editKind === 'group' ? 'Выбрать учеников' : 'Выбрать ученика'" />
            @endif
            @if ($editChosen->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach ($editChosen as $s)
                        <x-ui.token :remove="'pickEditStudent(' . $s->id . ')'" :label="$s->name" title="{{ $s->email }}" wire:key="edit-st-{{ $s->id }}">{{ $s->name }}</x-ui.token>
                    @endforeach
                </div>
            @endif
            @error('editStudents.*')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            @unless ($errors->has('editStudents') || $errors->has('editStudents.*'))<span class="text-t3 text-muted">{{ $editHint }}</span>@endunless
        </fieldset>

        <x-ui.field label="Предмет и название" name="editName" wire:model="editName" placeholder="Например: Английский язык" maxlength="255" />

        <x-slot:note>{{ $editNote }}</x-slot:note>
        <x-slot:footer>
            <x-ui.btn wire:click="closeEdit">Отмена</x-ui.btn>
            <x-ui.btn variant="primary" wire:click="saveEdit" wire:loading.attr="disabled" wire:target="saveEdit">Сохранить</x-ui.btn>
        </x-slot:footer>
    </x-ui.modal>
@endif
