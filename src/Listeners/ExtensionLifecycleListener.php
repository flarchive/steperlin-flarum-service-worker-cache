<?php

/**
 * Extension Lifecycle Listener - 极简安全版本
 * 
 * 监听扩展启用/禁用事件，快速部署 Service Worker
 * 专注于性能和稳定性，避免阻塞操作
 * 
 * @package SteperLin\ServiceWorkerCache\Listeners
 * @author Steper Lin <steper.lin@icloud.com>
 * @license Apache-2.0
 * @version 1.3.1
 */

namespace SteperLin\ServiceWorkerCache\Listeners;

use Flarum\Extension\Event\Enabled;
use Flarum\Extension\Event\Disabled;
use Illuminate\Contracts\Events\Dispatcher;

class ExtensionLifecycleListener
{
    /**
     * 订阅扩展生命周期事件
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Enabled::class, [$this, 'onExtensionEnabled']);
        $events->listen(Disabled::class, [$this, 'onExtensionDisabled']);
    }
    
    /**
     * 扩展启用时快速部署
     */
    public function onExtensionEnabled(Enabled $event): void
    {
        if ($event->extension->getId() !== 'steperlin-service-worker-cache') {
            return;
        }
        
        // 后台异步执行，避免阻塞
        register_shutdown_function([$this, 'backgroundDeploy']);
    }
    
    /**
     * 扩展禁用时快速清理
     */
    public function onExtensionDisabled(Disabled $event): void
    {
        if ($event->extension->getId() !== 'steperlin-service-worker-cache') {
            return;
        }
        
        // 后台异步执行，避免阻塞
        register_shutdown_function([$this, 'backgroundCleanup']);
    }
    
    /**
     * 后台部署 - 非阻塞
     */
    public function backgroundDeploy(): void
    {
        try {
            // 快速路径检测
            $cwd = getcwd();
            if (!$cwd) return;
            
            $publicDir = $cwd . '/public';
            if (!is_dir($publicDir) || !is_writable($publicDir)) {
                return;
            }
            
            // 寻找源文件 - 仅检查最可能的位置
            $sourceFile = null;
            $paths = [
                $cwd . '/vendor/steperlin/flarum-service-worker-cache/service-worker.js',
                __DIR__ . '/../../service-worker.js'
            ];
            
            foreach ($paths as $path) {
                if (file_exists($path) && is_readable($path)) {
                    $sourceFile = $path;
                    break;
                }
            }
            
            if (!$sourceFile) return;
            
            // 快速复制
            $targetFile = $publicDir . '/service-worker.js';
            if (copy($sourceFile, $targetFile)) {
                @chmod($targetFile, 0644);
            }
            
        } catch (\Throwable $e) {
            // 静默处理错误，不影响主进程
        }
    }
    
    /**
     * 后台清理 - 非阻塞
     */
    public function backgroundCleanup(): void
    {
        try {
            $cwd = getcwd();
            if (!$cwd) return;
            
            $targetFile = $cwd . '/public/service-worker.js';
            if (file_exists($targetFile)) {
                @unlink($targetFile);
            }
            
        } catch (\Throwable $e) {
            // 静默处理错误，不影响主进程
        }
    }
}