<?php

/**
 * Service Worker Registration Middleware with Auto-Deploy
 * Compatible with Flarum 2.0.0@beta
 * 
 * @package SteperLin\ServiceWorkerCache\Middleware
 * @author Steper Lin <steper.lin@icloud.com>
 * @license Apache-2.0
 * @version 1.3.0
 */

namespace SteperLin\ServiceWorkerCache\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SteperLin\ServiceWorkerCache\Http\ServiceWorkerResponder;

class RegisterServiceWorker implements MiddlewareInterface
{
    private ServiceWorkerResponder $responder;

    public function __construct(ServiceWorkerResponder $responder)
    {
        $this->responder = $responder;
    }

    /**
     * Process all requests and intercept Service Worker requests
     * 
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->responder->shouldHandle($request)) {
            return $this->responder->respond($request);
        }

        // 对于其他请求，继续处理
        return $handler->handle($request);
    }
}