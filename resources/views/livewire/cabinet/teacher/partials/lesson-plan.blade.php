{{-- Экран занятия: план конкретного занятия (пункты с новой строки). focus — фокус-блок экрана, editable — кнопка «Изменить». --}}
<x-ui.card :focus="$focus" aria-labelledby="l-plan">
    <x-ui.card-head id="l-plan" title="План занятия">
        @if ($editable)
            <x-slot:action><x-ui.btn size="s" wire:click="openLessonPlan">{{ $planItems ? 'Изменить' : 'Составить' }}</x-ui.btn></x-slot:action>
        @endif
    </x-ui.card-head>
    @if ($planItems)
        <ul class="flex flex-col gap-3">
            @foreach ($planItems as $item)
                <li class="flex gap-3 text-t1" wire:key="plan-{{ $loop->index }}"><span class="mt-2 size-2 shrink-0 rounded-full bg-ink" aria-hidden="true"></span><span class="min-w-0">{{ $item }}</span></li>
            @endforeach
        </ul>
    @else
        <p class="text-t2 text-muted">Пока пусто — запишите, что разобрать на занятии.</p>
    @endif
</x-ui.card>
