{{-- Линейная иконка 24×24, stroke 1.8. size: s (16) | m (20). Новые иконки — только в этот список. --}}
@props(['name', 'size' => 'm'])
@php
    $paths = [
        'home' => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'chat' => '<path d="M5 5h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-5 4V6a1 1 0 0 1 1-1z"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5M16 4.8a3.5 3.5 0 0 1 0 6.4M18.5 14.8c1.6.8 2.6 2.6 3 5.2"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.8-3.5 3.6-5.5 7-5.5s6.2 2 7 5.5"/>',
        'tasks' => '<rect x="5" y="4" width="14" height="17" rx="3"/><path d="M9 3h6v3H9zM9 13l2 2 4-4"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'video' => '<rect x="3" y="6" width="13" height="12" rx="3"/><path d="M16 10.5 21 8v8l-5-2.5"/>',
        'star' => '<path d="M12 3.5l2.6 5.4 5.9.8-4.3 4.1 1 5.9L12 16.9l-5.2 2.8 1-5.9-4.3-4.1 5.9-.8z"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="13" rx="3"/><path d="M3 10h18M15 15h3"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5V14M12 17.2v.1"/>',
        'bell' => '<path d="M6 16v-5a6 6 0 1 1 12 0v5l1.5 2h-15zM10 20.5a2 2 0 0 0 4 0"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'arrow-left' => '<path d="M19 12H5m6-6-6 6 6 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'play' => '<path d="M8 5.5v13l11-6.5z"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'repeat' => '<path d="M4 12a8 8 0 0 1 14-5.3M20 4v4h-4M20 12a8 8 0 0 1-14 5.3M4 20v-4h4"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'more' => '<path d="M5 12h.01M12 12h.01M19 12h.01" stroke-width="3"/>',
        'download' => '<path d="M12 4v11m-5-5 5 5 5-5M5 20h14"/>',
        'share' => '<path d="M12 15V4m-4 4 4-4 4 4M5 13v6a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-6"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="3"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l4-4-4-4M14 12H4"/>',
    ];
@endphp
<svg {{ $attributes->class(['ic', 'ic-s' => $size === 's']) }} viewBox="0 0 24 24" aria-hidden="true">{!! $paths[$name] ?? '' !!}</svg>
