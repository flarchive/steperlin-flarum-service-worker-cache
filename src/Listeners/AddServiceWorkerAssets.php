<?php

namespace SteperLin\ServiceWorkerCache\Listeners;

use Flarum\Frontend\Document;
use Flarum\Frontend\Events\Rendering;

class AddServiceWorkerAssets
{
    public function handle(Rendering $event)
    {
        if ($event->isAdmin()) {
            return;
        }
        
        // 这里可以添加额外的前端资源，如果需要
    }
}