<?php

/**
 * Service Worker 强制部署修复工具 v1.2.2
 * 
 * 专门用于解决 Service Worker 404 问题的强制部署脚本
 * 绕过 ExtensionLifecycleListener，直接执行文件复制和验证
 * 
 * 使用方法：
 * php force-deploy-fix-v1.2.2.php
 */

declare(strict_types=1);

class ForceDeployFix
{
    private string $workingDir;
    private string $publicDir;
    private string $targetFile;
    private array $log = [];
    
    public function __construct()
    {
        $this->workingDir = getcwd();
        $this->publicDir = $this->workingDir . '/public';
        $this->targetFile = $this->publicDir . '/service-worker.js';
    }
    
    public function execute(): bool
    {
        $this->printHeader();
        
        try {
            $this->log("🔍 开始强制部署修复...");
            
            // 1. 环境预检
            $this->validateEnvironment();
            
            // 2. 查找源文件
            $sourceFile = $this->findBestSourceFile();
            
            // 3. 执行强制部署
            $this->performForceDeploy($sourceFile);
            
            // 4. 验证部署结果
            $this->validateDeployment();
            
            // 5. 设置权限
            $this->setCorrectPermissions();
            
            $this->log("✅ 强制部署完成！");
            $this->printSummary();
            
            return true;
            
        } catch (Exception $e) {
            $this->log("❌ 部署失败: " . $e->getMessage());
            $this->log("详细错误: " . $e->getTraceAsString());
            return false;
        }
    }
    
    private function printHeader(): void
    {
        echo "\n";
        echo "🚀 Service Worker 强制部署修复工具 v1.2.2\n";
        echo "=========================================\n";
        echo "目标: 解决 https://beiduofen.top/service-worker.js 404 错误\n";
        echo "时间: " . date('Y-m-d H:i:s') . "\n\n";
    }
    
    private function validateEnvironment(): void
    {
        $this->log("🌍 验证环境...");
        
        // 检查是否在正确的目录
        if (!file_exists($this->workingDir . '/flarum')) {
            throw new Exception("当前目录不是 Flarum 根目录: {$this->workingDir}");
        }
        
        // 检查 public 目录
        if (!is_dir($this->publicDir)) {
            throw new Exception("Public 目录不存在: {$this->publicDir}");
        }
        
        if (!is_writable($this->publicDir)) {
            throw new Exception("Public 目录不可写: {$this->publicDir}");
        }
        
        $this->log("✅ 环境验证通过");
    }
    
    private function findBestSourceFile(): string
    {
        $this->log("🔍 查找最佳源文件...");
        
        $candidates = [
            $this->workingDir . '/vendor/steperlin/flarum-service-worker-cache/service-worker.js',
            $this->workingDir . '/extensions/flarum-service-worker-cache/service-worker.js',
            $this->workingDir . '/extensions/steperlin-service-worker-cache/service-worker.js',
        ];
        
        $bestFile = null;
        $bestScore = 0;
        
        foreach ($candidates as $file) {
            if (!file_exists($file) || !is_readable($file)) {
                continue;
            }
            
            $score = $this->evaluateSourceFile($file);
            $this->log("📄 候选文件: $file (得分: $score)");
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestFile = $file;
            }
        }
        
        if (!$bestFile) {
            throw new Exception("未找到有效的源文件");
        }
        
