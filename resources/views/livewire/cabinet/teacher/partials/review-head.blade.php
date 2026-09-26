{{-- Шапка отзыва: имя (новый — красная точка), звёзды и дата. $r — отзыв, $new — непрочитанный. --}}
<span class="flex min-w-0 flex-col gap-1">
    <span class="flex min-w-0 items-center gap-2 text-t1 font-medium">
        <span class="truncate">{{ $r['name'] }}</span>
        @if ($new)<span class="size-2 shrink-0 rounded-full bg-danger" aria-label="Новый"></span>@endif
    </span>
    <span class="flex flex-wrap items-center gap-2 text-t2 text-muted">
        <x-ui.stars :value="$r['rating']" />{{ $r['date'] }}
        @if (! $new && $r['reported'])<x-ui.badge>Жалоба на проверке</x-ui.badge>@endif
    </span>
</span>
