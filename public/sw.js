/*
 * RAGA service worker.
 *
 * KEBIJAKAN CACHE — sengaja konservatif karena RAGA menyimpan data kesehatan
 * privat (HRV, tidur, stress, body battery, recovery/readiness) dan rekaman GPS
 * yang menunjukkan lokasi rumah pengguna:
 *
 *  - Hanya ASET STATIS yang di-cache: /icons/*, hasil build Vite (/build/*),
 *    ikon, dan manifest. Semuanya identik untuk setiap pengguna.
 *  - HTML TIDAK PERNAH di-cache. Halaman RAGA dirender per pengguna; bila HTML
 *    tersimpan di cache, pengguna berikutnya di perangkat yang sama bisa melihat
 *    halaman milik pengguna sebelumnya. Navigasi selalu network-only, dengan
 *    fallback ke /offline.
 *  - Permintaan /api/* dan seluruh metode non-GET tidak pernah disentuh.
 *  - Halaman root "/" sengaja TIDAK di-precache agar tidak ada HTML yang
 *    tersimpan di perangkat.
 *
 * Versi cache diberi nama dan versi lama dibersihkan saat activate.
 */

const VERSION = 'raga-static-v1';
const STATIC_CACHE = `${VERSION}-static`;
const OFFLINE_URL = '/offline';

const PRECACHE_URLS = [
  OFFLINE_URL,
  '/manifest.webmanifest',
  '/icons/icon.svg',
  '/icons/icon-192x192.png',
  '/icons/icon-512x512.png',
  '/icons/icon-maskable-512x512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    (async () => {
      const cache = await caches.open(STATIC_CACHE);
      // Satu per satu supaya satu URL gagal tidak membatalkan seluruh instalasi.
      await Promise.all(
        PRECACHE_URLS.map((url) =>
          cache.add(new Request(url, { cache: 'reload' })).catch(() => undefined),
        ),
      );
      await self.skipWaiting();
    })(),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(
        keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key)),
      );
      await self.clients.claim();
    })(),
  );
});

self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

/** Aset statis yang aman disimpan di cache: namanya ber-hash atau milik bersama. */
function isStaticAsset(url) {
  return (
    url.pathname.startsWith('/icons/') ||
    url.pathname.startsWith('/build/') ||
    url.pathname === '/manifest.webmanifest'
  );
}

self.addEventListener('fetch', (event) => {
  const { request } = event;

  // Metode selain GET (POST rekaman, kudos, komentar) tidak pernah ditangani.
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  // Jangan pernah menyentuh API: responsnya spesifik per pengguna.
  if (url.pathname.startsWith('/api/')) {
    return;
  }

  if (isStaticAsset(url)) {
    event.respondWith(
      (async () => {
        const cache = await caches.open(STATIC_CACHE);
        const hit = await cache.match(request);
        if (hit) {
          return hit;
        }
        try {
          const response = await fetch(request);
          if (response.ok) {
            cache.put(request, response.clone());
          }
          return response;
        } catch {
          return hit ?? Response.error();
        }
      })(),
    );
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      (async () => {
        try {
          return await fetch(request);
        } catch {
          const cache = await caches.open(STATIC_CACHE);
          const offline = await cache.match(OFFLINE_URL);
          return (
            offline ??
            new Response('<h1>Tidak ada koneksi</h1>', {
              status: 503,
              headers: { 'Content-Type': 'text/html; charset=utf-8' },
            })
          );
        }
      })(),
    );
  }
});
