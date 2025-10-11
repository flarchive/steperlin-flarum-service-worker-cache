<?php

/**
 * Extension Lifecycle Listener with Enhanced Auto-Deploy
 * 
 * 监听扩展启用/禁用事件，自动处理 Service Worker 部署
 * 在扩展启用时强制复制文件到 public 目录，确保零配置部署
 * 
 * @package SteperLin\ServiceWorkerCache\Listeners
 * @author Steper Lin <steper.lin@icloud.com>
 * @license Apache-2.0
 * @version 1.2.1
 */

namespace SteperLin\ServiceWorkerCache\Listeners;

use Flarum\Extension\Event\Enabled;
use Flarum\Extension\Event\Disabled;
use Illuminate\Contracts\Events\Dispatcher;
use Exception;

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
     * 扩展启用时的处理 - 强制部署 Service Worker
     * 
     * @param Enabled $event 启用事件
     * @return void
     */
    public function onExtensionEnabled(Enabled $event): void
    {
        // 检查是否是我们的扩展
        if ($event->extension->getId() === 'steperlin-service-worker-cache') {
            $this->log('Extension enabled, starting forced deployment...');
            $this->forceDeploy();
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
            $this->log('Extension disabled, cleaning up...');
            $this->cleanupServiceWorker();
        }
    }
    
    /**
     * 强制部署 Service Worker 文件
     * 
     * 这是核心方法，确保在扩展启用时 100% 部署成功
     * 不依赖任何外部脚本，纯 PHP 实现
     * 
     * @return void
     */
    private function forceDeploy(): void
    {
        try {
            $this->log('Starting forced deployment process...');
            
            // 1. 确定工作目录
            $workingDir = $this->getWorkingDirectory();
            $this->log("Working directory: {$workingDir}");
            
            // 2. 查找源文件
            $sourceFile = $this->findSourceFile($workingDir);
            if (!$sourceFile) {
                throw new Exception('Service Worker source file not found');
            }
            $this->log("Source file found: {$sourceFile}");
            
            // 3. 确保 public 目录存在且可写
            $publicDir = $workingDir . '/public';
            if (!is_dir($publicDir)) {
                throw new Exception('Public directory not found');
            }
            if (!is_writable($publicDir)) {
                throw new Exception('Public directory is not writable');
            }
            
            // 4. 目标文件路径
            $targetFile = $publicDir . '/service-worker.js';
            
            // 5. 备份现有文件（如果存在）
            if (file_exists($targetFile)) {
                $backupFile = $targetFile . '.backup.' . date('YmdHis');
                if (rename($targetFile, $backupFile)) {
                    $this->log("Existing file backed up to: {$backupFile}");
                } else {
                    $this->log('Warning: Could not backup existing file');
                }
            }
            
            // 6. 执行文件复制（强制使用复制而非符号链接，确保兼容性）
            if (!copy($sourceFile, $targetFile)) {
                throw new Exception('Failed to copy Service Worker file');
            }
            
            // 7. 设置正确的文件权限
            if (!chmod($targetFile, 0644)) {
                $this->log('Warning: Could not set file permissions');
            }
            
            // 8. 验证部署结果
            if (!file_exists($targetFile)) {
                throw new Exception('Target file does not exist after copy');
            }
            
            $fileSize = filesize($targetFile);
            if ($fileSize < 1000) {
                throw new Exception("Target file is too small: {$fileSize} bytes");
            }
            
            // 9. 验证文件内容
            $content = file_get_contents($targetFile);
            if (strpos($content, 'Flarum Service Worker') === false) {
                throw new Exception('Target file content is invalid');
            }
            
            $this->log("Forced deployment completed successfully!");
            $this->log("File size: {$fileSize} bytes");
            $this->log("Target: {$targetFile}");
            
        } catch (Exception $e) {
            $this->log("Forced deployment failed: " . $e->getMessage(), 'error');
            // 不抛出异常，避免阻断扩展启用过程
        }
    }
    
    /**
     * 获取工作目录
     * 
     * @return string
     */
    private function getWorkingDirectory(): string
    {
        // 尝试多种方法确定工作目录
        $candidates = [
            getcwd(),
            dirname($_SERVER['SCRIPT_FILENAME'] ?? ''),
            realpath('.'),
        ];
        
        foreach ($candidates as $dir) {
            if ($dir && is_dir($dir) && file_exists($dir . '/flarum')) {
                return $dir;
            }
        }
        
        // 如果都不行，使用当前目录
        return getcwd() ?: '/tmp';
    }
    
    /**
     * 查找 Service Worker 源文件
     * 
     * @param string $workingDir 工作目录
     * @return string|null 源文件路径
     */
    private function findSourceFile(string $workingDir): ?string
    {
        $possiblePaths = [
            // Packagist 标准安装
            $workingDir . '/vendor/steperlin/flarum-service-worker-cache/service-worker.js',
            
            // 本地开发安装
            $workingDir . '/extensions/flarum-service-worker-cache/service-worker.js',
            
            // 自定义扩展目录
            $workingDir . '/extensions/steperlin-service-worker-cache/service-worker.js',
            
            // 相对路径（从当前类文件位置）
            __DIR__ . '/../../service-worker.js',
            
            // 绝对路径（解析后）
            realpath(__DIR__ . '/../../service-worker.js'),
        ];
        
        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path) && is_readable($path)) {
                // 验证文件内容
                $content = file_get_contents($path);
                if (strpos($content, 'Flarum Service Worker') !== false) {
                    return $path;
                }
            }
        }
        
        return null;
    }
    
    /**
     * 清理 Service Worker 文件
     * 
     * @return void
     */
    private function cleanupServiceWorker(): void
    {
        try {
            $workingDir = $this->getWorkingDirectory();
            $targetPath = $workingDir . '/public/service-worker.js';
            
            if (file_exists($targetPath)) {
                // 创建备份而不是直接删除
                $backupPath = $targetPath . '.disabled.' . date('YmdHis');
                if (rename($targetPath, $backupPath)) {
                    $this->log("Service Worker disabled and backed up to: {$backupPath}");
                } else {
                    $this->log('Warning: Could not backup Service Worker file');
                }
            } else {
                $this->log('No Service Worker file to clean up');
            }
        } catch (Exception $e) {
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
        
        // 构建日志消息
        $logMessage = "[{$timestamp}] {$prefix} {$message}";
        
        // 写入到日志文件（如果可能）
        try {
            $logFile = $this->getWorkingDirectory() . '/storage/logs/service-worker.log';
            $logDir = dirname($logFile);
            
            if (is_dir($logDir) && is_writable($logDir)) {
                file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        } catch (Exception $e) {
            // 忽略日志写入错误
        }
        
        // 输出到标准流（用于调试）
        $output = ($level === 'error') ? STDERR : STDOUT;
        if (is_resource($output)) {
            fwrite($output, $logMessage . PHP_EOL);
        }
        
        // 使用 error_log 作为备用
        error_log($logMessage);
    }
}