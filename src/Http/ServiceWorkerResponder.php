<?php

namespace SteperLin\ServiceWorkerCache\Http;

use Laminas\Diactoros\Response as DiactorosResponse;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use SteperLin\ServiceWorkerCache\Installer;

class ServiceWorkerResponder
{
    private const EXTENSION_VERSION = '1.3.2';

    /**
     * 保证自动部署仅尝试一次，避免重复消耗资源
     */
    private static bool $deploymentAttempted = false;

    /**
     * 判断当前请求是否应由 Service Worker 响应器处理
     */
    public function shouldHandle(string $uri): bool
    {
        return $uri === '/service-worker.js' || $uri === '/forum/service-worker.js';
    }

    /**
     * 生成 Service Worker 响应
     */
    public function respond(string $uri): PsrResponseInterface
    {
        if (!self::$deploymentAttempted) {
            $this->attemptAutoDeployment();
            self::$deploymentAttempted = true;
        }

        $publicPath = getcwd() . '/public/service-worker.js';
        if ($publicPath && file_exists($publicPath) && is_readable($publicPath)) {
            return $this->serveFileFromPublic($publicPath);
        }

        return $this->serveFileFromExtension();
    }

    private function attemptAutoDeployment(): void
    {
        try {
            $publicPath = getcwd() . '/public/service-worker.js';
            if ($publicPath && file_exists($publicPath)) {
                return;
            }

            Installer::deployServiceWorker();
        } catch (\Throwable $e) {
            error_log('[SW Responder] Auto-deployment failed: ' . $e->getMessage());
        }
    }

    private function serveFileFromPublic(string $filePath): PsrResponseInterface
    {
        $content = file_get_contents($filePath) ?: '';
        $debugInfo = "\n// Service Worker served from public directory\n// File: {$filePath}\n// Timestamp: " . date('Y-m-d H:i:s') . "\n";
        $content = $debugInfo . $content;

    $response = new DiactorosResponse();
        $response->getBody()->write($content);

        return $this->withCommonHeaders($response, 'public-directory');
    }

    private function serveFileFromExtension(): PsrResponseInterface
    {
        $extensionRoot = dirname(__DIR__, 2);
        $possiblePaths = [
            $extensionRoot . '/service-worker.js',
            getcwd() . '/vendor/steperlin/flarum-service-worker-cache/service-worker.js',
            getcwd() . '/extensions/flarum-service-worker-cache/service-worker.js',
            getcwd() . '/extensions/steperlin-service-worker-cache/service-worker.js',
        ];

        $content = null;
        $foundPath = null;

        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path) && is_readable($path)) {
                $content = file_get_contents($path);
                $foundPath = $path;
                break;
            }
        }

    $response = new DiactorosResponse();

        if ($content !== null) {
            $debugInfo = "\n// Service Worker loaded from extension directory\n// File: {$foundPath}\n// Timestamp: " . date('Y-m-d H:i:s') . "\n";
            $response->getBody()->write($debugInfo . $content);

            return $this->withCommonHeaders($response, 'extension-directory');
        }

        $response->getBody()->write($this->getDefaultServiceWorker());

        return $this->withCommonHeaders($response, 'fallback');
    }

    private function withCommonHeaders(PsrResponseInterface $response, string $source): PsrResponseInterface
    {
        return $response
            ->withHeader('Content-Type', 'application/javascript; charset=utf-8')
            ->withHeader('Service-Worker-Allowed', '/')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0')
            ->withHeader('X-Flarum-Version', '2.0.0@beta')
            ->withHeader('X-Extension-Version', self::EXTENSION_VERSION)
            ->withHeader('X-SW-Source', $source);
    }

    private function getDefaultServiceWorker(): string
    {
        $template = <<<'SW'
// Enhanced Fallback Service Worker for Flarum 2.0 (v%VERSION%)
// Auto-deployment mechanism active
const VERSION = '%VERSION%';
console.log(`[SW Enhanced Fallback] Flarum 2.0 Service Worker v${VERSION} with Auto-Deploy`);
console.log('[SW Enhanced Fallback] This fallback includes auto-deployment features');

const ENHANCED_CACHE_NAME = `flarum-enhanced-v${VERSION}`;

self.addEventListener('install', event => {
    console.log('[SW Enhanced Fallback] Installing with auto-deploy support...');
    event.waitUntil(
        caches.open(ENHANCED_CACHE_NAME).then(() => {
            console.log('[SW Enhanced Fallback] Cache opened successfully');
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', event => {
    console.log('[SW Enhanced Fallback] Activating...');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            const oldCaches = cacheNames.filter(name => 
                name.startsWith('flarum-') && name !== ENHANCED_CACHE_NAME
            );
            
            return Promise.all(
                oldCaches.map(cacheName => {
                    console.log(`[SW Enhanced Fallback] Deleting old cache: ${cacheName}`);
                    return caches.delete(cacheName);
                })
            );
        }).then(() => {
            console.log('[SW Enhanced Fallback] Claiming clients');
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    
    if (event.request.method !== 'GET') return;
    if (url.origin !== self.location.origin) return;
    if (url.pathname.startsWith('/admin')) return;
    
    console.log(`[SW Enhanced Fallback] Handling: ${url.pathname}`);
    
    event.respondWith(
        caches.match(event.request).then(cachedResponse => {
            if (cachedResponse) {
                console.log(`[SW Enhanced Fallback] Cache hit: ${url.pathname}`);
                fetch(event.request).then(response => {
                    if (response.status === 200) {
                        caches.open(ENHANCED_CACHE_NAME).then(cache => {
                            cache.put(event.request, response.clone());
                        });
                    }
                }).catch(() => {});
                return cachedResponse;
            }
            
            return fetch(event.request).then(response => {
                if (response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(ENHANCED_CACHE_NAME).then(cache => {
                        cache.put(event.request, responseClone);
                    });
                }
                return response;
            }).catch(() => {
                return new Response('Content not available offline', {
                    status: 503,
                    headers: new Headers({ 'Content-Type': 'text/plain' })
                });
            });
        })
    );
});

self.addEventListener('message', event => {
    console.log('[SW Enhanced Fallback] Received message:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        console.log('[SW Enhanced Fallback] Skipping waiting...');
        self.skipWaiting();
    }
});

console.log('[SW Enhanced Fallback] Enhanced Fallback Service Worker loaded with auto-deploy support');
SW;

        return str_replace('%VERSION%', self::EXTENSION_VERSION, $template);
    }
}
