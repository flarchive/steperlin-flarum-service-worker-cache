<?php

/**
 * Service Worker Cache Auto Installer
 * 
 * 自动化部署 Service Worker 文件到 public 目录
 * 支持多种部署策略：符号链接、文件复制、权限检查
 * 
 * @package SteperLin\ServiceWorkerCache
 * @author Steper Lin <steper.lin@icloud.com>
 * @license Apache-2.0
 * @version 1.3.4
 */

namespace SteperLin\ServiceWorkerCache;

use Exception;

class Installer
{
    /**
     * Service Worker 文件相对路径
     */
    private const SW_SOURCE_PATH = 'service-worker.js';
    
    /**
     * 目标部署路径
     */
    private const SW_TARGET_PATH = 'public/service-worker.js';
    
    /**
     * 日志前缀
     */
    private const LOG_PREFIX = '[SW Installer]';
    
    /**
     * 自动部署 Service Worker 文件
     * 
     * 此方法会在 composer install/update 后自动执行
     * 实现零配置的 Service Worker 部署
     * 
     * @return void
     */
    public static function deployServiceWorker(): void
    {
        try {
            $installer = new self();
            $installer->execute();
        } catch (Exception $e) {
            // 不阻断 composer 流程，只记录错误
            self::log("Deployment failed: " . $e->getMessage(), 'error');
            self::log("Please run manual deployment script later", 'warning');
        }
    }
    
    /**
     * 执行部署流程
     * 
     * @return void
     * @throws Exception
     */
    private function execute(): void
    {
    self::log("Starting Service Worker auto-deployment v1.3.4");
        
        // 1. 环境检查
        $this->validateEnvironment();
        
        // 2. 查找源文件
        $sourcePath = $this->findSourceFile();
        
        // 3. 准备目标目录
        $this->prepareTargetDirectory();
        
        // 4. 部署文件
        $this->deployFile($sourcePath);
        
        // 5. 验证部署
        $this->verifyDeployment();
        
        self::log("Service Worker deployment completed successfully");
    }
    
    /**
     * 验证环境是否适合部署
     * 
     * @return void
     * @throws Exception
     */
    private function validateEnvironment(): void
    {
        // 检查是否在 Flarum 项目根目录
        if (!file_exists('composer.json') || !file_exists('flarum')) {
            throw new Exception("Not in Flarum root directory");
        }
        
        // 检查 public 目录是否存在
        if (!is_dir('public')) {
            throw new Exception("Public directory not found");
        }
        
        // 检查 public 目录是否可写
        if (!is_writable('public')) {
            throw new Exception("Public directory is not writable");
        }
        
        self::log("Environment validation passed");
    }
    
    /**
     * 查找 Service Worker 源文件
     * 
     * 支持多种安装方式的路径检测：
     * - Packagist 安装 (vendor 目录)
     * - 本地开发安装 (extensions 目录)
     * - 自定义安装路径
     * 
     * @return string 源文件的绝对路径
     * @throws Exception
     */
    private function findSourceFile(): string
    {
        $possiblePaths = [
            // Packagist 标准安装路径
            'vendor/steperlin/flarum-service-worker-cache/' . self::SW_SOURCE_PATH,
            
            // 本地开发安装路径
            'extensions/flarum-service-worker-cache/' . self::SW_SOURCE_PATH,
            
            // 自定义扩展目录
            'extensions/steperlin-service-worker-cache/' . self::SW_SOURCE_PATH,
            
            // 备用路径
            '../' . self::SW_SOURCE_PATH,
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_readable($path)) {
                $realPath = realpath($path);
                self::log("Found source file: {$realPath}");
                return $realPath;
            }
        }
        
