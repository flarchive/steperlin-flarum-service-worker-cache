<?php

/**
 * Service Worker Registration Middleware
 * Compatible with Flarum 2.0.0@beta
 * 
 * @package SteperLin\ServiceWorkerCache\Middleware
 * @author Steper Lin <steper.lin@icloud.com>
 * @license Apache-2.0
 * @version 1.1.0
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
     * Process Service Worker requests
     * 
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri()->getPath();

        // 仅在访问 /service-worker.js 路径时处理请求
        if ($uri === '/service-worker.js') {
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
        $serviceWorkerPath = __DIR__ . '/../../service-worker.js';
        
        $response = new Response();
        
        if (file_exists($serviceWorkerPath)) {
            $serviceWorkerContent = file_get_contents($serviceWorkerPath);
            $response->getBody()->write($serviceWorkerContent);
        } else {
            // 如果文件不存在，返回一个简单的默认 service worker
            $defaultServiceWorker = $this->getDefaultServiceWorker();
            $response->getBody()->write($defaultServiceWorker);
        }
        
        return $response
            ->withHeader('Content-Type', 'application/javascript')
            ->withHeader('Service-Worker-Allowed', '/')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->withHeader('X-Flarum-Version', '2.0.0@beta')
            ->withHeader('X-Extension-Version', '1.1.0');
    }
    
    /**
     * Get default Service Worker content
     * 
     * @return string
     */
    private function getDefaultServiceWorker(): string
    {
        return "
            // Default Service Worker for Flarum 2.0
            console.log('[SW] Flarum 2.0 Service Worker initialized');
            
            self.addEventListener('install', event => {
                console.log('[SW] Service Worker installed');
                self.skipWaiting();
            });
            
            self.addEventListener('activate', event => {
                console.log('[SW] Service Worker activated');
                return self.clients.claim();
            });
            
            self.addEventListener('fetch', event => {
                // 简单的网络优先策略
                if (event.request.method !== 'GET') return;
                
                event.respondWith(
                    fetch(event.request).catch(() => {
                        return caches.match(event.request);
                    })
                );
            });
        ";
    }
}
