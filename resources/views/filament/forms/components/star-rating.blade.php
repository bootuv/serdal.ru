<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <!-- Wrapper with x-data. We define 'state' to bind to the Livewire property. -->
    <div x-data="{ 
        state: @js($getState()),
        hover: 0,
        rate(value) {
            this.state = value;
            $wire.set('{{ $getStatePath() }}', value);
        }
    }" class="flex items-center gap-1">

        @foreach(range(1, 5) as $i)
            <button type="button" @click="rate({{ $i }})" @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0"
                class="focus:outline-none transition-transform hover:scale-110 p-0.5" title="{{ $i }} out of 5">
                <!-- 
                             We use inline styles and explicit logic to ensure colors work. 
                             Gray (#D1D5DB) for inactive, Orange (#F59E0B) for active.
                        -->
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"
                    :style="(hover || state) >= {{ $i }} ? 'color: #F59E0B;' : 'color: #D1D5DB;'">
                    <path
                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
            </button>
        @endforeach

        <!-- Hidden Input to ensuring form submission picks it up if entangle fails (fallback) -->
        <input type="hidden" id="{{ $getId() }}" x-model="state" />
    </div>
</x-dynamic-component>