{{-- Панель уведомлений: справа на компьютере, на весь экран на телефоне. --}}
<div>
    @if ($open)
        <div class="fixed inset-0 z-30 flex justify-end bg-scrim" x-data x-on:keydown.escape.window="$wire.close()" wire:click.self="close">
            <aside role="dialog" aria-modal="true" aria-labelledby="notifications-title" class="flex h-full w-full flex-col bg-white shadow-modal lg:w-drawer lg:rounded-l-xl">
                <div class="flex items-center justify-between gap-4 px-6 pb-4 pt-6">
                    <h2 id="notifications-title" class="text-h2 font-medium">Уведомления</h2>
                    <x-ui.btn square icon="x" wire:click="close" aria-label="Закрыть" />
                </div>

                <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-3 pb-6">
                    @if ($fresh->isEmpty() && $old->isEmpty())
                        <p class="p-3 text-t2 text-muted">{{ $emptyText }}</p>
                    @endif

                    @foreach ([['Новые', $fresh], ['Раньше', $old]] as [$label, $items])
                        @if ($items->isNotEmpty())
                            <section class="flex flex-col" aria-label="{{ $label }}">
                                <div class="flex min-h-8 items-center justify-between gap-4 px-3">
                                    <span class="text-t3 font-semibold text-muted">{{ $label }}</span>
                                    @if ($label === 'Новые')
                                        <button type="button" wire:click="readAll" class="link text-t3">Прочитать все</button>
                                    @endif
                                </div>
                                @foreach ($items as $n)
                                    <button type="button" wire:key="n-{{ $n['id'] }}" wire:click="visit('{{ $n['id'] }}')"
                                        @class(['flex w-full items-center gap-3 rounded p-3 text-left', 'hover:bg-soft-hover' => $n['link'], 'cursor-default' => ! $n['link']])>
                                        <span class="flex size-10 shrink-0 items-center justify-center rounded bg-soft" aria-hidden="true"><x-ui.icon :name="$n['icon']" size="s" /></span>
                                        <span class="flex min-w-0 flex-1 flex-col">
                                            <span @class(['text-t1-s font-medium', 'text-ink' => $n['unread'], 'text-muted' => ! $n['unread']])>{{ $n['title'] }}</span>
                                            @if ($n['body'] !== '')<span class="line-clamp-2 text-t2 text-muted">{{ $n['body'] }}</span>@endif
                                        </span>
                                        <span class="shrink-0 self-start text-t3 text-muted">{{ $n['time'] }}</span>
                                        <span @class(['size-2 shrink-0 rounded-full', 'bg-danger' => $n['unread']]) aria-hidden="true"></span>
                                    </button>
                                @endforeach
                            </section>
                        @endif
                    @endforeach
                </div>

                <div class="flex items-center justify-between gap-4 border-t border-line px-6 py-4 text-t2">
                    @if ($settingsUrl)<a href="{{ $settingsUrl }}" class="link">Настроить уведомления</a>@else<span></span>@endif
                    @if ($fresh->isNotEmpty() || $old->isNotEmpty())
                        <button type="button" wire:click="clear" class="font-medium text-muted hover:text-ink">Очистить список</button>
                    @endif
                </div>
            </aside>
        </div>
    @endif
</div>
