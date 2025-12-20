@php
    $fullUrl = route('tutors.show', ['username' => auth()->user()->username]);
    $displayUrl = preg_replace('#^https?://#', '', $fullUrl);
@endphp

<div x-data="{
        url: '{{ $fullUrl }}',
        copied: false,
        copyToClipboard() {
            navigator.clipboard.writeText(this.url).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
                
                // Filament notification
                new FilamentNotification()
                    .title('Ссылка скопирована')
                    .success()
                    .send();
            });
        }
    }"
    class="flex items-center gap-2 px-3 pr-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-all cursor-pointer group"
    style="height: 32px;" @click="copyToClipboard" title="Нажмите, чтобы скопировать ссылку">
    <a href="{{ $fullUrl }}" target="_blank" class="text-sm font-medium hover:underline truncate max-w-[180px]"
        style="color: #D97706;" @click.stop>
        {{ $displayUrl }}
    </a>

    <button type="button"
        class="flex-shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors rounded hover:bg-gray-100 dark:hover:bg-gray-700"
        @click.stop="copyToClipboard" title="Скопировать ссылку">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            class="w-4 h-4" x-show="!copied">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
        </svg>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            class="w-4 h-4 text-green-500" x-show="copied" x-cloak>
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
    </button>
</div>