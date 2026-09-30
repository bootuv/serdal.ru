{{-- Окно «Запланировать занятие» (макет LsPlan). Состояние и сохранение — трейт PlansLessons. --}}
@if ($planOpen)
    <x-ui.modal title="Запланировать занятие" close="closePlan">
        <div class="flex flex-col gap-2">
            <span class="text-t2 font-medium">Тип занятия</span>
            <x-ui.seg :items="['individual' => 'Индивидуальное', 'group' => 'Групповое']" model="planKind" :active="$planKind" aria-label="Тип занятия" />
        </div>

        @if ($planKind === 'individual')
            <div class="flex flex-col gap-2">
                <x-ui.person-select label="Ученик" name="planStudentId" :people="$planPeople" :selected="$planStudentId" model="planStudentId" placeholder="Выберите ученика" />
                @unless ($errors->has('planStudentId'))<span class="text-t3 text-muted">{{ $planWhoHint }}</span>@endunless
            </div>
        @else
            <div class="flex flex-col gap-2">
                <span class="text-t2 font-medium">Ученики</span>
                @if ($planChosen)
                    <div class="flex flex-wrap gap-2">
                        @foreach ($planChosen as $person)
                            <span class="inline-flex h-9 items-center gap-1 rounded-full bg-soft pl-3 pr-1 text-t2 font-medium" wire:key="plan-st-{{ $person['id'] }}">
                                {{ $person['name'] }}
                                <button type="button" wire:click="removePlanStudent({{ $person['id'] }})" class="flex size-8 items-center justify-center rounded-full text-muted hover:text-ink" aria-label="Убрать: {{ $person['name'] }}"><x-ui.icon name="x" size="s" /></button>
                            </span>
                        @endforeach
                    </div>
                @endif
                @if ($planGroupPeople)
                    <x-ui.person-select name="planAdd" :people="$planGroupPeople" action="addPlanStudent" placeholder="Добавить ученика" />
                @endif
                @error('planStudents')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@else<span class="text-t3 text-muted">{{ $planWhoHint }}</span>@enderror
            </div>
        @endif

        <x-ui.field label="Предмет и название" name="planName" wire:model="planName" placeholder="Например: Английский язык" maxlength="255" />

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <x-ui.field :label="$planRepeat === 'weekly' ? 'Первое занятие' : 'Дата'" name="planDate" type="date" wire:model.live="planDate" />
            <x-ui.field label="Время" name="planTime" type="time" wire:model.live="planTime" />
            <x-ui.select label="Длительность" name="planDuration" :options="$planDurations" wire:model="planDuration" />
        </div>

        <div class="flex flex-col gap-2">
            <span class="text-t2 font-medium">Повтор</span>
            <x-ui.seg :items="['once' => 'Не повторять', 'weekly' => 'Каждую неделю']" model="planRepeat" :active="$planRepeat" aria-label="Повтор" />
        </div>

        @if ($planRepeat === 'weekly')
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
                <div class="flex flex-col gap-2">
                    <span class="text-t2 font-medium">По каким дням</span>
                    @include('livewire.cabinet.teacher.partials.lesson-day-chips', ['selected' => $planDays, 'action' => 'togglePlanDay', 'label' => 'По каким дням'])
                    @error('planDays')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                </div>
                <div class="min-w-0 flex-1">
                    <x-ui.field label="До какого дня, необязательно" name="planUntil" type="date" wire:model="planUntil" />
                </div>
            </div>

            @foreach ($planSlots as $i => $slot)
                <div class="flex flex-col gap-2 border-t border-line pt-4" wire:key="plan-slot-{{ $i }}">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-t2 font-medium">Другое время</span>
                        <button type="button" wire:click="removePlanSlot({{ $i }})" class="link text-t2">Убрать</button>
                    </div>
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                        @include('livewire.cabinet.teacher.partials.lesson-day-chips', ['selected' => $slot['days'] ?? [], 'action' => 'togglePlanDay', 'slot' => $i, 'label' => 'Дни недели'])
                        <div class="min-w-0 flex-1">
                            <x-ui.field label="Время" name="planSlots.{{ $i }}.time" type="time" wire:model="planSlots.{{ $i }}.time" />
                        </div>
                    </div>
                    @error('planSlots.' . $i . '.days')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
                </div>
            @endforeach

            <x-ui.btn size="s" icon="plus" wire:click="addPlanSlot" class="self-start">Добавить другое время</x-ui.btn>
        @endif

        <div class="flex flex-col gap-1 rounded-lg bg-soft p-4">
            <span class="text-t1-s font-semibold">{{ $planPrice['main'] }}</span>
            <span class="text-t2 text-muted">{{ $planPrice['sub'] }} <a href="{{ $planPricesUrl }}" class="link">{{ $planPrice['link'] }}</a></span>
        </div>

        <x-slot:note>{{ $planFirst }}</x-slot:note>
        <x-slot:footer>
            <x-ui.btn wire:click="closePlan">Отмена</x-ui.btn>
            <x-ui.btn variant="primary" wire:click="savePlan" wire:loading.attr="disabled" wire:target="savePlan">Запланировать</x-ui.btn>
        </x-slot:footer>
    </x-ui.modal>
@endif
