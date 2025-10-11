<?php

/**
 * Service Worker Registration Middleware
 * Compatible with Flarum 2.0.0@beta
 * 
 * @package SteperLin\ServiceWorkerCache\Middleware
 * @author Steper Lin <steper.lin@icloud.com>
 * @license Apache-2.0
 * @version 1.1.1
 */

namespace SteperLin\ServiceWorkerCache\Middleware;

use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RegisterServiceWorker implements MiddlewareInterface
{
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
     * Handle Service Worker file request
     * 
     * @return ResponseInterface
     */
    private function handleServiceWorkerRequest(): ResponseInterface
    {
        // 尝试多个可能的文件位置
        $possiblePaths = [
            __DIR__ . '/../../service-worker.js',  // 扩展根目录
            getcwd() . '/service-worker.js',       // 当前工作目录
            realpath(__DIR__ . '/../../') . '/service-worker.js'  // 解析绝对路径
        ];
        
        $serviceWorkerContent = null;
        $foundPath = null;
        
        // 尝试找到文件
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $serviceWorkerContent = file_get_contents($path);
                $foundPath = $path;
                break;
            }
        }
        
        $response = new Response();
        
        if ($serviceWorkerContent) {
            // 添加调试信息
            $debugInfo = "\n// Service Worker loaded from: {$foundPath}\n// Timestamp: " . date('Y-m-d H:i:s') . "\n";
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
                ->withHeader('X-Extension-Version', '1.1.1')
                ->withHeader('X-SW-Source', 'middleware');
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
// Fallback Service Worker for Flarum 2.0 (v1.1.0)
// This is a minimal fallback when main service-worker.js is not found
console.log('[SW Fallback] Flarum 2.0 Fallback Service Worker initialized');
console.log('[SW Fallback] Extension Version: 1.1.0');
console.log('[SW Fallback] If you see this, the main service-worker.js file was not found');

const FALLBACK_CACHE_NAME = 'flarum-fallback-v1.1';

self.addEventListener('install', event => {
    console.log('[SW Fallback] Service Worker installed');
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    console.log('[SW Fallback] Service Worker activated');
    return self.clients.claim();
});

self.addEventListener('fetch', event => {
    // 简单的网络优先策略，带有基本缓存
    if (event.request.method !== 'GET') {
        return;
    }
    
    const url = new URL(event.request.url);
    
    // 只处理本站请求
    if (url.origin !== self.location.origin) {
        return;
    }
    
    // 不缓存管理面板和API请求
    if (url.pathname.startsWith('/admin') || url.pathname.startsWith('/api/')) {
        return;
    }
    
    event.respondWith(
        // 网络优先，缓存备用
        fetch(event.request)
            .then(response => {
                // 只缓存成功的响应
                if (response.status === 200 && response.type === 'basic') {
                    const responseClone = response.clone();
                    caches.open(FALLBACK_CACHE_NAME)
                        .then(cache => {
                            cache.put(event.request, responseClone);
                        });
                }
                return response;
            })
            .catch(() => {
                // 网络失败时尝试从缓存获取
                return caches.match(event.request);
            })
    );
});

// 消息处理
self.addEventListener('message', event => {
    console.log('[SW Fallback] Received message:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        console.log('[SW Fallback] Skipping waiting to activate immediately');
        self.skipWaiting();
    }
});

console.log('[SW Fallback] Fallback Service Worker script loaded successfully');
        ";
    }
}
