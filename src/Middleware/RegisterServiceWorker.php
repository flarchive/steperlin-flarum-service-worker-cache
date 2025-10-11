<?php

namespace SteperLin\ServiceWorkerCache\Middleware;

use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RegisterServiceWorker implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri()->getPath();

        // 仅在访问 /service-worker.js 路径时处理请求
        if ($uri === '/service-worker.js') {
            $serviceWorkerPath = __DIR__ . '/../../service-worker.js';
            
            $response = new Response();
            
            if (file_exists($serviceWorkerPath)) {
                $serviceWorkerContent = file_get_contents($serviceWorkerPath);
                $response->getBody()->write($serviceWorkerContent);
            } else {
                // 如果文件不存在，返回一个简单的默认 service worker
                $response->getBody()->write("
                    // Default Service Worker
                    self.addEventListener('install', event => {
                        console.log('Service Worker installed');
                        self.skipWaiting();
                    });
                    
                    self.addEventListener('activate', event => {
                        console.log('Service Worker activated');
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
                ");
            }
            
            return $response
                ->withHeader('Content-Type', 'application/javascript')
                ->withHeader('Service-Worker-Allowed', '/')
                ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        }

        // 对于其他请求，继续处理
        return $handler->handle($request);
    }
}
