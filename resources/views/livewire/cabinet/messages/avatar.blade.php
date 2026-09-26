{{-- Аватар диалога: поддержка, группа или человек. --}}
@if ($d['type'] === 'support')
    <span class="flex size-10 shrink-0 items-center justify-center rounded bg-soft" aria-hidden="true"><x-ui.icon name="help" size="s" /></span>
@elseif ($d['type'] === 'group' || ! $d['avatar'])
    <x-ui.avatar group />
@else
    <x-ui.avatar :user="$d['avatar']" />
@endif
