@php
    use App\Models\LessonType;
    $hasPhoto = $photo || ($user->avatar && ! $removePhoto);
@endphp
<div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3 lg:gap-8">
    {{-- Шаги настройки --}}
    <aside class="hidden flex-col gap-6 rounded-xl bg-mint p-6 lg:flex" aria-label="Шаги настройки">
        <p class="text-h2 font-medium">Добро пожаловать, {{ $firstName }}!</p>
        <ol class="flex flex-col gap-2">
            @foreach ($steps as $n => [$title, $sub])
                @php $on = ! $done && $step === $n; $isDone = $done || $n < $reached && $n !== $step; @endphp
                <li>
                    <button type="button" wire:click="goTo({{ $n }})" @disabled($done || $n > $reached) @if ($on) aria-current="step" @endif
                            @class(['flex w-full items-center gap-4 rounded-lg p-4 text-left text-ink disabled:cursor-default', 'bg-white shadow-card' => $on])>
                        <span @class(['flex size-8 shrink-0 items-center justify-center rounded-full text-t2 font-semibold',
                                      'bg-ink text-white' => $on,
                                      'bg-white text-ink' => ! $on && $isDone,
                                      'text-muted shadow-outline' => ! $on && ! $isDone])>
                            @if (! $on && $isDone)<x-ui.icon name="check" size="s" />@else{{ $n }}@endif
                        </span>
                        <span class="text-t1 font-medium">{{ $title }}</span>
                    </button>
                </li>
            @endforeach
        </ol>
        <div class="flex flex-col gap-2 text-t2 text-muted">
            <span>Есть вопросы? <a href="mailto:info@serdal.ru" class="link">info@serdal.ru</a></span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="link">Выйти</button>
            </form>
        </div>
    </aside>

    <div class="flex min-w-0 flex-col gap-8 lg:col-span-2">
        @if (! $done)
            <header class="flex flex-col gap-2">
                <span class="text-t2 text-muted lg:hidden">Шаг {{ $step }} из 3</span>
                <h1 class="text-h1-m font-medium lg:text-h1">{{ $steps[$step][0] }}</h1>
                <p class="text-t1 text-muted">{{ $steps[$step][1] }}</p>
            </header>

            @if ($step === 1)
                <div class="flex flex-col gap-6">
                    <div class="flex flex-col gap-2">
                        <span class="text-t2 font-medium">Фото профиля</span>
                        <div class="flex items-center gap-4">
                            @if ($photo && ! $errors->has('photo') && $photo->isPreviewable())
                                <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-16 shrink-0 rounded-lg object-cover">
                            @elseif ($hasPhoto)
                                <img src="{{ $user->avatar_thumb_url }}" alt="" class="size-16 shrink-0 rounded-lg object-cover">
                            @else
                                <span class="flex size-16 shrink-0 items-center justify-center rounded-lg bg-soft text-muted"><x-ui.icon name="user" /></span>
                            @endif
                            <div class="flex min-w-0 flex-col gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-ui.photo-crop>{{ $hasPhoto ? 'Заменить' : 'Загрузить фото' }}</x-ui.photo-crop>
                                    @if ($hasPhoto)<x-ui.btn wire:click="deletePhoto">Удалить</x-ui.btn>@endif
                                </div>
                                <span wire:loading wire:target="photo" class="text-t3 text-muted">Загружаем…</span>
                                @error('photo')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                                <span class="text-t3 text-muted">Ученики увидят его в расписании и на занятиях</span>
                            </div>
                        </div>
                    </div>

                    @if ($directOptions->isNotEmpty())
                        <div class="flex flex-col gap-2" role="group" aria-labelledby="o-dir">
                            <span id="o-dir" class="text-t2 font-medium">Направления</span>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($directOptions as $id => $name)
                                    <x-ui.chip :on="in_array($id, $directs, true)" wire:click="toggleDirect({{ $id }})" wire:key="dir-{{ $id }}">{{ $name }}</x-ui.chip>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-col gap-2" role="group" aria-labelledby="o-grade">
                        <span id="o-grade" class="text-t2 font-medium">С кем занимаетесь</span>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($gradeGroups as $key => [$label, $members])
                                <x-ui.chip :on="empty(array_diff($members, $grades))" wire:click="toggleGradeGroup('{{ $key }}')">{{ $label }}</x-ui.chip>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <x-ui.field label="WhatsApp" name="whatsup" type="tel" wire:model="whatsup" placeholder="+7 900 000-00-00" />
                        <x-ui.field label="Telegram" name="telegram" wire:model="telegram" placeholder="@ник" />
                    </div>
                </div>
            @elseif ($step === 2)
                <div class="flex flex-col gap-6">
                    @foreach ($lessonTypes as $i => $row)
                        @php $monthly = ($row['payment_type'] ?? null) === LessonType::PAYMENT_MONTHLY; @endphp
                        <x-ui.card wire:key="lt-{{ $i }}" aria-labelledby="o-p{{ $i }}">
                            <div class="flex min-h-9 items-center justify-between gap-4">
                                <h2 id="o-p{{ $i }}" class="text-h2 font-medium">{{ $row['type'] === LessonType::TYPE_GROUP ? 'Групповые занятия' : 'Индивидуальные занятия' }}</h2>
                                @if (count($lessonTypes) > 1)<x-ui.btn size="s" wire:click="removeLessonType({{ $i }})">Убрать</x-ui.btn>@endif
                            </div>
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <x-ui.select label="Тип занятий" name="lessonTypes.{{ $i }}.type" :options="[LessonType::TYPE_INDIVIDUAL => 'Индивидуальные', LessonType::TYPE_GROUP => 'Групповые']"
                                             wire:model.live="lessonTypes.{{ $i }}.type" />
                                <div class="flex flex-col gap-2">
                                    <span class="text-t2 font-medium">Как платят ученики</span>
                                    <x-ui.seg :items="[LessonType::PAYMENT_PER_LESSON => 'За занятие', LessonType::PAYMENT_MONTHLY => 'За месяц']"
                                              :model="'lessonTypes.' . $i . '.payment_type'" :active="$row['payment_type']" aria-label="Как платят ученики" />
                                </div>
                                <x-ui.field :label="$monthly ? 'Цена за месяц, ₽' : 'Цена за занятие, ₽'" name="lessonTypes.{{ $i }}.price" type="number" min="1" inputmode="numeric"
                                            wire:model="lessonTypes.{{ $i }}.price" />
                                <x-ui.field label="Длительность, мин" name="lessonTypes.{{ $i }}.duration" type="number" min="15" inputmode="numeric"
                                            wire:model="lessonTypes.{{ $i }}.duration" />
                                @if ($monthly)
                                    <x-ui.field label="Занятий в неделю" name="lessonTypes.{{ $i }}.count_per_week" type="number" min="1" inputmode="numeric"
                                                wire:model="lessonTypes.{{ $i }}.count_per_week" />
                                @endif
                            </div>
                        </x-ui.card>
                    @endforeach
                    @error('lessonTypes')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                    @if (count($lessonTypes) < 2)
                        <x-ui.btn icon="plus" class="self-start" wire:click="addLessonType">
                            {{ ($lessonTypes[0]['type'] ?? null) === LessonType::TYPE_GROUP ? 'Добавить цену для индивидуальных занятий' : 'Добавить цену для групповых занятий' }}
                        </x-ui.btn>
                    @endif
                </div>
            @else
                <div class="flex flex-col gap-6">
                    <x-ui.seg :items="['month' => 'Помесячно', 'year' => 'На год' . ($maxDiscount > 0 ? ' — скидка до ' . $maxDiscount . '%' : '')]"
                              model="billingPeriod" :active="$billingPeriod" aria-label="Период оплаты" />

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2" role="radiogroup" aria-label="Тариф">
                        @foreach ($tariffs as $t)
                            @php
                                $on = $tariffId === $t->id;
                                $price = match (true) {
                                    $t->isFree() => 'Бесплатно',
                                    $billingPeriod === 'year' && $t->hasYearly() => number_format($t->yearly_price, 0, ',', ' ') . ' ₽ в год',
                                    default => number_format($t->price, 0, ',', ' ') . ' ₽ в месяц',
                                };
                            @endphp
                            <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="$set('tariffId', {{ $t->id }})" wire:key="tar-{{ $t->id }}"
                                    @class(['relative flex flex-col gap-2 rounded-lg bg-white p-6 text-left text-ink',
                                            'shadow-outline-ink' => $on, 'shadow-outline hover:shadow-outline-ink' => ! $on])>
                                @if ($on)<span class="absolute right-6 top-6 flex size-6 items-center justify-center rounded-full bg-ink text-white"><x-ui.icon name="check" size="s" /></span>@endif
                                <span class="flex flex-wrap items-center gap-2 pr-8"><span class="text-t1 font-medium">{{ $t->name }}</span>@if ($t->is_popular)<x-ui.badge>Популярный</x-ui.badge>@endif</span>
                                <span class="text-num font-medium">{{ $price }}</span>
                                <span class="text-t2 text-muted">{{ $t->short_description ?: $t->participants_label }}</span>
                                <span class="text-t2 font-semibold">{{ $t->lessons_label }} · {{ mb_strtolower($t->participants_label) }}</span>
                            </button>
                        @endforeach
                    </div>
                    @error('tariffId')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror

                    @if ($payable)
                        @if ($configured)
                            @include('livewire.cabinet.teacher.partials.pay-methods', ['methods' => $methods, 'model' => 'payMethod', 'active' => $payMethod])
                            <p class="text-t2 text-muted">Дальше откроется страница оплаты: <x-ui.em>{{ $payAmount }}</x-ui.em>. Тариф включится сразу после оплаты, до этого действует бесплатный «{{ $freeName }}».</p>
                        @else
                            <p class="text-t2 text-muted">Онлайн-оплата подключается — тариф можно будет оплатить позже в разделе «Тариф и платежи». Пока будет действовать бесплатный «{{ $freeName }}».</p>
                        @endif
                    @endif
                </div>
            @endif

            <footer class="flex items-center justify-between gap-4 border-t border-line pt-6">
                <div class="flex items-center gap-2">
                    @if ($step > 1)<x-ui.btn size="l" icon="arrow-left" wire:click="back">Назад</x-ui.btn>@endif
                </div>
                <div class="flex items-center gap-4">
                    @if ($step === 1)<button type="button" class="link text-t1" wire:click="skip">Пропустить</button>@endif
                    @if ($step < 3)
                        <x-ui.btn variant="primary" size="l" wire:click="next" wire:loading.attr="disabled" wire:target="next,photo">Дальше</x-ui.btn>
                    @else
                        <x-ui.btn variant="primary" size="l" wire:click="finish" wire:loading.attr="disabled" wire:target="finish">{{ $payable && $configured ? 'Завершить и оплатить' : 'Завершить настройку' }}</x-ui.btn>
                    @endif
                </div>
            </footer>
            <div class="flex flex-wrap items-center gap-2 text-t2 text-muted lg:hidden">
                <span>Есть вопросы? <a href="mailto:info@serdal.ru" class="link">info@serdal.ru</a></span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="link">Выйти</button>
                </form>
            </div>
        @else
            {{-- Готово --}}
            <div class="flex flex-col gap-6">
                <span class="flex size-16 items-center justify-center rounded-lg bg-ok-bg text-ok-fg"><x-ui.icon name="check" /></span>
                <header class="flex flex-col gap-2">
                    <h1 class="text-h1-m font-medium lg:text-h1">Всё готово, {{ $firstName }}!</h1>
                    <p class="text-t1 text-muted">{{ $doneNote ?? 'Профиль и цены сохранены, действует тариф «' . ($user->activeSubscription()?->tariff->name ?? $freeName) . '».' }}</p>
                </header>
            </div>

            <x-ui.card focus aria-labelledby="o-inv">
                <div class="flex flex-col gap-1">
                    <h2 id="o-inv" class="text-h2 font-medium">Пригласите первого ученика</h2>
                    <p class="text-t2 text-muted">Ученик создаст аккаунт по ссылке и сразу появится в вашем списке.</p>
                </div>
                <x-ui.copy-field label="Ссылка-приглашение" :value="$inviteUrl" />
            </x-ui.card>

            <div class="flex flex-wrap items-center gap-4">
                <x-ui.btn variant="primary" size="l" :href="$cabinetUrl">Перейти в кабинет</x-ui.btn>
                <button type="button" class="link text-t1" wire:click="restart">Изменить настройки</button>
            </div>
        @endif
    </div>
</div>
