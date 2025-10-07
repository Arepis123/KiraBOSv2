// KiraBOS Service Worker - Offline Support
const VERSION = '1.0.0';
const CACHE_NAME = `kirabos-cache-v${VERSION}`;
const OFFLINE_URL = 'offline.html';

// Environment detection
const isProduction = location.hostname !== 'localhost' && location.hostname !== '127.0.0.1';

// Files to cache for offline use
const STATIC_CACHE_URLS = [
    'cashier.php',
    'offline.html',
    'manifest.json',
    'https://cdn.tailwindcss.com',
    'https://unpkg.com/lucide@latest/dist/umd/lucide.js'
];

// Install event - cache static resources
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_CACHE_URLS).catch((err) => {
                if (!isProduction) {
                    console.error('Failed to cache some resources:', err);
                }
                // Continue even if some resources fail to cache
                return Promise.resolve();
            });
        }).then(() => {
            return self.skipWaiting();
        })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            return self.clients.claim();
        })
    );
});

// Fetch event - serve from cache when offline
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Handle navigation requests (HTML pages)
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    // If online, cache the response and return it
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseClone);
                    });
                    return response;
                })
                .catch(() => {
                    // If offline, try to serve from cache
                    return caches.match(request).then((response) => {
                        return response || caches.match(OFFLINE_URL);
                    });
                })
        );
        return;
    }

    // Handle POST requests (order submissions)
    if (request.method === 'POST') {
        event.respondWith(
            (async () => {
                // Clone the request before using it
                const requestClone = request.clone();

                try {
                    const response = await fetch(request);
                    return response;
                } catch (error) {
                    // If offline, queue the request for later sync
                    try {
                        await queueRequest(requestClone);
                        return new Response(
                            JSON.stringify({
                                success: true,
                                offline: true,
                                message: 'Order queued for sync when online'
                            }),
                            {
                                headers: { 'Content-Type': 'application/json' },
                                status: 200
                            }
                        );
                    } catch (queueError) {
                        console.error('Failed to queue request:', queueError);
                        return new Response(
                            JSON.stringify({
                                success: false,
                                message: 'Failed to process request offline'
                            }),
                            {
                                headers: { 'Content-Type': 'application/json' },
                                status: 500
                            }
                        );
                    }
                }
            })()
        );
        return;
    }

    // Handle other requests (CSS, JS, images)
    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(request).then((response) => {
                // Cache successful responses
                if (response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseClone);
                    });
                }
                return response;
            });
        })
    );
});

// Queue POST requests for background sync
async function queueRequest(request) {
    const formData = await request.formData();
    const data = {};
    for (const [key, value] of formData.entries()) {
        data[key] = value;
    }

    // Store in IndexedDB
    const db = await openDatabase();
    const tx = db.transaction('sync-queue', 'readwrite');
    const store = tx.objectStore('sync-queue');

    await store.add({
        url: request.url,
        method: request.method,
        headers: Object.fromEntries(request.headers.entries()),
        body: data,
        timestamp: Date.now()
    });

    // Register for background sync
    if ('sync' in self.registration) {
        await self.registration.sync.register('sync-orders');
    }
}

// Open IndexedDB for offline storage
function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('kirabos-offline', 1);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('sync-queue')) {
                db.createObjectStore('sync-queue', { keyPath: 'id', autoIncrement: true });
            }
        };
    });
}

// Background sync event - sync queued orders when online
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-orders') {
        event.waitUntil(syncQueuedOrders());
    }
});

// Sync all queued orders
async function syncQueuedOrders() {
    const db = await openDatabase();
    const tx = db.transaction('sync-queue', 'readonly');
    const store = tx.objectStore('sync-queue');

    // Get all requests from store
    const allRequests = await new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    const syncPromises = allRequests.map(async (queuedRequest) => {
        try {
            // Convert body data back to FormData
            const formData = new FormData();
            for (const [key, value] of Object.entries(queuedRequest.body)) {
                formData.append(key, value);
            }

            // Resend the request
            const response = await fetch(queuedRequest.url, {
                method: queuedRequest.method,
                headers: queuedRequest.headers,
                body: formData
            });

            if (response.ok) {
                // Remove from queue after successful sync
                const deleteTx = db.transaction('sync-queue', 'readwrite');
                const deleteStore = deleteTx.objectStore('sync-queue');
                await deleteStore.delete(queuedRequest.id);

                // Notify clients about successful sync
                self.clients.matchAll().then(clients => {
                    clients.forEach(client => {
                        client.postMessage({
                            type: 'SYNC_SUCCESS',
                            orderId: queuedRequest.body.order_number || 'unknown'
                        });
                    });
                });
            }
        } catch (error) {
            console.error('Failed to sync order:', error);
        }
    });

    await Promise.all(syncPromises);
}

// Message event - handle messages from clients
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
