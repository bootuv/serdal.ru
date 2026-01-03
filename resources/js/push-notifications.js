/**
 * Serdal Push Notifications Client
 * Handles Service Worker registration and push subscription management
 */

class PushNotifications {
    constructor() {
        this.vapidPublicKey = null;
        this.swRegistration = null;
        this.isSubscribed = false;
    }

    /**
     * Initialize push notifications
     * @param {string} vapidPublicKey - The VAPID public key from server
     */
    async init(vapidPublicKey) {
        if (!this.isSupported()) {
            console.log('Push notifications are not supported');
            return false;
        }

        this.vapidPublicKey = vapidPublicKey;

        try {
            // Register service worker
            this.swRegistration = await navigator.serviceWorker.register('/sw.js');
            console.log('Service Worker registered');

            // Check current subscription status
            const subscription = await this.swRegistration.pushManager.getSubscription();
            this.isSubscribed = subscription !== null;

            return true;
        } catch (error) {
            console.error('Failed to register Service Worker:', error);
            return false;
        }
    }

    /**
     * Check if push notifications are supported
     */
    isSupported() {
        return 'serviceWorker' in navigator && 'PushManager' in window;
    }

    /**
     * Check if user has granted notification permission
     */
    hasPermission() {
        return Notification.permission === 'granted';
    }

    /**
     * Check if user is currently subscribed
     */
    async checkSubscription() {
        if (!this.swRegistration) return false;
        const subscription = await this.swRegistration.pushManager.getSubscription();
        this.isSubscribed = subscription !== null;
        return this.isSubscribed;
    }

    /**
     * Request permission and subscribe to push notifications
     */
    async subscribe() {
        if (!this.swRegistration || !this.vapidPublicKey) {
            console.error('Push notifications not initialized');
            return false;
        }

        // Request permission
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            console.log('Notification permission denied');
            return false;
        }

        try {
            // Subscribe to push
            const subscription = await this.swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
            });

            // Send to server
            await this.sendSubscriptionToServer(subscription);
            this.isSubscribed = true;

            console.log('Push subscription successful');
            return true;
        } catch (error) {
            console.error('Failed to subscribe to push:', error);
            return false;
        }
    }

    /**
     * Unsubscribe from push notifications
     */
    async unsubscribe() {
        if (!this.swRegistration) return false;

        try {
            const subscription = await this.swRegistration.pushManager.getSubscription();
            if (subscription) {
                // Remove from server
                await this.removeSubscriptionFromServer(subscription);
                // Unsubscribe locally
                await subscription.unsubscribe();
            }

            this.isSubscribed = false;
            console.log('Push unsubscription successful');
            return true;
        } catch (error) {
            console.error('Failed to unsubscribe from push:', error);
            return false;
        }
    }

    /**
     * Toggle subscription state
     */
    async toggle() {
        if (this.isSubscribed) {
            return await this.unsubscribe();
        } else {
            return await this.subscribe();
        }
    }

    /**
     * Send subscription to server
     */
    async sendSubscriptionToServer(subscription) {
        const response = await fetch('/push-subscription', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify(subscription.toJSON())
        });

        if (!response.ok) {
            throw new Error('Failed to save subscription on server');
        }

        return response.json();
    }

    /**
     * Remove subscription from server
     */
    async removeSubscriptionFromServer(subscription) {
        const response = await fetch('/push-subscription', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ endpoint: subscription.endpoint })
        });

        if (!response.ok) {
            throw new Error('Failed to remove subscription from server');
        }

        return response.json();
    }

    /**
     * Convert base64 VAPID key to Uint8Array
     */
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
}

// Export for use
window.PushNotifications = new PushNotifications();
