{{-- Отзыв учителя о платформе: приглашение на «Сегодня» (prompt) или карточка в профиле. Логика — PlatformReviewService.
     Корень — contents: когда приглашения нет, пустой блок не добавляет отступ в колонке карточек. --}}
<div class="contents">
    @if ($visible)
        @if ($prompt)
            {{-- Приглашение: появляется, когда учитель провёл несколько занятий; можно закрыть --}}
            <x-ui.card aria-labelledby="pr-ask">
                <x-ui.card-head id="pr-ask" title="Как вам Serdal?" />
                <p class="text-t2 text-muted">Расскажите коллегам, как вам работается на платформе — отзыв появится на странице отзывов после проверки.</p>
                <div class="flex flex-wrap items-center gap-4">
                    <x-ui.btn icon="star" wire:click="openForm">Оставить отзыв</x-ui.btn>
                    <button type="button" wire:click="dismiss" class="link text-t2">Не сейчас</button>
                </div>
            </x-ui.card>
        @else
            <x-ui.card aria-labelledby="pr-card">
                <x-ui.card-head id="pr-card" title="Отзыв о Serdal">
                    @if ($review)
                        <x-slot:action><button type="button" wire:click="openForm" class="link text-t2">Изменить</button></x-slot:action>
                    @endif
                </x-ui.card-head>
                @if ($review)
                    <div class="flex flex-col gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.stars :value="$review->rating" role="img" aria-label="Оценка {{ $review->rating }} из 5" />
                            <span class="text-t2 text-muted">{{ $updated }}</span>
                        </div>
                        <p class="line-clamp-3 text-t1-s">{{ $review->text }}</p>
                        <span @class(['text-t2', 'font-semibold' => $status === 'На проверке', 'text-muted' => $status !== 'На проверке'])>
                            {{ $status }}@if ($status === 'На сайте') · <a href="{{ $publicUrl }}" class="link" target="_blank" rel="noopener">страница отзывов</a>@endif
                        </span>
                    </div>
                @else
                    <p class="text-t2 text-muted">Расскажите, как вам работается на платформе. Отзыв появится на странице отзывов после проверки.</p>
                    <x-ui.btn icon="star" wire:click="openForm" class="self-start">Оставить отзыв</x-ui.btn>
                @endif
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
