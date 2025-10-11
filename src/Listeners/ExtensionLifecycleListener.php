<?php

/**
 * Extension Lifecycle Listener
 * 
 * 监听扩展启用/禁用事件，自动处理 Service Worker 部署
 * 
 * @package SteperLin\ServiceWorkerCache\Listeners
 * @author Steper Lin <steper.lin@icloud.com>
 * @license Apache-2.0
 * @version 1.2.0
 */

namespace SteperLin\ServiceWorkerCache\Listeners;

use Flarum\Extension\Event\Enabled;
use Flarum\Extension\Event\Disabled;
use Illuminate\Contracts\Events\Dispatcher;
use SteperLin\ServiceWorkerCache\Installer;

class ExtensionLifecycleListener
{
    /**
     * 订阅扩展生命周期事件
     * 
     * @param Dispatcher $events 事件调度器
     * @return void
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Enabled::class, [$this, 'onExtensionEnabled']);
        $events->listen(Disabled::class, [$this, 'onExtensionDisabled']);
    }
    
    /**
     * 扩展启用时的处理
     * 
     * @param Enabled $event 启用事件
     * @return void
     */
    public function onExtensionEnabled(Enabled $event): void
    {
        // 检查是否是我们的扩展
        if ($event->extension->getId() === 'steperlin-service-worker-cache') {
            $this->deployServiceWorker();
        }
    }
    
    /**
     * 扩展禁用时的处理
     * 
     * @param Disabled $event 禁用事件
     * @return void
     */
    public function onExtensionDisabled(Disabled $event): void
    {
        // 检查是否是我们的扩展
        if ($event->extension->getId() === 'steperlin-service-worker-cache') {
            $this->cleanupServiceWorker();
        }
    }
    
    /**
     * 部署 Service Worker 文件
     * 
     * @return void
     */
    private function deployServiceWorker(): void
    {
        try {
            Installer::deployServiceWorker();
            $this->log("Service Worker deployed automatically on extension enable");
        } catch (\Exception $e) {
            $this->log("Auto-deployment failed: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * 清理 Service Worker 文件
     * 
     * @return void
     */
    private function cleanupServiceWorker(): void
    {
        $targetPath = 'public/service-worker.js';
        
        try {
            if (file_exists($targetPath)) {
                // 创建备份
                $backupPath = $targetPath . '.disabled.' . date('YmdHis');
                rename($targetPath, $backupPath);
                $this->log("Service Worker disabled and backed up to: {$backupPath}");
            }
        } catch (\Exception $e) {
            $this->log("Cleanup failed: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * 记录日志
     * 
     * @param string $message 日志消息
     * @param string $level 日志级别
     * @return void
     */
    private function log(string $message, string $level = 'info'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $prefix = '[SW Lifecycle]';
        
        $output = ($level === 'error') ? STDERR : STDOUT;
        $formattedMessage = "[{$timestamp}] {$prefix} {$message}" . PHP_EOL;
        
        fwrite($output, $formattedMessage);
    }
}