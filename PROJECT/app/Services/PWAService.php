<?php
namespace App\Services;
use App\Models\PWASettings;

class PWAService
{
    public function generateManifest(PWASettings $settings): array
    {
        return [
            'name' => $settings->app_name,
            'short_name' => $settings->app_short_name,
            'description' => $settings->description,
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'theme_color' => $settings->theme_color ?? '#1976d2',
            'background_color' => $settings->background_color ?? '#ffffff',
            'icons' => $this->generateIcons($settings),
            'screenshots' => [
                ['src' => '/images/screenshot1.png', 'sizes' => '540x720', 'type' => 'image/png'],
                ['src' => '/images/screenshot2.png', 'sizes' => '540x720', 'type' => 'image/png']
            ],
            'categories' => ['business', 'productivity'],
            'shortcuts' => [
                ['name' => 'Create Booking', 'url' => '/bookings/create', 'icons' => []],
                ['name' => 'View Reports', 'url' => '/reports', 'icons' => []]
            ]
        ];
    }
    
    protected function generateIcons(PWASettings $settings): array
    {
        return [
            ['src' => $settings->icon_url ?? '/images/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => $settings->icon_url ?? '/images/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png']
        ];
    }
    
    public function getServiceWorkerCode(int $tenantId): string
    {
        return <<<'JS'
const CACHE_VERSION = 'qudrix-v1';
const CRITICAL_CACHE = ['/', '/index.html', '/css/app.css', '/js/app.js'];
const API_CACHE = 'qudrix-api-v1';
const IMAGE_CACHE = 'qudrix-images-v1';

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then(cache => cache.addAll(CRITICAL_CACHE))
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(names => 
            Promise.all(names.map(name => 
                name !== CACHE_VERSION && caches.delete(name)
            ))
        )
    );
});

self.addEventListener('fetch', event => {
    const {request} = event;
    const url = new URL(request.url);
    
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(networkFirst(request));
    } else if (request.destination === 'image') {
        event.respondWith(cacheImages(request));
    } else {
        event.respondWith(cacheFirst(request));
    }
});

async function networkFirst(request) {
    try {
        const response = await fetch(request);
        const cache = await caches.open(API_CACHE);
        cache.put(request, response.clone());
        return response;
    } catch {
        return caches.match(request);
    }
}

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;
    
    try {
        const response = await fetch(request);
        const cache = await caches.open(CACHE_VERSION);
        cache.put(request, response.clone());
        return response;
    } catch {
        return new Response('Offline', {status: 503});
    }
}

async function cacheImages(request) {
    const cached = await caches.match(request);
    if (cached) return cached;
    
    try {
        const response = await fetch(request);
        const cache = await caches.open(IMAGE_CACHE);
        cache.put(request, response.clone());
        return response;
    } catch {
        return new Response('Image not available', {status: 404});
    }
}

self.addEventListener('sync', event => {
    if (event.tag === 'sync-data') {
        event.waitUntil(syncPendingData());
    }
});

async function syncPendingData() {
    const db = await indexedDB.databases();
    // Sync queued changes back to server
}
JS;
    }
}
