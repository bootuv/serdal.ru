<div x-data="{
show: @entangle('show'),
isSupported: false,
permissionDenied: false,
isLoading: false,
checksCompleted: false,

async init() {
this.isSupported = 'serviceWorker' in navigator && 'PushManager' in window;

if (!this.isSupported) {
this.show = false;
this.checksCompleted = true;
return;
}

if (Notification.permission === 'denied') {
this.permissionDenied = true;
this.show = false;
this.checksCompleted = true;
return;
}

// Initialize push notifications and sync with server
const vapidKey = '{{ $this->getVapidPublicKey() }}';
if (vapidKey && window.PushNotifications) {
await window.PushNotifications.init(vapidKey);

// Use syncWithServer to detect mismatches (e.g., user revoked then re-granted)
const needsSubscription = await window.PushNotifications.syncWithServer();
if (!needsSubscription) {
this.show = false;
}
// If needsSubscription is true, show stays as is (from Livewire)
}
this.checksCompleted = true;
},

async enableNotifications() {
this.isLoading = true;

try {
const vapidKey = '{{ $this->getVapidPublicKey() }}';
if (vapidKey && window.PushNotifications) {
await window.PushNotifications.init(vapidKey);
const success = await window.PushNotifications.subscribe();
if (success) {
this.$wire.call('dismiss');
} else if (Notification.permission === 'denied') {
this.permissionDenied = true;
}
}
} catch (error) {
console.error('Failed to enable push:', error);
} finally {
this.isLoading = false;
}
},

remindLater() {
this.$wire.call('remindLater');
}
}" x-init="init()" x-show="show && isSupported && !permissionDenied && checksCompleted" x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 dark:bg-gray-900/80">
    <div x-show="show" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="mx-4 w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800">
        {{-- Icon --}}
        <div class="flex justify-center">
            <div style="width: 48px; height: 48px;"
                class="flex shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/50">
                <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" xmlns="http://www.w3.org/2000/svg"
                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
            </div>
        </div>

        {{-- Title --}}
        <h3 class="mt-4 text-center text-lg font-semibold text-gray-900 dark:text-white">
            Включить уведомления?
        </h3>

        {{-- Description --}}
        <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
            Включите, чтобы не пропускать важные события: новые сообщения, начало занятий, изменения в расписании и
            другие обновления
        </p>

        {{-- Buttons --}}
        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:gap-3">
            <button type="button" x-on:click="enableNotifications()" :disabled="isLoading"
                class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-70 dark:bg-primary-500 dark:hover:bg-primary-400">
                <template x-if="isLoading">
                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                </template>
                <span x-text="isLoading ? 'Подключение...' : 'Включить'"></span>
            </button>

            <button type="button" x-on:click="remindLater()" :disabled="isLoading"
                class="inline-flex flex-1 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-70 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                Напомнить позже
            </button>
        </div>
    </div>
</div>