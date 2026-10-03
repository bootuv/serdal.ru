{{-- Жалобы на отзывы. Макет: AdminReviews. Вкладки «Жалобы / Все отзывы / Скрытые / О платформе», поиск, окно отзыва с решением,
     «Поделиться» — окно с картинкой для сторис (RvShareDesktop), на телефоне — системное окно. --}}
<div class="flex flex-col gap-6 lg:gap-8">
    <x-ui.page-head title="Жалобы на отзывы" />

    <div class="flex flex-col gap-6">
        {{-- Вкладки и поиск в одной строке с общей линией снизу --}}
        <div class="flex flex-col lg:flex-row lg:items-end">
            <x-ui.tabs model="tab" :active="$tab" :items="$tabs" :counts="['reports' => $reportsCount, 'platform' => $platformCount]" class="flex-1" aria-label="Отзывы" />
            <div class="pt-4 lg:border-b lg:border-line lg:pb-2 lg:pl-6 lg:pt-0">
                <x-ui.search wire:model.live.debounce.300ms="q" placeholder="Ученик или учитель" />
            </div>
        </div>

        <x-ui.card class="gap-0" aria-label="{{ $tabs[$tab] }}">
            @if ($rows->isEmpty())
                <p class="text-t2 text-muted">{{ $emptyText }}</p>
            @else
            <x-ui.list>
                @foreach ($rows as $x)
                    <button type="button" wire:click="open({{ $x['id'] }})" wire:key="rv-{{ $x['id'] }}"
                            class="group flex w-full items-start gap-4 border-t border-line py-4 text-left first:border-t-0 first:pt-0 last:pb-0">
                        <x-ui.avatar :user="$x['user']" :name="$x['student']" />
                        <span class="flex min-w-0 flex-1 flex-col gap-2">
                            <span class="flex min-w-0 flex-col gap-1">
                                <span class="text-t1 font-medium group-hover:underline group-hover:decoration-line-strong group-hover:underline-offset-4">{{ $x['pair'] }}</span>
                                <span class="flex items-center gap-2 text-t2 text-muted"><x-ui.stars :value="$x['rating']" />{{ $x['date'] }}</span>
                            </span>
                            <span class="line-clamp-2 text-t1-s">{{ $x['text'] }}</span>
                            @if ($x['reported'])
                                <span class="flex flex-wrap items-center gap-2">
                                    <x-ui.badge tone="danger">{{ $x['reason'] }}</x-ui.badge>
                                    @if ($x['reportedAt'])<span class="text-t2 text-muted">жалоба {{ $x['reportedAt'] }}</span>@endif
                                </span>
                            @endif
                            @if ($x['platform'])
                                {{-- Исключение — ждёт проверки: жирным; остальное — тихо --}}
                                <span @class(['text-t2', 'font-semibold' => $x['platformStatus'] === 'Ждёт проверки', 'text-muted' => $x['platformStatus'] !== 'Ждёт проверки'])>{{ $x['platformStatus'] }}</span>
                            @elseif ($x['hidden'])<span class="text-t2 text-muted">{{ $x['hiddenNote'] }}</span>@endif
                        </span>
                        <x-ui.icon name="chevron-right" class="self-center text-faint group-hover:text-ink" />
                    </button>
                @endforeach
            </x-ui.list>
            @endif
            @if ($more > 0)
                <button type="button" wire:click="more" class="link mt-6 self-start text-t1-s">Показать ещё {{ min($more, 20) }}</button>
            @endif
        </x-ui.card>
    </div>

    {{-- Отзыв --}}
    @if ($r && $step === 'view')
        <x-ui.modal :title="$r['title']" :sub="$r['sub']" close="close">
            <div class="flex flex-col gap-2 rounded-lg bg-soft p-4">
                <x-ui.stars :value="$r['rating']" />
                <p class="whitespace-pre-line text-t1-s">{{ $r['text'] }}</p>
            </div>

            @if ($r['hasReport'])
                <section class="flex flex-col gap-3" aria-labelledby="rv-rep">
                    <span id="rv-rep" class="text-t3 font-semibold text-muted">{{ $r['reportTitle'] }}</span>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.badge :tone="$r['status'] === 'reported' ? 'danger' : 'neutral'">{{ $r['reason'] }}</x-ui.badge>
                        <span class="text-t2 text-muted">{{ $r['reportBy'] }}</span>
                    </div>
                    @if ($r['note'])<p class="whitespace-pre-line text-t1-s">«{{ $r['note'] }}»</p>@endif
                </section>
            @endif

            @if ($r['platform'])
                <p class="text-t2 text-muted">{{ $r['platformText'] }}</p>
            @elseif ($r['status'] === 'hidden')
                <p class="text-t2 text-muted">{{ $r['hiddenText'] }}</p>
            @endif

            @if ($r['status'] === 'reported')
                <x-ui.option wire:model.live="notify" title="Сообщить учителю о решении" :sub="$r['teacherShort'] . ' получит письмо'" />
            @endif

            <x-slot:footer>
                @if ($r['status'] === 'pending')
                    <x-ui.btn wire:click="toHide">Не публиковать</x-ui.btn>
                    <x-ui.btn variant="primary" wire:click="approve" wire:loading.attr="disabled" wire:target="approve">Опубликовать</x-ui.btn>
                @elseif ($r['status'] === 'published')
                    <x-ui.btn wire:click="toHide">Снять с сайта</x-ui.btn>
                    <x-ui.btn variant="primary" icon="share" wire:click="toShare" class="hidden lg:inline-flex">Поделиться</x-ui.btn>
                    <x-ui.btn variant="primary" icon="share" class="lg:hidden"
                              x-init="innerWidth < 1024 && window.serdalPrefetchReviewCard({{ Js::from($r['shareUrl']) }})"
                              x-on:pointerdown="window.serdalPrefetchReviewCard({{ Js::from($r['shareUrl']) }})"
                              x-on:click="window.serdalShareReviewCard({{ Js::from($r['shareUrl']) }}, () => $wire.toShare(), $el)">Поделиться</x-ui.btn>
                @elseif ($r['status'] === 'private')
                    <x-ui.btn wire:click="close">Закрыть</x-ui.btn>
                @elseif ($r['status'] === 'reported')
                    <x-ui.btn variant="dark" wire:click="toHide">Скрыть отзыв</x-ui.btn>
                    <x-ui.btn variant="primary" wire:click="toKeep">Оставить отзыв</x-ui.btn>
                @elseif ($r['status'] === 'visible')
                    <x-ui.btn wire:click="toHide">Скрыть отзыв</x-ui.btn>
                    <x-ui.btn variant="primary" icon="share" wire:click="toShare" class="hidden lg:inline-flex">Поделиться</x-ui.btn>
                    <x-ui.btn variant="primary" icon="share" class="lg:hidden"
                              x-init="innerWidth < 1024 && window.serdalPrefetchReviewCard({{ Js::from($r['shareUrl']) }})"
                              x-on:pointerdown="window.serdalPrefetchReviewCard({{ Js::from($r['shareUrl']) }})"
                              x-on:click="window.serdalShareReviewCard({{ Js::from($r['shareUrl']) }}, () => $wire.toShare(), $el)">Поделиться</x-ui.btn>
                @else
                    <x-ui.btn wire:click="close">Закрыть</x-ui.btn>
                    <x-ui.btn variant="primary" wire:click="restore">Вернуть отзыв</x-ui.btn>
                @endif
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Поделиться --}}
    @if ($r && $step === 'share' && $r['shareable'])
        @include('livewire.cabinet.partials.review-share-modal', [
            'shared' => ['name' => $r['name'], 'date' => $r['day'], 'text' => $r['text'], 'shareUrl' => $r['shareUrl']],
            'pageLabel' => $r['pageLabel'],
            'close' => 'back',
        ])
    @endif

    {{-- Скрыть --}}
    @if ($r && $step === 'hide')
        <x-ui.modal :title="$r['platform'] ? 'Не показывать на сайте?' : 'Скрыть отзыв?'" :sub="$r['pair']" close="back" width="s">
            <p class="text-t1">{{ $r['hideText'] }}</p>
            @if ($r['status'] === 'reported')
                <p class="text-t2 text-muted">{{ $r['teacherShort'] }} {{ $notify ? 'получит' : 'не получит' }} письмо о решении.</p>
            @endif
            <x-slot:footer>
                <x-ui.btn wire:click="back">Отмена</x-ui.btn>
                <x-ui.btn variant="dark" wire:click="hide" wire:loading.attr="disabled" wire:target="hide">{{ $r['platform'] ? 'Не показывать' : 'Скрыть отзыв' }}</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Оставить --}}
    @if ($r && $step === 'keep')
        <x-ui.modal title="Оставить отзыв?" :sub="$r['pair']" close="back" width="s">
            <p class="text-t1">{{ $r['keepText'] }}</p>
            <p class="text-t2 text-muted">{{ $r['teacherShort'] }} {{ $notify ? 'получит' : 'не получит' }} письмо о решении.</p>
            <x-slot:footer>
                <x-ui.btn wire:click="back">Отмена</x-ui.btn>
                <x-ui.btn variant="primary" wire:click="keep" wire:loading.attr="disabled" wire:target="keep">Оставить отзыв</x-ui.btn>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($toast)
        <x-ui.toast-action :message="$toast" close="hideToast" :action="$undo ? 'undoHide' : null" wire:key="toast-{{ md5($toast . json_encode($undo)) }}" />
    @endif
</div>
