/**
 * Art in Heaven - Service Worker for Push Notifications
 */

self.addEventListener('install', function(event) {
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function(event) {
    if (!event.data) {
        return;
    }

    var data;
    try {
        data = event.data.json();
    } catch (e) {
        return;
    }

    var options = {
        body: data.body || '',
        icon: data.icon || '',
        tag: data.tag || 'aih-notification',
        renotify: true,
        data: {
            url: data.url || '/',
            art_piece_id: data.art_piece_id || null
        }
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'Art in Heaven', options)
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    var url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            // Focus existing tab if one matches this exact URL
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            // Navigate an existing same-origin tab if available
            for (var j = 0; j < clientList.length; j++) {
                var client = clientList[j];
                if ('navigate' in client) {
                    return client.navigate(url).then(function(c) { return c.focus(); });
                }
            }
            // Otherwise open a new tab
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});
