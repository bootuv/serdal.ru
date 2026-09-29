{{-- Отзыв учителя о платформе: приглашение на «Сегодня» (prompt) или карточка в профиле. Логика — PlatformReviewService.
     Корень — contents: когда приглашения нет, пустой блок не добавляет отступ в колонке карточек. --}}
<div class="contents">
    @if ($visible)
        @if ($prompt)
            {{-- Приглашение: появляется, когда учитель провёл несколько занятий. Призыв к отзыву — голубая карточка rate
                 со звёздами (как «Как вам занятия?» у ученика): звезда открывает форму с этой оценкой --}}
            <x-ui.card rate aria-labelledby="pr-ask">
                <div class="flex flex-col gap-1">
                    <h2 id="pr-ask" class="text-h2 font-medium">Как вам Serdal?</h2>
                    <p class="text-t2 text-muted">Расскажите коллегам, как вам работается на платформе.</p>
                </div>
                <div class="flex flex-col gap-2">
                    <x-ui.stars :value="$promptRating" model="promptRating" on-tint />
                    <span class="text-t2 text-muted">Оцените от 1 до 5 · <button type="button" wire:click="dismiss" class="link">Позже</button></span>
                </div>
            </x-ui.card>
        @elseif (! $review)
            {{-- Карточка в профиле, пока отзыва нет, — тоже призыв: голубая, со звёздами --}}
            <x-ui.card rate aria-labelledby="pr-card">
                <div class="flex flex-col gap-1">
                    <h2 id="pr-card" class="text-h2 font-medium">Отзыв о Serdal</h2>
                    <p class="text-t2 text-muted">Расскажите, как вам работается на платформе. Отзыв появится на странице отзывов после проверки.</p>
                </div>
                <div class="flex flex-col gap-2">
                    <x-ui.stars :value="$promptRating" model="promptRating" on-tint />
                    <span class="text-t2 text-muted">Оцените от 1 до 5</span>
                </div>
            </x-ui.card>
        @else
            {{-- Отзыв оставлен — обычная карточка: оценка, текст, статус --}}
            <x-ui.card aria-labelledby="pr-card">
                <x-ui.card-head id="pr-card" title="Отзыв о Serdal">
                    <x-slot:action><button type="button" wire:click="openForm" class="link text-t2">Изменить</button></x-slot:action>
                </x-ui.card-head>
                <div class="flex flex-col gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.stars :value="$review->rating" role="img" aria-label="Оценка {{ $review->rating }} из 5" />
                        <span class="text-t2 text-muted">{{ $updated }}</span>
                    </div>
                    <p class="line-clamp-3 break-words text-t1-s">{{ $review->text }}</p>
                    <span @class(['text-t2', 'font-semibold' => $status === 'На проверке', 'text-muted' => $status !== 'На проверке'])>
                        {{ $status }}@if ($status === 'На сайте') · <a href="{{ $publicUrl }}" class="link" target="_blank" rel="noopener">страница отзывов</a>@endif
                    </span>
                </div>
            </x-ui.card>
        @endif
    @endif

    @if ($open)
        <x-ui.modal :title="$review ? 'Ваш отзыв о Serdal' : 'Отзыв о Serdal'" :sub="$review ? 'После изменения отзыв снова пройдёт проверку' : 'Появится на странице отзывов после проверки'" close="closeForm">
            <div class="flex flex-col gap-2">
                <span class="text-t2 font-medium">Оценка</span>
                <div class="flex flex-wrap items-center gap-4">
                    <x-ui.stars :value="$rating" model="rating" />
                    <span class="text-t1 font-semibold">{{ $stars[$rating] ?? '' }}</span>
                </div>
                @error('rating')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            </div>
            <x-ui.field label="Расскажите о своём опыте" name="text" rows="6" wire:model="text"
                        placeholder="Например: что стало проще, чего не хватает" hint="Без телефонов и ссылок" maxlength="{{ \App\Models\Review::MAX_TEXT }}" />
            <x-ui.option wire:model="showOnSite" title="Показать на сайте" sub="С вашим именем и фото на странице отзывов. Без галочки отзыв увидит только команда Serdal" />

            <x-slot:footer>
                <x-ui.btn wire:click="closeForm">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">{{ $review ? 'Сохранить изменения' : 'Отправить отзыв' }}</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