        throw new Exception("Service Worker source file not found");
    }
    
    /**
     * 准备目标目录
     * 
     * @return void
     * @throws Exception
     */
    private function prepareTargetDirectory(): void
    {
        // 如果目标文件已存在，备份或删除
        if (file_exists(self::SW_TARGET_PATH)) {
            if (is_link(self::SW_TARGET_PATH)) {
                // 如果是符号链接，直接删除
                unlink(self::SW_TARGET_PATH);
                self::log("Removed existing symlink");
            } else {
                // 如果是普通文件，创建备份
                $backupPath = self::SW_TARGET_PATH . '.backup.' . date('YmdHis');
                rename(self::SW_TARGET_PATH, $backupPath);
                self::log("Backed up existing file to: {$backupPath}");
            }
        }
    }
    
    /**
     * 部署文件到目标位置
     * 
     * 优先使用符号链接，失败时回退到文件复制
     * 
     * @param string $sourcePath 源文件路径
     * @return void
     * @throws Exception
     */
    private function deployFile(string $sourcePath): void
    {
        // 计算相对路径（用于符号链接）
        $relativePath = $this->calculateRelativePath('public', $sourcePath);
        
        // 尝试创建符号链接
        if ($this->createSymlink($relativePath, self::SW_TARGET_PATH)) {
            self::log("Symlink created successfully");
            return;
        }
        
        // 符号链接失败，尝试文件复制
        if ($this->copyFile($sourcePath, self::SW_TARGET_PATH)) {
            self::log("File copied successfully");
            return;
        }
        
        throw new Exception("Failed to deploy Service Worker file");
    }
    
    /**
     * 创建符号链接
     * 
     * @param string $target 目标路径
     * @param string $link 链接路径
     * @return bool
     */
    private function createSymlink(string $target, string $link): bool
    {
        try {
            return symlink($target, $link);
        } catch (Exception $e) {
            self::log("Symlink creation failed: " . $e->getMessage(), 'warning');
            return false;
        }
    }
    
    /**
     * 复制文件
     * 
     * @param string $source 源文件路径
     * @param string $target 目标文件路径
     * @return bool
     */
    private function copyFile(string $source, string $target): bool
    {
        try {
            if (copy($source, $target)) {
                chmod($target, 0644);
                return true;
            }
            return false;
        } catch (Exception $e) {
            self::log("File copy failed: " . $e->getMessage(), 'warning');
            return false;
        }
    }
    
    /**
     * 计算相对路径
     * 
     * @param string $from 起始目录
     * @param string $to 目标文件
     * @return string 相对路径
     */
    private function calculateRelativePath(string $from, string $to): string
    {
        $fromPath = explode('/', realpath($from));
        $toPath = explode('/', dirname($to));
        
        // 找到共同的路径前缀
        $commonLength = 0;
        $minLength = min(count($fromPath), count($toPath));
        
        for ($i = 0; $i < $minLength; $i++) {
            if ($fromPath[$i] === $toPath[$i]) {
                $commonLength++;
            } else {
                break;
            }
        }
        
        // 构建相对路径
        $relativeParts = [];
        
        // 添加 "../" 部分
        for ($i = $commonLength; $i < count($fromPath); $i++) {
            $relativeParts[] = '..';
        }
        
        // 添加目标路径部分
        for ($i = $commonLength; $i < count($toPath); $i++) {
            $relativeParts[] = $toPath[$i];
        }
        
        // 添加文件名
        $relativeParts[] = basename($to);
        
        return implode('/', $relativeParts);
    }
    
    /**
     * 验证部署结果
     * 
     * @return void
     * @throws Exception
     */
    private function verifyDeployment(): void
    {
        if (!file_exists(self::SW_TARGET_PATH)) {
            throw new Exception("Deployment verification failed: target file not found");
        }
        
        if (!is_readable(self::SW_TARGET_PATH)) {
            throw new Exception("Deployment verification failed: target file not readable");
        }
        
        $fileSize = filesize(self::SW_TARGET_PATH);
        if ($fileSize < 1000) {
            throw new Exception("Deployment verification failed: target file too small ({$fileSize} bytes)");
        }
        
        // 检查文件内容
        $content = file_get_contents(self::SW_TARGET_PATH);
        if (strpos($content, 'Flarum Service Worker') === false) {
            throw new Exception("Deployment verification failed: invalid file content");
        }
        
        self::log("Deployment verification passed (file size: {$fileSize} bytes)");
    }
    
    /**
     * 记录日志信息
     * 
     * @param string $message 日志消息
     * @param string $level 日志级别
     * @return void
     */
    private static function log(string $message, string $level = 'info'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $prefix = self::LOG_PREFIX;
        
        // 根据级别选择输出流
        $output = ($level === 'error') ? STDERR : STDOUT;
        
        // 格式化消息
        $formattedMessage = "[{$timestamp}] {$prefix} {$message}" . PHP_EOL;
        
        // 输出消息
        fwrite($output, $formattedMessage);
    }
    
    /**
     * 获取安装状态信息
     * 
     * @return array 状态信息
     */
    public static function getStatus(): array
    {
        $status = [
            'deployed' => false,
            'file_exists' => false,
            'is_symlink' => false,
            'file_size' => 0,
            'last_modified' => null,
            'deployment_method' => 'unknown',
            'errors' => []
        ];
        
        try {
            if (file_exists(self::SW_TARGET_PATH)) {
                $status['file_exists'] = true;
                $status['deployed'] = true;
                $status['is_symlink'] = is_link(self::SW_TARGET_PATH);
                $status['file_size'] = filesize(self::SW_TARGET_PATH);
                $status['last_modified'] = date('Y-m-d H:i:s', filemtime(self::SW_TARGET_PATH));
                $status['deployment_method'] = $status['is_symlink'] ? 'symlink' : 'copy';
            }
        } catch (Exception $e) {
            $status['errors'][] = $e->getMessage();
        }
        
        return $status;
    }
    
    /**
     * 手动触发重新部署
     * 
     * @return bool 部署是否成功
     */
    public static function redeploy(): bool
    {
        try {
            self::deployServiceWorker();
            return true;
        } catch (Exception $e) {
            self::log("Manual redeploy failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
}