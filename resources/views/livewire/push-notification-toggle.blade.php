<div x-data="{
        isSubscribed: @entangle('isSubscribed'),
        isLoading: false,
        isSupported: false,
        permissionDenied: false,

        async init() {
            this.isSupported = 'serviceWorker' in navigator && 'PushManager' in window;

            if (!this.isSupported) return;

            if (Notification.permission === 'denied') {
                this.permissionDenied = true;
                return;
            }

            // Initialize push notifications
            const vapidKey = '{{ $this->getVapidPublicKey() }}';
            if (vapidKey && window.PushNotifications) {
                await window.PushNotifications.init(vapidKey);
                this.isSubscribed = await window.PushNotifications.checkSubscription();
            }
        },

        async toggle() {
            if (!this.isSupported || this.permissionDenied || !window.PushNotifications) return;

            this.isLoading = true;

            try {
                const success = await window.PushNotifications.toggle();
                if (success) {
                    this.isSubscribed = !this.isSubscribed;
                    this.$wire.set('isSubscribed', this.isSubscribed);
                } else if (Notification.permission === 'denied') {
                    this.permissionDenied = true;
                }
            } catch (error) {
                console.error('Failed to toggle push subscription:', error);
            } finally {
                this.isLoading = false;
            }
        }
    }" x-init="init()">
    <button type="button" x-show="isSupported && !permissionDenied" x-on:click="toggle()" :disabled="isLoading"
        class="fi-icon-btn relative flex items-center justify-center rounded-lg outline-none transition duration-75 focus-visible:ring-2 disabled:pointer-events-none disabled:opacity-70 h-9 w-9 text-gray-400 hover:text-gray-500 focus-visible:ring-primary-600 dark:text-gray-500 dark:hover:text-gray-400 dark:focus-visible:ring-primary-500"
        :class="{ 'text-primary-600 dark:text-primary-400': isSubscribed }"
        :title="isSubscribed ? 'Отключить браузерные уведомления' : 'Включить браузерные уведомления'">
        {{-- Loading spinner --}}
        <template x-if="isLoading">
            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
            </svg>
        </template>

        {{-- Bell icon when subscribed --}}
        <template x-if="!isLoading && isSubscribed">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
            </svg>
        </template>

        {{-- Bell slash icon when not subscribed --}}
        <template x-if="!isLoading && !isSubscribed">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9.143 17.082a24.248 24.248 0 0 0 3.844.148m-3.844-.148a23.856 23.856 0 0 1-5.455-1.31 8.964 8.964 0 0 0 2.3-5.542m3.155 6.852a3 3 0 0 0 5.667 1.97m1.965-2.277L21 21m-4.225-4.225a23.81 23.81 0 0 0 3.536-1.003A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6.53 6.53m10.245 10.245L6.53 6.53M3 3l3.53 3.53" />
            </svg>
        </template>
    </button>

    {{-- Hidden indicator when permission is denied --}}
    <template x-if="permissionDenied">
        <span class="text-xs text-gray-400 dark:text-gray-500" title="Уведомления заблокированы в настройках браузера">
            <svg class="h-5 w-5 text-gray-300 dark:text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9.143 17.082a24.248 24.248 0 0 0 3.844.148m-3.844-.148a23.856 23.856 0 0 1-5.455-1.31 8.964 8.964 0 0 0 2.3-5.542m3.155 6.852a3 3 0 0 0 5.667 1.97m1.965-2.277L21 21m-4.225-4.225a23.81 23.81 0 0 0 3.536-1.003A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6.53 6.53m10.245 10.245L6.53 6.53M3 3l3.53 3.53" />
            </svg>
        </span>
    </template>
</div>