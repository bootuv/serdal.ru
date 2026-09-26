@php
    $backUrl = route('cabinet.student.tasks');
    $teacher = $homework->teacher;
@endphp
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head :title="$homework->title" :sub="$facts" :back="$backUrl" back-label="Задания" />

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">

            {{-- Условие и файлы учителя --}}
            @if ($description || $teacherFiles || $canSubmit)
                <x-ui.card aria-labelledby="task-body">
                    <x-ui.card-head id="task-body" title="Задание от учителя" />
                    @if ($description)
                        <div class="rich break-words">{{ $description }}</div>
                    @elseif (empty($teacherFiles))
                        <p class="text-t2 text-muted">Учитель не добавил описания — подробности можно спросить в сообщениях.</p>
                    @endif
                    @if ($teacherFiles)
                        <x-ui.list id="files">
                            @foreach ($teacherFiles as $file)
                                <x-ui.row :href="$file['url']" :chevron="false" target="_blank" rel="noopener" wire:key="tf-{{ $loop->index }}">
                                    <x-ui.file-tile :name="$file['path']" />
                                    <x-ui.text :title="$file['name']" :sub="$file['meta']" />
                                    <x-ui.icon name="download" class="text-muted group-hover:text-ink" />
                                </x-ui.row>
                            @endforeach
                        </x-ui.list>
                    @endif
                </x-ui.card>
            @endif

            @if ($canSubmit)
                {{-- Фокус-блок: ответ ученика --}}
                <x-ui.card focus aria-labelledby="task-answer">
                    <x-ui.card-head id="task-answer" title="Ваш ответ">
                        @if ($state === 'revision')
                            <x-slot:action><x-ui.badge tone="danger">На доработке</x-ui.badge></x-slot:action>
                        @elseif ($state === 'overdue')
                            <x-slot:action><x-ui.badge tone="danger">Срок прошёл</x-ui.badge></x-slot:action>
                        @endif
                    </x-ui.card-head>

                    @if ($state === 'revision' && ($feedback || $feedbackFiles))
                        <div class="flex flex-col gap-3">
                            <p class="text-t2 text-muted">Комментарий учителя</p>
                            @if ($feedback)
                                <div class="rich break-words rounded-lg bg-white px-4 py-3">{{ $feedback }}</div>
                            @endif
                            @include('livewire.cabinet.student.partials.task-files', ['files' => $feedbackFiles])
                        </div>
                    @endif

                    <x-ui.editor label="Текст ответа" hide-label name="answer" wire:model="answer" placeholder="Начните писать здесь" />

                    <x-ui.dropzone onMint title="Прикрепите файлы или фото тетради" hint="PDF, Word, JPG или PNG до 50 МБ" :accept="implode(',', \App\Services\HomeworkSubmissionService::ACCEPTED_MIMES)"
                                   wire:model="picked" aria-label="Прикрепите файлы или фото тетради" />
                    <span class="text-t2 text-muted" wire:loading wire:target="picked">Загружаем…</span>
                    @error('picked.*')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                    @error('files.*')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror

                    @if ($keptFiles || $newFiles)
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach ($keptFiles as $i => $file)
                                <div class="flex items-center gap-3 rounded-lg bg-white p-3" wire:key="kept-{{ $file['path'] }}">
                                    <x-ui.file-tile :name="$file['path']" onMint />
                                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                                        <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="truncate text-t1 font-medium">{{ $file['name'] }}</a>
                                        <span class="truncate text-t2 text-muted">@if ($file['annotated'])<x-ui.em>С пометками учителя</x-ui.em>@else{{ $file['meta'] }}@endif</span>
                                    </div>
                                    <x-ui.btn size="s" square icon="x" wire:click="removeKept({{ $i }})" aria-label="Убрать файл" />
                                </div>
                            @endforeach
                            @foreach ($newFiles as $i => $file)
                                <div class="flex items-center gap-3 rounded-lg bg-white p-3" wire:key="new-{{ $i }}-{{ $file['name'] }}">
                                    <x-ui.file-tile :name="$file['name']" onMint />
                                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                                        <span class="truncate text-t1 font-medium">{{ $file['name'] }}</span>
                                        <span class="truncate text-t2 text-muted">{{ $file['meta'] }}</span>
                                    </div>
                                    <x-ui.btn size="s" square icon="x" wire:click="removeFile({{ $i }})" aria-label="Убрать файл" />
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex flex-col gap-4 pt-2 sm:flex-row sm:items-center">
                        <x-ui.btn variant="primary" size="l" icon="send" wire:click="submit" wire:loading.attr="disabled" wire:target="submit,picked">Отправить на проверку</x-ui.btn>
                        <span class="text-t2 text-muted">Изменить ответ можно, только если учитель вернёт работу</span>
                    </div>
                </x-ui.card>
            @else
                {{-- Фокус-блок: работа отправлена или проверена --}}
                <x-ui.card focus aria-labelledby="task-result">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($state === 'graded')
                            <x-ui.badge tone="ok">Оценка {{ $grade }}</x-ui.badge>
                            <span class="text-t2 text-muted">проверено {{ $checkedAt }}</span>
                        @elseif ($justSent)
                            <x-ui.badge tone="ok">Отправлено</x-ui.badge>
                            <span class="text-t2 text-muted">{{ $sentAt }}</span>
                        @else
                            <x-ui.badge>На проверке</x-ui.badge>
                            <span class="text-t2 text-muted">отправлено {{ $sentAt }}</span>
                        @endif
                    </div>
                    <div class="flex flex-col gap-1">
                        <h2 id="task-result" class="text-h2 font-medium lg:text-num">{{ $state === 'graded' ? 'Работа проверена' : 'Готово, учитель проверит' }}</h2>
                        @if ($state !== 'graded')
                            <p class="text-t1 text-muted">Сообщим, когда {{ $teacher?->name ?? 'учитель' }} проверит работу.</p>
                        @endif
                    </div>

                    @if ($state === 'graded' && ($feedback || $feedbackFiles))
                        <div class="flex flex-col gap-3 pt-2">
                            <h3 class="text-t2 text-muted">Комментарий учителя</h3>
                            @if ($feedback)
                                <div class="rich break-words rounded-lg bg-white px-4 py-3">{{ $feedback }}</div>
                            @endif
                            @include('livewire.cabinet.student.partials.task-files', ['files' => $feedbackFiles])
                        </div>
                    @endif

                    @if ($content || $myFiles)
                        <div class="flex flex-col gap-3 pt-2">
                            <h3 class="text-t2 text-muted">Ваш ответ</h3>
                            @if ($content)
                                <div class="rich break-words rounded-lg bg-white px-4 py-3">{{ $content }}</div>
                            @endif
                            @include('livewire.cabinet.student.partials.task-files', ['files' => $myFiles])
                        </div>
                    @endif

                    <div class="flex pt-2"><x-ui.btn :href="$backUrl">К заданиям</x-ui.btn></div>
                </x-ui.card>
            @endif
        </div>

        {{-- Учитель --}}
        @if ($teacher)
            <x-ui.card aria-label="Учитель">
                <div class="flex items-center gap-3">
                    <x-ui.avatar :user="$teacher" />
                    <x-ui.text :title="$teacher->name" sub="Ваш учитель" />
                    <x-ui.btn size="s" :href="$messengerUrl">Написать учителю</x-ui.btn>
                </div>
                @if ($previous)
                    <x-ui.list>
                        <div class="flex flex-col gap-3 border-t border-line pt-4">
                            <span class="text-t2 text-muted">Комментарий к прошлой работе · <a href="{{ $previous['url'] }}" class="link">{{ $previous['title'] }}, оценка {{ $previous['grade'] }}</a></span>
                            <p class="rounded-lg bg-soft px-4 py-3 text-t1">«{{ $previous['text'] }}»</p>
                        </div>
                    </x-ui.list>
                @endif
            </x-ui.card>
        @endif
    </div>
</div>
