{{-- Окно «Презентации к занятию»: файлы, которые откроются в классе при старте (rooms.presentations),
     и подтверждение удаления презентации. Состояние — Lesson::openPresentations/savePresentations/deletePresentation. --}}
@if ($presentationsOpen)
    <x-ui.modal title="Презентации к занятию" sub="Откроются в классе, когда вы начнёте занятие" close="closePresentations"
                x-data="{ up: false, progress: 0 }"
                x-on:livewire-upload-start="up = true; progress = 0"
                x-on:livewire-upload-progress="progress = $event.detail.progress"
                x-on:livewire-upload-finish="up = false"
                x-on:livewire-upload-error="up = false; $wire.presentationUploadFailed()">
        <x-ui.dropzone wire:model="presentationUploads" :accept="$presentationAccept" aria-label="Презентации"
                       hint="PDF, PowerPoint, Word, Excel или фото JPG и PNG до 200 МБ"
                       x-on:change="$wire.presentationUploadNames = Array.from($event.target.files).map(f => f.name)" />
        <p x-show="up" x-cloak class="text-t2 text-muted">Передаём файлы… <span x-text="progress + '%'"></span></p>
        @error('presentationUploads')<p class="text-t2 font-medium text-danger-fg">{{ $message }}</p>@enderror

        @if ($presentationUploads)
            <x-ui.list>
                @foreach ($presentationUploads as $i => $u)
                    @php $uName = $presentationUploadNames[$i] ?? $u->getClientOriginalName(); @endphp
                    <x-ui.row wire:key="pres-up-{{ $i }}">
                        <x-ui.file-tile :name="$uName" />
                        <x-ui.text :title="$uName" :sub="\Illuminate\Support\Number::fileSize($u->getSize(), precision: 1)" />
                    </x-ui.row>
                @endforeach
            </x-ui.list>
        @endif

        <x-slot:note>Другие файлы — в <a href="{{ $materialsLink }}" class="link">«Материалах»</a></x-slot:note>
        <x-slot:footer>
            <x-ui.btn wire:click="closePresentations">Отмена</x-ui.btn>
            <x-ui.btn variant="primary" wire:click="savePresentations" wire:loading.attr="disabled" wire:target="savePresentations,presentationUploads" x-bind:disabled="up">Добавить</x-ui.btn>
        </x-slot:footer>
    </x-ui.modal>
@endif

@if ($confirmPresentation && ($confirmPresentationName ?? null))
    <x-ui.modal title="Удалить презентацию?" :sub="$confirmPresentationName" close="$set('confirmPresentation', null)" width="s">
        <p class="text-t1 text-ink">Файл удалится, в классе он больше не откроется.</p>
        <x-slot:footer>
            <x-ui.btn wire:click="$set('confirmPresentation', null)">Отмена</x-ui.btn>
            <x-ui.btn variant="dark" wire:click="deletePresentation">Удалить</x-ui.btn>
        </x-slot:footer>
    </x-ui.modal>
@endif
