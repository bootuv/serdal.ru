<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Профиль" :sub="'Ученик · на ' . \App\Support\Seo::SITE_NAME . ' с ' . $since">
        <x-slot:actions>
            <form method="POST" action="{{ route('filament.student.auth.logout') }}">
                @csrf
                <x-ui.btn type="submit" icon="logout">Выйти</x-ui.btn>
            </form>
        </x-slot:actions>
    </x-ui.page-head>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
            {{-- Личные данные --}}
            <x-ui.card as="form" wire:submit="save" aria-labelledby="pf-data">
                <x-ui.card-head id="pf-data" title="Личные данные" />

                <div class="flex items-center gap-4" x-data>
                    @if ($photo && ! $errors->has('photo') && $photo->isPreviewable())
                        <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-16 shrink-0 rounded-lg object-cover">
                    @elseif ($user->avatar)
                        <img src="{{ $user->avatar_url }}" alt="" class="size-16 shrink-0 rounded-lg object-cover">
                    @else
                        <x-ui.avatar :user="$user" size="lg" />
                    @endif
                    <div class="flex min-w-0 flex-col gap-2">
                        <x-ui.btn size="s" class="self-start" x-on:click="$refs.photo.click()" wire:loading.attr="disabled" wire:target="photo">Загрузить фото</x-ui.btn>
                        <input type="file" x-ref="photo" wire:model="photo" accept="image/*" class="sr-only" tabindex="-1" aria-label="Фото профиля">
                        <span wire:loading wire:target="photo" class="text-t2 text-muted">Загружаем…</span>
                        @error('photo')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                        @if ($photo && ! $errors->has('photo'))<span class="text-t2 text-muted">Сохраните, чтобы обновить фото</span>@endif
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-x-6">
                    <x-ui.field label="Имя и фамилия" name="name" wire:model="name" autocomplete="name" />
                    <x-ui.select label="Класс" name="grade" :options="$grades" placeholder="Не указан" wire:model="grade" />
                    <x-ui.field label="Почта" name="email" type="email" wire:model="email" autocomplete="email" />
                    <x-ui.field label="Телефон" name="phone" type="tel" wire:model="phone" autocomplete="tel" />
                    <x-ui.field label="Новый пароль" name="password" type="password" wire:model="password" autocomplete="new-password"
                                placeholder="Оставьте пустым, если не меняете" />
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <x-ui.btn variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">Сохранить</x-ui.btn>
                    @if ($saved)<x-ui.badge tone="ok">Изменения сохранены</x-ui.badge>@endif
                </div>
            </x-ui.card>

            {{-- Учителя: текущие, затем бывшие --}}
            <x-ui.card aria-labelledby="pf-teachers">
                <x-ui.card-head id="pf-teachers" title="Учителя" />
                @if ($teachers->isEmpty())
                    <p class="text-t2 text-muted">Пока пусто — учителя появятся здесь, когда пригласят вас на занятия.</p>
                @else
                    <x-ui.list>
                        @foreach ($teachers as $t)
                            <x-ui.row :chevron="false" class="flex-wrap" wire:key="teacher-{{ $t['id'] }}">
                                <x-ui.avatar :name="$t['name']" :id="$t['id']" />
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="truncate text-t1 font-medium">{{ $t['name'] }}</span>
                                    @if ($t['sub'])<span class="text-t2 text-muted">{{ $t['sub'] }}</span>@endif
                                    @if ($t['rejected'])<span class="text-t2 text-muted">Новый отзыв этому учителю оставить нельзя</span>@endif
                                </div>
                                <div class="flex w-full flex-wrap items-center gap-4 lg:w-auto">
                                    @if ($t['rejected'])
                                        <x-ui.badge>Отзыв скрыт модератором</x-ui.badge>
                                    @else
                                        @if ($t['review'])
                                            <x-ui.stars :value="$t['review']->rating" role="img" />
                                        @endif
                                        @if ($t['canReview'])
                                            <button type="button" class="link text-t2" wire:click="openReview({{ $t['id'] }})">{{ $t['review'] ? 'Изменить отзыв' : 'Оставить отзыв' }}</button>
                                        @elseif ($t['lessons'] === 0 && ! $t['review'])
                                            <span class="text-t2 text-muted">Отзыв — после первого занятия</span>
                                        @endif
                                    @endif
                                    @if ($t['chat'])
                                        <x-ui.btn size="s" :href="$t['chat']">Написать</x-ui.btn>
                                    @endif
                                </div>
                            </x-ui.row>
                        @endforeach
                    </x-ui.list>
                @endif
            </x-ui.card>
        </div>

        {{-- Уведомления на этом устройстве --}}
        <x-ui.card aria-labelledby="pf-notify">
            <livewire:push-notification-toggle variant="cabinet" key="push-switch" />
        </x-ui.card>
    </div>

    {{-- Окно отзыва --}}
    @if ($reviewing)
        @php
            $hasReview = (bool) $reviewing['review'];
            $sub = $hasReview
                ? $reviewing['name'] . ' · опубликован ' . \App\Support\HumanDate::at($reviewing['review']->updated_at ?? $reviewing['review']->created_at)
                : implode(' · ', array_filter([$reviewing['name'], $reviewing['teacher']->subjects->pluck('name')->join(', ')]));
        @endphp
        <x-ui.modal :title="$hasReview ? 'Ваш отзыв' : 'Отзыв об учителе'" :sub="$sub" close="closeReview">
            <div class="flex flex-col gap-2">
                <span class="text-t2 font-medium">Оценка</span>
                <div class="flex flex-wrap items-center gap-4">
                    <x-ui.stars :value="$rating" model="rating" />
                    <span class="text-t1 font-semibold">{{ $stars[$rating] ?? '' }}</span>
                </div>
                @error('rating')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
            </div>
            <x-ui.field label="Расскажите о занятиях" name="reviewText" rows="6" wire:model="reviewText"
                        placeholder="Например: что получилось благодаря занятиям" hint="Без телефонов и ссылок" />

            <x-slot:note>
                @if ($reviewing['publicUrl'])
                    {{ $hasReview ? 'Обновится' : 'Появится' }} на <a href="{{ $reviewing['publicUrl'] }}" class="link" target="_blank" rel="noopener">{{ preg_replace('#^https?://#', '', $reviewing['publicUrl']) }}</a>
                @endif
            </x-slot:note>
            <x-slot:footer>
                <x-ui.btn wire:click="closeReview">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="saveReview" wire:loading.attr="disabled" wire:target="saveReview">{{ $hasReview ? 'Сохранить изменения' : 'Отправить отзыв' }}</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
