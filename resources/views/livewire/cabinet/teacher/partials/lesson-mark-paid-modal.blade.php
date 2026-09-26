{{-- Окно «Отметить оплату» (макет PmMarkPaid): выбор неоплаченных занятий ученика. Состояние — трейт MarksPayments. --}}
@if ($markStudentId && ! empty($markStudent))
    <x-ui.modal title="Отметить оплату" :sub="$markStudent->name" close="closeMarkPaid" width="s">
        <div class="flex flex-col gap-2" role="group" aria-label="Занятия">
            @foreach ($markRows as $row)
                <x-ui.option value="{{ $row['id'] }}" wire:model.live="markSelected" wire:key="mark-{{ $row['id'] }}">
                    <span class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="truncate text-t1-s font-medium">{{ $row['title'] }}</span>
                        @if ($row['hint'])<span @class(['text-t2', 'font-semibold text-ink' => $row['overdue'], 'text-muted' => ! $row['overdue']])>{{ $row['hint'] }}</span>@endif
                    </span>
                    @if ($row['amount'])<span class="shrink-0 text-t1-s font-medium">{{ \App\Support\Money::format($row['amount']) }}</span>@endif
                </x-ui.option>
            @endforeach
        </div>
        @error('markSelected')<span class="text-t2 font-medium text-danger-fg">{{ $message }}</span>@enderror
        @if ($markSum)
            <div class="flex items-center justify-between gap-4 border-t border-line pt-4">
                <span class="text-t1 font-medium">Итого</span>
                <span class="text-h2 font-medium">{{ \App\Support\Money::format($markSum) }}</span>
            </div>
        @endif
        <x-slot:footer>
            <x-ui.btn wire:click="closeMarkPaid">Отмена</x-ui.btn>
            <x-ui.btn variant="primary" wire:click="confirmMarkPaid" :disabled="empty($markSelected)">Отметить оплату</x-ui.btn>
        </x-slot:footer>
    </x-ui.modal>
@endif
