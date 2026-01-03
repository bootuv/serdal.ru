/**
 * Serdal Push Notification Service Worker
 * Handles background push events and notification clicks
 */

const CACHE_VERSION = 'v1';
const APP_ICON = '/images/logo-icon.png';

/**
 * Handle incoming push messages
 */
self.addEventListener('push', function (event) {
    if (!event.data) {
        console.log('Push event but no data');
        return;
    }

    let data;
    try {
        data = event.data.json();
    } catch (e) {
        data = {
            title: 'Serdal',
            body: event.data.text(),
        };
    }

    const options = {
        body: data.body || '',
        icon: data.icon || APP_ICON,
        badge: APP_ICON,
        tag: data.tag || 'serdal-' + Date.now(),
        renotify: true,
        requireInteraction: data.requireInteraction || false,
        data: {
            url: data.url || data.data?.url || '/',
            notificationId: data.id || null
        },
        actions: data.actions || []
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'Serdal', options)
    );
});

/**
 * Handle notification click - open the app or focus existing window
 */
self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const urlToOpen = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function (clientList) {
                // Try to focus an existing window with the same origin
                for (const client of clientList) {
                    if (client.url.includes(self.location.origin) && 'focus' in client) {
                        client.focus();
                        client.navigate(urlToOpen);
                        return;
                    }
                }
                // Open new window if none found
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});

/**
 * Handle notification close
 */
self.addEventListener('notificationclose', function (event) {
    console.log('Notification closed:', event.notification.tag);
});

/**
 * Service Worker activation - clean up old caches
 */
self.addEventListener('activate', function (event) {
    event.waitUntil(
        self.clients.claim()
    );
});
