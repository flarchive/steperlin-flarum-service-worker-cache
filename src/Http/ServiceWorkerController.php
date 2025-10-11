<?php

namespace SteperLin\ServiceWorkerCache\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ServiceWorkerController
{
    public function __construct(private readonly ServiceWorkerResponder $responder)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responder->respond($request);
    }
}
