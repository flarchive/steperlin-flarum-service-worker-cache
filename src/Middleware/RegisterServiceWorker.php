<?php

/**
 * Service Worker Registration Middleware with Auto-Deploy
 * Compatible with Flarum 2.0.0@beta
 * 
 * @package SteperLin\ServiceWorkerCache\Middleware
 * @author Steper Lin <steper.lin@icloud.com>
 * @license Apache-2.0
 * @version 1.2.0
 */

namespace SteperLin\ServiceWorkerCache\Middleware;

use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SteperLin\ServiceWorkerCache\Installer;

class RegisterServiceWorker implements MiddlewareInterface
{
    /**
     * 是否已尝试自动部署
     */
    private static bool $deploymentAttempted = false;

    /**
     * Process all requests and intercept Service Worker requests
     * 
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri()->getPath();

        // 检查是否是 Service Worker 请求
        if ($uri === '/service-worker.js' || $uri === '/forum/service-worker.js') {
            return $this->handleServiceWorkerRequest();
        }

        // 对于其他请求，继续处理
        return $handler->handle($request);
    }
    
    /**
     * Handle Service Worker file request with auto-deploy
     * 
     * @return ResponseInterface
     */
    private function handleServiceWorkerRequest(): ResponseInterface
    {
        // 尝试自动部署（只在第一次请求时）
        if (!self::$deploymentAttempted) {
            $this->attemptAutoDeployment();
            self::$deploymentAttempted = true;
        }
        
        // 首先检查 public 目录中是否已有文件
        $publicPath = getcwd() . '/public/service-worker.js';
        if (file_exists($publicPath) && is_readable($publicPath)) {
            return $this->serveFileFromPublic($publicPath);
        }
        
        // 如果 public 目录中没有，尝试从扩展目录提供
        return $this->serveFileFromExtension();
    }
    
    /**
     * 尝试自动部署 Service Worker
     * 
     * @return void
     */
    private function attemptAutoDeployment(): void
    {
        try {
            // 检查是否已部署
            $publicPath = getcwd() . '/public/service-worker.js';
            if (file_exists($publicPath)) {
                return; // 已部署，无需重复
            }
            
            // 尝试自动部署
            Installer::deployServiceWorker();
        } catch (\Exception $e) {
            // 自动部署失败，但不阻断响应
            error_log("[SW Middleware] Auto-deployment failed: " . $e->getMessage());
        }
    }
    
    /**
     * 从 public 目录提供文件
     * 
     * @param string $filePath 文件路径
     * @return ResponseInterface
     */
    private function serveFileFromPublic(string $filePath): ResponseInterface
    {
        $content = file_get_contents($filePath);
        $debugInfo = "\n// Service Worker served from public directory\n// File: {$filePath}\n// Timestamp: " . date('Y-m-d H:i:s') . "\n";
        $content = $debugInfo . $content;
        
        $response = new Response();
        $response->getBody()->write($content);
        
        return $response
            ->withHeader('Content-Type', 'application/javascript; charset=utf-8')
            ->withHeader('Service-Worker-Allowed', '/')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0')
            ->withHeader('X-Flarum-Version', '2.0.0@beta')
            ->withHeader('X-Extension-Version', '1.1.1')
            ->withHeader('X-SW-Source', 'public-directory');
    }
    
    /**
     * 从扩展目录提供文件
     * 
     * @return ResponseInterface
     */
    private function serveFileFromExtension(): ResponseInterface
    {
        // 尝试多个可能的文件位置
        $possiblePaths = [
            __DIR__ . '/../../service-worker.js',  // 扩展根目录
            getcwd() . '/vendor/steperlin/flarum-service-worker-cache/service-worker.js',
            getcwd() . '/extensions/flarum-service-worker-cache/service-worker.js',
            realpath(__DIR__ . '/../../') . '/service-worker.js'  // 解析绝对路径
        ];
        
        $serviceWorkerContent = null;
        $foundPath = null;
        
        // 尝试找到文件
        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_readable($path)) {
                $serviceWorkerContent = file_get_contents($path);
                $foundPath = $path;
                break;
            }
        }
        
        $response = new Response();
        
        if ($serviceWorkerContent) {
            // 添加调试信息
            $debugInfo = "\n// Service Worker loaded from extension directory\n// File: {$foundPath}\n// Timestamp: " . date('Y-m-d H:i:s') . "\n";
            $serviceWorkerContent = $debugInfo . $serviceWorkerContent;
            
            $response->getBody()->write($serviceWorkerContent);
            
            // 设置正确的响应头
            return $response
                ->withHeader('Content-Type', 'application/javascript; charset=utf-8')
                ->withHeader('Service-Worker-Allowed', '/')
                ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->withHeader('Pragma', 'no-cache')
                ->withHeader('Expires', '0')
                ->withHeader('X-Flarum-Version', '2.0.0@beta')
                ->withHeader('X-Extension-Version', '1.2.0')
                ->withHeader('X-SW-Source', 'extension-directory');
        }
        
        // 如果文件不存在，返回一个简单的默认 service worker
        $defaultServiceWorker = $this->getDefaultServiceWorker();
        $response->getBody()->write($defaultServiceWorker);
        
        return $response
            ->withHeader('Content-Type', 'application/javascript; charset=utf-8')
            ->withHeader('Service-Worker-Allowed', '/')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0')
            ->withHeader('X-Flarum-Version', '2.0.0@beta')
            ->withHeader('X-Extension-Version', '1.1.1')
            ->withHeader('X-SW-Source', 'fallback');
    }
    
    /**
     * Get default Service Worker content
     * 
     * @return string
     */
    private function getDefaultServiceWorker(): string
    {
        return "
// Enhanced Fallback Service Worker for Flarum 2.0 (v1.1.1)
// Auto-deployment mechanism active
console.log('[SW Enhanced Fallback] Flarum 2.0 Service Worker v1.1.1 with Auto-Deploy');
console.log('[SW Enhanced Fallback] This fallback includes auto-deployment features');

const ENHANCED_CACHE_NAME = 'flarum-enhanced-v1.1.1';

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
                    console.log(`[SW Enhanced Fallback] Deleting old cache: \${cacheName}`);
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
    
    // 只处理 GET 请求
    if (event.request.method !== 'GET') return;
    
    // 只处理本站请求
    if (url.origin !== self.location.origin) return;
    
    // 跳过管理面板
    if (url.pathname.startsWith('/admin')) return;
    
    console.log(`[SW Enhanced Fallback] Handling: \${url.pathname}`);
    
    // 智能缓存策略
    event.respondWith(
        caches.match(event.request).then(cachedResponse => {
            if (cachedResponse) {
                console.log(`[SW Enhanced Fallback] Cache hit: \${url.pathname}`);
                // 后台更新
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

// 消息处理
self.addEventListener('message', event => {
    console.log('[SW Enhanced Fallback] Received message:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        console.log('[SW Enhanced Fallback] Skipping waiting...');
        self.skipWaiting();
    }
});

console.log('[SW Enhanced Fallback] Enhanced Fallback Service Worker loaded with auto-deploy support');
        ";
    }
}