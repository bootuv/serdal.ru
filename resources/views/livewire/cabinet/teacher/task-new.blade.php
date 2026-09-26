@php
    $isEdit = (bool) $homework;
    $isDraft = ! $isEdit || ! $homework->is_visible;
@endphp
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head :title="$isEdit ? 'Изменить задание' : 'Новое задание'" :back="$backUrl" back-label="Задания" />

    <form wire:submit="publish" class="flex w-full max-w-form flex-col gap-6">
        <x-ui.field label="Название" name="title" wire:model="title" placeholder="Например, эссе «Мои летние каникулы»" maxlength="255" />

        <x-ui.editor label="Что нужно сделать" name="description" wire:model="description"
                     placeholder="Опишите задание: что сделать, объём, на что обратить внимание" />

        {{-- Кому --}}
        <fieldset class="flex flex-col gap-3">
            <legend class="pb-2 text-t2 font-medium">Кому</legend>
            @if ($searchable)
                <x-ui.search placeholder="Найти ученика" wire:model.live.debounce.300ms="search" x-on:keydown.enter.prevent />
            @endif
            @if ($students->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach ($students as $s)
                        <x-ui.pick :user="$s" :on="in_array($s->id, $studentIds, true)" wire:click="toggleStudent({{ $s->id }})" wire:key="st-{{ $s->id }}" />
                    @endforeach
                </div>
            @endif
            <span class="text-t2 text-muted">{{ $whoLabel }}</span>
            @error('studentIds')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            @error('studentIds.*')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
        </fieldset>

        <div class="flex flex-col gap-2">
            <x-ui.select label="К какому занятию · необязательно" name="roomId" wire:model.live="roomId" :options="$rooms" placeholder="Не привязывать к занятию" />
            <span class="text-t2 text-muted">Ученики подставятся из занятия</span>
        </div>

        {{-- Срок сдачи --}}
        <div class="flex flex-col gap-2" role="group" aria-labelledby="due-label">
            <span id="due-label" class="text-t2 font-medium">Срок сдачи · необязательно</span>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <input type="date" name="date" wire:model="date" aria-label="Дата" min="{{ now()->format('Y-m-d') }}"
                       @class(['field sm:col-span-2', 'shadow-outline-ink' => $errors->has('date')])>
                <input type="time" name="time" wire:model="time" aria-label="Время"
                       @class(['field', 'shadow-outline-ink' => $errors->has('time')])>
            </div>
            @error('date')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            @error('time')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            @if (! $errors->has('date') && ! $errors->has('time'))<span class="text-t2 text-muted">Без времени — до 20:00</span>@endif
        </div>

        <div class="w-full sm:w-sidebar">
            <x-ui.field label="Максимальный балл" name="maxScore" type="number" min="1" max="1000" inputmode="numeric" wire:model="maxScore"
                        hint="Работу оцените баллами — от 1 до этого числа" />
        </div>

        {{-- Файлы --}}
        <div class="flex flex-col gap-2">
            <span class="text-t2 font-medium">Файлы · необязательно</span>
            <x-ui.dropzone wire:model="picked" title="Перетащите файлы сюда или выберите на компьютере" aria-label="Файлы задания"
                           hint="PDF, Word, Excel, PowerPoint и фото · до 100 МБ каждый"
                           :accept="implode(',', \App\Services\HomeworkSubmissionService::TASK_MIMES)" />
            <span wire:loading wire:target="picked" class="text-t2 text-muted">Загружаем…</span>
            @error('picked.*')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            @error('files.*')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            @foreach ($keptFiles as $i => $file)
                <div class="flex items-center gap-3 rounded-lg p-2 shadow-outline" wire:key="kept-{{ $file['path'] }}">
                    <x-ui.file-tile :name="$file['path']" />
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="truncate text-t1 font-medium">{{ $file['name'] }}</a>
                        <span class="truncate text-t2 text-muted">{{ $file['meta'] }}</span>
                    </div>
                    <x-ui.btn size="s" square icon="x" wire:click="removeKept({{ $i }})" aria-label="Убрать файл" />
                </div>
            @endforeach
            @foreach ($newFiles as $i => $file)
                <div class="flex items-center gap-3 rounded-lg p-2 shadow-outline" wire:key="new-{{ $i }}-{{ $file['name'] }}">
                    <x-ui.file-tile :name="$file['name']" />
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="truncate text-t1 font-medium">{{ $file['name'] }}</span>
                        <span class="truncate text-t2 text-muted">{{ $file['meta'] }}</span>
                    </div>
                    <x-ui.btn size="s" square icon="x" wire:click="removeFile({{ $i }})" aria-label="Убрать файл" />
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2 pt-2">
            <x-ui.btn type="submit" variant="primary" wire:loading.attr="disabled" wire:target="publish,saveDraft,picked">
                {{ $isDraft ? 'Выдать задание' : 'Сохранить' }}
            </x-ui.btn>
            @if ($isDraft)
                <x-ui.btn wire:click="saveDraft" wire:loading.attr="disabled" wire:target="publish,saveDraft,picked">Сохранить черновик</x-ui.btn>
            @endif
            <x-ui.btn :href="$backUrl">Отмена</x-ui.btn>
            @if ($isEdit)
                <button type="button" wire:click="$set('confirmDelete', true)" class="link ml-auto text-t2">Удалить задание</button>
            @endif
        </div>
    </form>

    @if ($isEdit && $confirmDelete)
        <x-ui.modal title="Удалить задание?" :sub="$homework->title" close="$set('confirmDelete', false)" width="s">
            <p class="text-t1">Задание пропадёт у учеников вместе с их сданными работами, оценками и файлами. Вернуть их не получится.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="$set('confirmDelete', false)">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">Удалить задание и работы</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
