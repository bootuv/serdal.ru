<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head :title="$heading" :sub="$facts" :back="route('cabinet.admin.news')" back-label="Новости">
        <x-slot:actions>
            <div class="hidden items-center gap-2 lg:flex">
                @if (in_array($status, ['new', 'draft'], true))
                    <x-ui.btn wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft,submit">Сохранить черновик</x-ui.btn>
                @endif
                <x-ui.btn variant="primary" wire:click="submit" wire:loading.attr="disabled" wire:target="saveDraft,submit">{{ $submitLabel }}</x-ui.btn>
            </div>
        </x-slot:actions>
    </x-ui.page-head>

    {{-- Телефон: действия шапки под заголовком --}}
    <div class="flex flex-wrap items-center gap-2 lg:hidden">
        <x-ui.btn variant="primary" wire:click="submit" wire:loading.attr="disabled" wire:target="saveDraft,submit">{{ $submitLabel }}</x-ui.btn>
        @if (in_array($status, ['new', 'draft'], true))
            <x-ui.btn wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft,submit">Сохранить черновик</x-ui.btn>
        @endif
    </div>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-12">
        <div class="flex w-full min-w-0 flex-col gap-6 lg:w-form lg:shrink-0">
            <x-ui.field label="Заголовок" name="title" wire:model.live.debounce.500ms="title" placeholder="Например, «Новое в расписании»" />

            <x-ui.editor-media label="Текст" name="body" wire:model="body" upload-model="image" upload-method="storeImage" video-model="video" video-method="storeVideo"
                               hint="Картинки, GIF и видео сжимаются сами. Видео — до 200 МБ" />

            @if ($model)
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    @if ($status === 'published')
                        <button type="button" class="link text-t2" wire:click="unpublish">Снять с публикации</button>
                    @elseif ($status === 'scheduled')
                        <button type="button" class="link text-t2" wire:click="unpublish">Отменить публикацию</button>
                    @endif
                    <button type="button" class="link text-t2" wire:click="askDelete">Удалить новость</button>
                </div>
            @endif
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-6">
            @if ($stats)
                <x-ui.card aria-labelledby="an-stats">
                    <x-ui.card-head id="an-stats" title="Прочитали">
                        <x-slot:action><span class="text-t2 text-muted"><x-ui.em>{{ $stats['read'] }}</x-ui.em> из {{ $stats['total'] }}</span></x-slot:action>
                    </x-ui.card-head>
                    <x-ui.progress :value="$stats['read']" :max="$stats['total']" label="Прочитали новость" />
                </x-ui.card>
            @endif

            <x-ui.card aria-labelledby="an-who">
                <x-ui.card-head id="an-who" title="Кому" />
                @if ($notified)
                    <div class="flex flex-col gap-1">
                        <span class="text-t1-s font-medium">{{ $audiences[$audience] ?? '' }}</span>
                        <span class="text-t2 text-muted">Уведомление получили {{ $who }}. Адресатов уже не изменить.</span>
                    </div>
                @else
                    <x-ui.seg :items="$audiences" model="audience" :active="$audience" aria-label="Кому показать новость" />
                    <span class="text-t2 text-muted">Уведомление получат {{ $who }}</span>
                @endif
            </x-ui.card>

            <x-ui.card aria-labelledby="an-show">
                <x-ui.card-head id="an-show" title="Как показать" />
                <div class="flex items-center justify-between gap-4">
                    <div class="flex min-w-0 flex-col gap-1">
                        <span class="text-t1-s font-medium">Важная</span>
                        <span class="text-t2 text-muted">Карточка на главной, пока не прочитают</span>
                    </div>
                    <x-ui.switch :checked="$important" label="Важная" wire:click="$toggle('important')" />
                </div>
                <div class="flex items-center justify-between gap-4 border-t border-line pt-4">
                    <div class="flex min-w-0 flex-col gap-1">
                        <span class="text-t1-s font-medium">Закрепить</span>
                        <span class="text-t2 text-muted">Вверху списка новостей</span>
                    </div>
                    <x-ui.switch :checked="$pinned" label="Закрепить" wire:click="$toggle('pinned')" />
                </div>
                <div class="flex items-center justify-between gap-4 border-t border-line pt-4">
                    <div class="flex min-w-0 flex-col gap-1">
                        <span class="text-t1-s font-medium">Письмо на почту</span>
                        <span class="text-t2 text-muted">{{ $notified ? ($sendMail ? 'Письмо отправлено' : 'Письмо не отправляли') : 'Вместе с уведомлением в кабинете — для того, что нельзя пропустить' }}</span>
                    </div>
                    @unless ($notified)
                        <x-ui.switch :checked="$sendMail" label="Письмо на почту" wire:click="$toggle('sendMail')" />
                    @endunless
                </div>
            </x-ui.card>

            @if ($status !== 'published')
                <x-ui.card aria-labelledby="an-when">
                    <x-ui.card-head id="an-when" title="Когда опубликовать" />
                    <x-ui.seg :items="['now' => 'Сразу', 'later' => 'По времени']" model="when" :active="$when" aria-label="Когда опубликовать" />
                    @if ($when === 'later')
                        <x-ui.field label="Дата и время" name="publishAt" type="datetime-local" wire:model="publishAt" hint="По московскому времени" />
                    @endif
                </x-ui.card>
            @endif
        </div>
    </div>

    @if ($confirmPublish)
        <x-ui.modal title="Опубликовать новость?" :sub="$heading" close="closePublish" width="s">
            <p class="text-t1-s">Уведомление получат {{ $who }}{{ $sendMail ? ', и ещё придёт письмо на почту' : '' }}. Отменить рассылку после публикации нельзя.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="closePublish">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="publish" wire:loading.attr="disabled" wire:target="publish">Опубликовать</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($confirmDelete && $model)
        <x-ui.modal title="Удалить новость?" :sub="$model->title" close="closeDelete" width="s">
            <p class="text-t1-s">Новость пропадёт у учителей и учеников вместе со статистикой прочтений. Вернуть её нельзя.</p>
            @if ($status === 'published')
                <span class="text-t2 text-muted">Нужно только убрать из кабинетов? <button type="button" class="link" wire:click="unpublish">Снимите с публикации</button></span>
            @endif
            <x-slot:footer>
                <x-ui.btn wire:click="closeDelete">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="delete">Удалить новость</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
