<div>
    <x-filament::section :heading="'История сессий (' . $totalCount . ')'" collapsible collapsed>
        @if($sessions->isEmpty())
            <p class="text-gray-500 dark:text-gray-400 text-sm">Сессий пока не было</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap">Дата</th>
                            @if(auth()->user()->role === \App\Models\User::ROLE_STUDENT)
                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                    Посещаемость</th>
                            @endif
                            <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Длительность</th>
                            @if(auth()->user()->role !== \App\Models\User::ROLE_STUDENT)
                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Стоимость</th>
                            @endif
                            <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300 text-right whitespace-nowrap">
                                Участники</th>
                            @if(auth()->user()->role !== \App\Models\User::ROLE_STUDENT)
                                <th
                                    class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300 text-right whitespace-nowrap w-10">
                                </th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($sessions as $session)
                            @php

                                $duration = '';
                                if ($session->ended_at && $session->started_at) {
                                    $duration = $session->started_at->diffForHumans($session->ended_at, true);
                                } elseif ($session->started_at) {
                                    $duration = $session->started_at->diffForHumans(now(), true) . ' (активна)';
                                }
                                $isAttended = false;
                                if (auth()->user()->role === \App\Models\User::ROLE_STUDENT) {
                                    $participants = $session->analytics_data['participants'] ?? [];
                                    foreach ($participants as $p) {
                                        if (($p['user_id'] ?? '') === (string) auth()->user()->id) { // Ensure comparison as strings if needed, though int safe usually
                                            $isAttended = true;
                                            break;
                                        }
                                    }
                                }
                            @endphp
                            @if($viewUrl)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer transition-colors"
                                    onclick="window.location.href='{{ str_replace(':id', $session->id, $viewUrl) }}'">
                            @else
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                @endif
                                <td class="px-4 py-3 text-gray-900 dark:text-white font-medium whitespace-nowrap">
                                    {{ format_datetime($session->started_at) }}
                                </td>
                                @if(auth()->user()->role === \App\Models\User::ROLE_STUDENT)
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex justify-start items-center gap-1.5">
                                            @if($isAttended)
                                                <svg class="w-4 h-4 text-success-600 dark:text-success-500"
                                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd"
                                                        d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                                <span class="text-sm font-medium text-success-600 dark:text-success-500">Посетил</span>
                                            @else
                                                <svg class="w-4 h-4 text-danger-600 dark:text-danger-500"
                                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path
                                                        d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                                </svg>
                                                <span class="text-sm font-medium text-danger-600 dark:text-danger-500">Пропустил</span>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                    {{ $duration }}
                                </td>
                                @if(auth()->user()->role !== \App\Models\User::ROLE_STUDENT)
                                    @php
                                        // Use stored pricing snapshot if available (immutable historical data)
                                        $sessionCost = 0;
                                        if (isset($session->pricing_snapshot['total_cost'])) {
                                            $sessionCost = $session->pricing_snapshot['total_cost'];
                                        } else {
                                            // Fallback to dynamic calculation for old sessions without snapshot
                                            $room = $session->room;
                                            if ($room) {
                                                $lessonType = $room->user?->lessonTypes?->where('type', $room->type)->first();
                                                $paymentType = $lessonType?->payment_type ?? 'per_lesson';
                                                if ($paymentType === 'monthly') {
                                                    foreach ($room->participants as $participant) {
                                                        $sessionCost += $room->getEffectivePrice($participant->id) ?? 0;
                                                    }
                                                } else {
                                                    $analytics = $session->analytics_data ?? [];
                                                    $participantsData = $analytics['participants'] ?? [];
                                                    $attendedIds = collect($participantsData)->pluck('user_id')->map(fn($id) => (string) $id)->toArray();
                                                    foreach ($room->participants as $participant) {
                                                        if (in_array((string) $participant->id, $attendedIds)) {
                                                            $sessionCost += $room->getEffectivePrice($participant->id) ?? 0;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    @endphp
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        {{ number_format($sessionCost, 0, '.', ' ') }} ₽
                                    </td>
                                @endif
                                @php
                                    $stats = $session->getStudentAttendance();
                                    $color = $stats['color'];
                                    // Calculate a lighter background color based on the main color (simple opacity approach or hardcoded mapping if needed, but hex with opacity works for modern browsers, or we can just set text color and a standard light bg?)
                                    // User asked for "inline styles". Let's try to set style directly.
                                    // Using rgba for background to make it lighter.
                                    // Converting hex to rgb manually or simply using a consistent look.
                                    // Simple approach: Use the color for text and a very light standard bg, or try to apply the color to bg with opacity.
                                    // Actually, let's use the pure hex for text and a 10% opacity version for background.
                                @endphp
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-right whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center justify-center -my-1 mx-auto min-h-6 min-w-6 px-2 py-0.5 rounded-full text-xs font-medium"
                                        style="color: {{ $color }}; background-color: {{ $color }}1A;">
                                        <!-- 1A is ~10% opacity in hex -->
                                        {{ $stats['attended'] }}/{{ $stats['total'] }}
                                    </span>
                                </td>
                                @if(auth()->user()->role !== \App\Models\User::ROLE_STUDENT)
                                    <td class="px-4 py-3 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                                        @if($session->deletion_requested_at)
                                            <button type="button" wire:click="openCancelModal({{ $session->id }})"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 dark:hover:text-gray-300 transition-colors"
                                                title="Отменить запрос на удаление">
                                                <x-heroicon-o-arrow-uturn-left class="w-4 h-4" />
                                            </button>
                                        @else
                                            <button type="button" wire:click="openDeletionModal({{ $session->id }})"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 dark:hover:text-gray-300 transition-colors"
                                                title="Запросить удаление">
                                                <x-heroicon-o-trash class="w-4 h-4" />
                                            </button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($hasMore)
                <div class="mt-4 flex justify-center">
                    <x-filament::button wire:click="loadMore" color="gray" size="sm" icon="heroicon-m-arrow-down">
                        Показать ещё
                    </x-filament::button>
                </div>
            @endif
        @endif
    </x-filament::section>

    {{-- Deletion Request Modal --}}
    @if($showDeletionModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-full items-center justify-center p-4">
                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75 transition-opacity"
                    wire:click="closeDeletionModal"></div>

                {{-- Modal panel --}}
                <div
                    class="relative transform overflow-hidden rounded-xl bg-white dark:bg-gray-900 text-left shadow-xl transition-all w-full max-w-md">
                    {{-- Close button --}}
                    <div class="absolute right-0 top-0 pr-4 pt-4">
                        <button type="button" wire:click="closeDeletionModal"
                            class="rounded-lg p-1 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="p-6">
                        {{-- Icon --}}
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-warning-100 dark:bg-warning-500/20 mb-4">
                            <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                        </div>

                        {{-- Header --}}
                        <div class="text-center">
                            <h3 class="text-lg font-semibold text-gray-950 dark:text-white" id="modal-title">
                                Запрос на удаление сессии
                            </h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Укажите причину удаления. Администратор рассмотрит ваш запрос.
                            </p>
                        </div>

                        {{-- Form --}}
                        <div class="mt-6">
                            <label for="deletion_reason" class="fi-fo-field-wrp-label inline-flex items-center gap-x-3">
                                <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                                    Причина удаления
                                    <sup class="text-danger-600 dark:text-danger-400 font-medium">*</sup>
                                </span>
                            </label>
                            <textarea wire:model="deletionReason" id="deletion_reason" rows="3"
                                class="fi-textarea mt-1 block w-full rounded-lg border-none bg-white py-1.5 text-base text-gray-950 outline-none transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-primary-600 disabled:text-gray-500 disabled:[-webkit-text-fill-color:theme(colors.gray.500)] disabled:placeholder:[-webkit-text-fill-color:theme(colors.gray.400)] dark:bg-white/5 dark:text-white dark:placeholder:text-gray-500 dark:disabled:text-gray-400 dark:disabled:[-webkit-text-fill-color:theme(colors.gray.400)] dark:disabled:placeholder:[-webkit-text-fill-color:theme(colors.gray.500)] sm:text-sm sm:leading-6 ring-1 ring-inset ring-gray-950/10 dark:ring-white/20"></textarea>
                            @error('deletionReason')
                                <p class="fi-fo-field-wrp-error-message mt-1 text-sm text-danger-600 dark:text-danger-400">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Footer --}}
                        <div class="mt-6 flex justify-center gap-3">
                            <x-filament::button color="gray" wire:click="closeDeletionModal">
                                Отменить
                            </x-filament::button>
                            <x-filament::button color="primary" wire:click="submitDeletionRequest">
                                Подтвердить
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Cancel Confirmation Modal --}}
    @if($showCancelModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-full items-center justify-center p-4">
                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75 transition-opacity"
                    wire:click="closeCancelModal"></div>

                {{-- Modal panel --}}
                <div
                    class="relative transform overflow-hidden rounded-xl bg-white dark:bg-gray-900 text-left shadow-xl transition-all w-full max-w-md">
                    {{-- Close button --}}
                    <div class="absolute right-0 top-0 pr-4 pt-4">
                        <button type="button" wire:click="closeCancelModal"
                            class="rounded-lg p-1 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="p-6">
                        {{-- Icon --}}
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-warning-100 dark:bg-warning-500/20 mb-4">
                            <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                        </div>

                        {{-- Header --}}
                        <div class="text-center">
                            <h3 class="text-lg font-semibold text-gray-950 dark:text-white" id="modal-title">
                                Отменить запрос на удаление?
                            </h3>
                        </div>

                        {{-- Footer --}}
                        <div class="mt-6 flex justify-center gap-3">
                            <x-filament::button color="gray" wire:click="closeCancelModal">
                                Отмена
                            </x-filament::button>
                            <x-filament::button color="primary" wire:click="confirmCancelDeletion">
                                Подтвердить
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>