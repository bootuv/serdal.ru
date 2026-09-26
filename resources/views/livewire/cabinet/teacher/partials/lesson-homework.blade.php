{{-- Экран занятия: задания, выданные к занятию. focus — фокус-блок экрана, withAction — кнопка «Выдать задание» в шапке. --}}
<x-ui.card :focus="$focus" aria-labelledby="l-hw">
    <x-ui.card-head id="l-hw" title="Домашнее задание">
        @if ($withAction)
            <x-slot:action><x-ui.btn size="s" icon="plus" :href="$taskUrl">Выдать задание</x-ui.btn></x-slot:action>
        @endif
    </x-ui.card-head>
    @if ($homework->isEmpty())
        <p class="text-t2 text-muted">Пока пусто — задания к этому занятию появятся здесь.</p>
    @else
        <x-ui.list>
            @foreach ($homework as $hw)
                <x-ui.row :href="$hw['url']" wire:key="hw-{{ $hw['id'] }}">
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="truncate text-t1 font-medium">{{ $hw['title'] }}</span>
                        @if ($hw['deadline'])<span class="text-t2 text-muted"><x-ui.em>{{ \Illuminate\Support\Str::ucfirst($hw['deadline']) }}</x-ui.em></span>@endif
                    </div>
                    @if ($hw['tone'])<x-ui.badge :tone="$hw['tone']">{{ $hw['label'] }}</x-ui.badge>@else<span class="shrink-0 text-t2 text-muted">{{ $hw['label'] }}</span>@endif
                </x-ui.row>
            @endforeach
        </x-ui.list>
    @endif
</x-ui.card>
