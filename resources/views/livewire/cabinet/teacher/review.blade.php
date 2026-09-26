@php
    $pencil = '<svg class="ic ic-s" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4"/></svg>';
@endphp
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head :title="$homework->title" :sub="$facts" :back="$backUrl" back-label="Задания">
        @if ($nextUrl)
            <x-slot:actions>
                @if ($position)<span class="hidden text-t2 text-muted sm:inline">Работа {{ $position }} из {{ $total }}</span>@endif
                <x-ui.btn :href="$nextUrl">Следующая<x-ui.icon name="chevron-right" size="s" /></x-ui.btn>
            </x-slot:actions>
        @endif
    </x-ui.page-head>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        {{-- Ответ ученика --}}
        <x-ui.card class="min-w-0 lg:col-span-2" aria-labelledby="answer">
            <x-ui.card-head id="answer" title="Ответ ученика">
                @if ($photos)<x-slot:action><span class="hidden text-t2 text-muted sm:inline">Нажмите на фото, чтобы оставить пометки</span></x-slot:action>
                @endif
            </x-ui.card-head>

            @if ($content)
                <div class="flex items-start gap-3">
                    <x-ui.avatar :user="$student" />
                    <div class="rich min-w-0 flex-1 break-words">{{ $content }}</div>
                </div>
            @endif

            @if ($photos)
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    @foreach ($photos as $i => $p)
                        <button type="button" wire:click="annotate({{ $i }})" wire:key="ph-{{ $p['path'] }}" aria-label="Открыть {{ $p['label'] }} для пометок"
                                class="flex min-w-0 flex-col gap-3 rounded-lg p-2 pb-3 text-left shadow-outline hover:shadow-outline-ink">
                            <span class="relative block">
                                <img src="{{ $p['url'] }}" alt="" loading="lazy" class="h-40 w-full rounded-sm bg-soft object-cover">
                                @if ($p['annotated'])
                                    <span class="absolute right-2 top-2 flex size-8 items-center justify-center rounded-sm bg-white text-ink shadow-seg">{!! $pencil !!}</span>
                                @endif
                            </span>
                            <span class="flex items-center gap-2 px-1">
                                <span class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="truncate text-t2 font-medium">{{ $p['name'] }}</span>
                                    @if ($p['annotated'])
                                        <span class="truncate text-t2 font-semibold text-ink">Есть пометки</span>
                                    @else
                                        <span class="truncate text-t2 text-muted">Без пометок</span>
                                    @endif
                                </span>
                                <x-ui.icon name="chevron-right" class="text-faint" />
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif

            @if ($otherFiles)
                <x-ui.list>
                    @foreach ($otherFiles as $file)
                        <x-ui.row :href="$file['url']" :chevron="false" target="_blank" rel="noopener" wire:key="af-{{ $file['path'] }}">
                            <x-ui.file-tile :name="$file['path']" />
                            <x-ui.text :title="$file['name']" :sub="$file['meta']" />
                            <x-ui.icon name="download" class="text-muted group-hover:text-ink" />
                        </x-ui.row>
                    @endforeach
                </x-ui.list>
            @endif

            @if (! $content && ! $photos && ! $otherFiles)
                <p class="text-t2 text-muted">Ответ пустой — уточните у ученика в сообщениях, что случилось.</p>
            @endif
        </x-ui.card>

        <div class="flex min-w-0 flex-col gap-6">
            {{-- Фокус-блок: оценка --}}
            <x-ui.card focus aria-labelledby="grade-title">
                @if ($mode === 'form')
                    <div class="flex flex-col gap-6">
                        <div class="flex flex-col gap-3" {!! $scale ? 'role="radiogroup" aria-labelledby="grade-title"' : '' !!}>
                            <div class="flex flex-col gap-1">
                                <h2 id="grade-title" class="text-h2 font-medium">Оценка</h2>
                                <span class="text-t2 text-muted">{{ $grade ? $grade . ' из ' . $max : 'от 1 до ' . $max . ' ' . plural_ru($max, 'балла', 'баллов', 'баллов', false) }}</span>
                            </div>
                            @if ($scale)
                                <div class="grid grid-cols-5 gap-1 rounded bg-white p-1">
                                    @foreach ($scale as $n)
                                        <button type="button" role="radio" aria-checked="{{ (int) $grade === $n ? 'true' : 'false' }}" aria-label="{{ $n }} из {{ $max }}"
                                                wire:click="pickGrade({{ $n }})"
                                                @class(['h-11 rounded-sm text-h2 tabular-nums',
                                                        'bg-ok-bg font-semibold text-ok-fg' => (int) $grade === $n,
                                                        'font-medium text-muted hover:text-ink' => (int) $grade !== $n])>{{ $n }}</button>
                                    @endforeach
                                </div>
                            @else
                                <label class="flex items-center gap-3">
                                    <span class="sr-only">Баллы</span>
                                    <input type="number" name="grade" min="1" max="{{ $max }}" inputmode="numeric" wire:model.live.debounce.300ms="grade"
                                           @class(['field w-40', 'shadow-outline-ink' => $errors->has('grade')])>
                                    <span class="text-t1 text-muted">из {{ $max }}</span>
                                </label>
                            @endif
                            @error('grade')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                        </div>

                        <div class="flex flex-col gap-3">
                            <x-ui.field label="Комментарий для ученика" name="comment" rows="5" wire:model="comment"
                                        placeholder="Что получилось хорошо и что стоит исправить" />
                            <label class="link inline-flex cursor-pointer items-center gap-2 self-start text-t2">
                                <x-ui.icon name="upload" size="s" />Прикрепить файл
                                <input type="file" multiple class="sr-only" wire:model="picked"
                                       accept="{{ implode(',', \App\Services\HomeworkSubmissionService::TASK_MIMES) }}">
                            </label>
                            <span wire:loading wire:target="picked" class="text-t2 text-muted">Загружаем…</span>
                            @error('picked.*')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                            @error('files.*')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                            @foreach ($newFiles as $i => $file)
                                <div class="flex items-center gap-3 rounded-lg bg-white p-2" wire:key="fb-{{ $i }}-{{ $file['name'] }}">
                                    <x-ui.file-tile :name="$file['name']" />
                                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                                        <span class="truncate text-t2 font-medium">{{ $file['name'] }}</span>
                                        <span class="truncate text-t2 text-muted">{{ $file['meta'] }}</span>
                                    </div>
                                    <x-ui.btn size="s" square icon="x" wire:click="removeFile({{ $i }})" aria-label="Убрать файл" />
                                </div>
                            @endforeach
                        </div>

                        <div class="flex flex-col gap-2">
                            <x-ui.btn variant="primary" class="w-full" wire:click="accept" wire:loading.attr="disabled" wire:target="accept,giveBack,picked">
                                {{ $editing ? 'Сохранить оценку' : 'Принять и оценить' }}
                            </x-ui.btn>
                            @if ($canReturn)
                                <x-ui.btn class="w-full" wire:click="giveBack" wire:loading.attr="disabled" wire:target="accept,giveBack,picked">Вернуть на доработку</x-ui.btn>
                            @endif
                            @if ($editing)
                                <x-ui.btn class="w-full" wire:click="cancelEdit">Отмена</x-ui.btn>
                            @endif
                            <span class="text-t2 text-muted">{{ $firstName }} получит уведомление</span>
                        </div>
                    </div>
                @elseif ($mode === 'graded')
                    <x-ui.card-head id="grade-title" title="Работа принята">
                        <x-slot:action><x-ui.badge tone="ok">Оценка {{ $gradeLabel }}</x-ui.badge></x-slot:action>
                    </x-ui.card-head>
                    <p class="text-t2 text-muted">{{ $firstName }} видит оценку, комментарий и пометки.</p>
                    @if ($feedback)<div class="rich break-words rounded-lg bg-white px-4 py-3">{{ $feedback }}</div>@endif
                    <div class="flex flex-wrap items-center gap-4">
                        <x-ui.btn wire:click="edit">Изменить оценку</x-ui.btn>
                        @if ($nextUrl)<a href="{{ $nextUrl }}" class="link text-t2">Следующая работа</a>@endif
                    </div>
                @else
                    <x-ui.card-head id="grade-title" title="Вернули на доработку">
                        <x-slot:action><x-ui.badge tone="danger">На доработке</x-ui.badge></x-slot:action>
                    </x-ui.card-head>
                    <p class="text-t2 text-muted">Когда {{ $firstName }} исправит работу, она снова появится в «Нужно проверить».</p>
                    @if ($feedback)<div class="rich break-words rounded-lg bg-white px-4 py-3">{{ $feedback }}</div>@endif
                    <div class="flex flex-wrap items-center gap-4">
                        <x-ui.btn :href="$backUrl">К заданиям</x-ui.btn>
                        @if ($nextUrl)<a href="{{ $nextUrl }}" class="link text-t2">Следующая работа</a>@endif
                    </div>
                @endif
            </x-ui.card>

            {{-- Условие задания --}}
            <x-ui.card aria-labelledby="task">
                <x-ui.card-head id="task" title="Задание" />
                @if ($description)
                    <div class="rich break-words">{{ $description }}</div>
                @endif
                @if ($taskFiles)
                    <x-ui.list>
                        @foreach ($taskFiles as $file)
                            <x-ui.row :chevron="false" wire:key="tf-{{ $file['path'] }}">
                                <x-ui.file-tile :name="$file['path']" />
                                <x-ui.text :title="$file['name']" :sub="$file['meta']" />
                                <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="link text-t2">Открыть</a>
                            </x-ui.row>
                        @endforeach
                    </x-ui.list>
                @endif
                <span class="text-t2 text-muted">{{ $issued }}</span>
            </x-ui.card>
        </div>
    </div>

    {{-- Пометки на фото: холст ImageAnnotator внутри окна --}}
    @if ($current)
        {{-- Закрыть и переключить фото — через холст: он предупредит о несохранённых пометках --}}
        <x-ui.modal :title="'Пометки · ' . $current['label']" :sub="trim(($student?->name ?? '') . ' · ' . $homework->title, ' ·')" close="$dispatch('annotator-leave')" width="l">
            <div class="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-start">
                @if (count($photos) > 1)
                    <div class="flex gap-3 overflow-x-auto lg:w-40 lg:shrink-0 lg:flex-col" role="group" aria-label="Фото">
                        @foreach ($photos as $i => $p)
                            <button type="button" x-on:click="$dispatch('annotator-leave', { photo: {{ $i }} })" aria-pressed="{{ $p['path'] === $current['path'] ? 'true' : 'false' }}" wire:key="rail-{{ $p['path'] }}"
                                    @class(['flex w-40 shrink-0 flex-col gap-2 rounded-lg p-2 text-left lg:w-full',
                                            'shadow-outline-ink' => $p['path'] === $current['path'],
                                            'shadow-outline hover:shadow-outline-ink' => $p['path'] !== $current['path']])>
                                <img src="{{ $p['url'] }}" alt="" loading="lazy" class="h-16 w-full rounded-sm bg-soft object-cover">
                                <span class="flex flex-col gap-1 px-1">
                                    <span class="truncate text-t2 font-medium">{{ $p['name'] }}</span>
                                    <span class="text-t2 text-muted">{{ $p['annotated'] ? 'Есть пометки' : 'Без пометок' }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif
                <livewire:image-annotator :image-path="$current['path']" :submission-id="$submission->id" :key="'ann-' . md5($current['path'])" />
            </div>
            <x-slot:note>{{ $marksNote }}</x-slot:note>
            <x-slot:footer>
                <x-ui.btn x-on:click="$dispatch('annotator-leave')">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" x-on:click="$dispatch('annotator-save')">Сохранить пометки</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