        $this->log("🎯 选择最佳源文件: $bestFile");
        return $bestFile;
    }
    
    private function evaluateSourceFile(string $file): int
    {
        $content = file_get_contents($file);
        $size = strlen($content);
        $score = 0;
        
        // 基础检查
        if ($size < 1000) return 0; // 文件太小
        if ($size > 100000) return 0; // 文件太大
        
        // 内容检查
        $checks = [
            'Flarum Service Worker' => 30,
            'CACHE_NAME' => 20,
            "addEventListener('install'" => 15,
            "addEventListener('activate'" => 15,
            "addEventListener('fetch'" => 20,
        ];
        
        foreach ($checks as $pattern => $points) {
            if (strpos($content, $pattern) !== false) {
                $score += $points;
            }
        }
        
        // 版本检查
        if (preg_match('/v1\.2\.[01]/', $content)) {
            $score += 10;
        }
        
        return $score;
    }
    
    private function performForceDeploy(string $sourceFile): void
    {
        $this->log("🚀 执行强制部署...");
        
        // 备份现有文件
        if (file_exists($this->targetFile)) {
            $backupFile = $this->targetFile . '.backup.' . date('YmdHis');
            if (copy($this->targetFile, $backupFile)) {
                $this->log("📦 已备份现有文件: $backupFile");
            } else {
                $this->log("⚠️ 备份失败，继续部署...");
            }
        }
        
        // 执行文件复制
        $this->log("📋 复制文件: $sourceFile -> {$this->targetFile}");
        
        if (!copy($sourceFile, $this->targetFile)) {
            $lastError = error_get_last();
            throw new Exception("文件复制失败: " . ($lastError['message'] ?? '未知错误'));
        }
        
        $this->log("✅ 文件复制成功");
    }
    
    private function validateDeployment(): void
    {
        $this->log("🔍 验证部署结果...");
        
        // 检查文件是否存在
        if (!file_exists($this->targetFile)) {
            throw new Exception("目标文件不存在: {$this->targetFile}");
        }
        
        // 检查文件大小
        $size = filesize($this->targetFile);
        if ($size < 1000) {
            throw new Exception("目标文件太小: {$size} bytes");
        }
        
        // 检查文件内容
        $content = file_get_contents($this->targetFile);
        if (strpos($content, 'Flarum Service Worker') === false) {
            throw new Exception("目标文件内容无效");
        }
        
        $this->log("✅ 部署验证通过 (文件大小: {$size} bytes)");
        
        // 显示版本信息
        if (preg_match('/缓存版本\s+v([\d.]+)/', $content, $matches)) {
            $this->log("📦 部署版本: {$matches[1]}");
        }
    }
    
    private function setCorrectPermissions(): void
    {
        $this->log("🔐 设置文件权限...");
        
        if (!chmod($this->targetFile, 0644)) {
            $this->log("⚠️ 权限设置失败，但不影响功能");
        } else {
            $this->log("✅ 权限设置成功 (644)");
        }
    }
    
    private function printSummary(): void
    {
        echo "\n📊 部署摘要\n";
        echo "===========\n";
        echo "目标文件: {$this->targetFile}\n";
        
        if (file_exists($this->targetFile)) {
            $size = filesize($this->targetFile);
            $mtime = date('Y-m-d H:i:s', filemtime($this->targetFile));
            $perms = substr(sprintf('%o', fileperms($this->targetFile)), -3);
            
            echo "文件大小: {$size} bytes\n";
            echo "修改时间: {$mtime}\n";
            echo "文件权限: {$perms}\n";
        }
        
        echo "\n🧪 测试步骤\n";
        echo "===========\n";
        echo "1. 浏览器访问: https://beiduofen.top/service-worker.js\n";
        echo "2. 检查返回状态码应该是 200\n";
        echo "3. 查看浏览器控制台确认 Service Worker 注册成功\n";
        echo "4. 如果仍有问题，检查 Nginx/Apache 配置\n\n";
        
        echo "🔧 如果还有问题，请检查:\n";
        echo "- Web 服务器配置 (Nginx/Apache)\n";
        echo "- 文件路径映射\n";
        echo "- CDN 缓存设置\n";
        echo "- 防火墙规则\n\n";
    }
    
    private function log(string $message): void
    {
        $timestamp = date('H:i:s');
        $logMessage = "[{$timestamp}] {$message}";
        echo $logMessage . "\n";
        $this->log[] = $logMessage;
    }
}

// 执行强制部署修复
try {
    $fixer = new ForceDeployFix();
    $success = $fixer->execute();
    
    if ($success) {
        echo "🎉 部署修复完成！请测试 Service Worker 是否正常工作。\n";
        exit(0);
    } else {
        echo "❌ 部署修复失败，请查看上述错误信息。\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "💥 致命错误: " . $e->getMessage() . "\n";
    exit(1);
}