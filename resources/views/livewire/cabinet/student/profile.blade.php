<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Профиль" :sub="'Ученик · на ' . \App\Support\Seo::SITE_NAME . ' с ' . $since">
        <x-slot:actions>
            <form method="POST" action="{{ route('logout') }}">
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

                <div class="flex items-center gap-4">
                    @if ($photo && ! $errors->has('photo') && $photo->isPreviewable())
                        <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-16 shrink-0 rounded-lg object-cover">
                    @elseif ($user->avatar && ! $removePhoto)
                        <img src="{{ $user->avatar_thumb_url }}" alt="" class="size-16 shrink-0 rounded-lg object-cover">
                    @else
                        <x-ui.avatar :user="$user" size="lg" />
                    @endif
                    <div class="flex min-w-0 flex-col gap-2">
                        <div class="flex flex-wrap items-center gap-4">
                            <x-ui.photo-crop size="s" icon="upload">Загрузить фото</x-ui.photo-crop>
                            @if ($photo || ($user->avatar && ! $removePhoto))
                                <button type="button" class="link text-t2" wire:click="deletePhoto">Удалить</button>
                            @endif
                        </div>
                        <span wire:loading wire:target="photo" class="text-t2 text-muted">Загружаем…</span>
                        @error('photo')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                        @if (($photo && ! $errors->has('photo')) || ($removePhoto && $user->avatar))<span class="text-t2 text-muted">Сохраните, чтобы обновить фото</span>@endif
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-x-6">
                    <x-ui.field label="Фамилия" name="last_name" wire:model="last_name" autocomplete="family-name" />
                    <x-ui.field label="Имя" name="first_name" wire:model="first_name" autocomplete="given-name" />
                    <x-ui.field label="Отчество" name="middle_name" wire:model="middle_name" autocomplete="additional-name" optional />
                    <x-ui.select label="Класс" name="grade" :options="$grades" placeholder="Не указан" wire:model="grade" />
                    <x-ui.field label="Почта" name="email" type="email" wire:model="email" autocomplete="email" />
                    <x-ui.field label="Телефон" name="phone" type="tel" wire:model="phone" autocomplete="tel" />
                    <x-ui.field label="Новый пароль" name="password" type="password" wire:model="password" autocomplete="new-password"
                                placeholder="Оставьте пустым, если не меняете" hint="Минимум 8 символов" />
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <x-ui.btn variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">Сохранить</x-ui.btn>
                    @if ($saved)<x-ui.badge tone="ok">Изменения сохранены</x-ui.badge>@endif
                </div>
            </x-ui.card>
        </div>

        {{-- Уведомления на этом устройстве --}}
        <x-ui.card aria-labelledby="pf-notify">
            <livewire:push-notification-toggle key="push-switch" />
        </x-ui.card>
    </div>
</div>
