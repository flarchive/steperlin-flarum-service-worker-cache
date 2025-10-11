<?php

/**
 * Service Worker Assets Listener
 * Compatible with Flarum 2.0.0@beta
 * 
 * @package SteperLin\ServiceWorkerCache\Listeners
 * @author Steper Lin <steper.lin@icloud.com>
 * @license Apache-2.0
 * @version 1.1.0
 */

namespace SteperLin\ServiceWorkerCache\Listeners;

use Flarum\Frontend\Document;
use Flarum\Frontend\Events\Rendering;

class AddServiceWorkerAssets
{
    /**
     * Handle the frontend rendering event
     * 
     * @param Rendering $event
     * @return void
     */
    public function handle(Rendering $event): void
    {
        // 跳过管理员界面
        if ($event->isAdmin()) {
            return;
        }
        
        // 这里可以添加额外的前端资源，如果需要
        // 例如：为Flarum 2.0添加额外的缓存配置或监控脚本
    }
}