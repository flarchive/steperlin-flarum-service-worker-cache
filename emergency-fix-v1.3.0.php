<?php

/**
 * 紧急修复工具 v1.3.0
 * 
 * 解决插件启用/禁用卡住和页面加载超时问题
 * 
 * 使用方法：
 * cd /path/to/flarum/root
 * php vendor/steperlin/flarum-service-worker-cache/emergency-fix-v1.3.0.php
 */

declare(strict_types=1);

echo "🚨 Service Worker Cache 紧急修复工具 v1.3.0\n";
echo "=============================================\n\n";

class EmergencyFixer
{
    private string $flarumRoot;
    private array $issues = [];
    private array $fixes = [];
    
    public function __construct()
    {
        $this->flarumRoot = $this->detectFlarumRoot();
        echo "🎯 Flarum 根目录: {$this->flarumRoot}\n\n";
    }
    
    public function runEmergencyFix(): void
    {
        echo "🔍 开始紧急诊断和修复...\n\n";
        
        $this->step1_checkProcesses();
        $this->step2_clearServiceWorker();
        $this->step3_checkPermissions();
        $this->step4_clearCaches();
        $this->step5_testDeployment();
        
        $this->printSummary();
    }
    
    private function step1_checkProcesses(): void
    {
        echo "1️⃣ 检查运行中的进程\n";
        echo "===================\n";
        
        // 检查是否有卡住的 PHP 进程
        $processes = [];
        exec('ps aux | grep "[f]larum extension"', $processes);
        
        if (!empty($processes)) {
            echo "⚠️ 发现可能卡住的 Flarum 进程:\n";
            foreach ($processes as $process) {
                echo "   {$process}\n";
            }
            $this->issues[] = "Found stuck Flarum processes";
            
            echo "\n💡 建议手动终止这些进程:\n";
            echo "   pkill -f 'flarum extension'\n";
        } else {
            echo "✅ 没有发现卡住的进程\n";
        }
        echo "\n";
    }
    
    private function step2_clearServiceWorker(): void
    {
        echo "2️⃣ 清理 Service Worker 文件\n";
        echo "============================\n";
        
        $swFile = $this->flarumRoot . '/public/service-worker.js';
        
        if (file_exists($swFile)) {
            echo "📄 发现现有 Service Worker 文件\n";
            
            // 创建安全备份
            $backupFile = $swFile . '.emergency-backup.' . date('YmdHis');
            if (copy($swFile, $backupFile)) {
                echo "✅ 已备份到: {$backupFile}\n";
                
                // 删除现有文件
                if (unlink($swFile)) {
                    echo "✅ 已删除现有 Service Worker 文件\n";
                    $this->fixes[] = "Removed existing Service Worker file";
                } else {
                    echo "❌ 无法删除现有文件\n";
                    $this->issues[] = "Cannot delete existing Service Worker file";
                }
            } else {
                echo "❌ 无法创建备份\n";
                $this->issues[] = "Cannot backup existing Service Worker file";
            }
        } else {
            echo "ℹ️ 没有找到现有的 Service Worker 文件\n";
        }
        echo "\n";
    }
    
    private function step3_checkPermissions(): void
    {
        echo "3️⃣ 检查和修复权限\n";
        echo "==================\n";
        
        $publicDir = $this->flarumRoot . '/public';
        
        if (!is_dir($publicDir)) {
            echo "❌ Public 目录不存在: {$publicDir}\n";
            $this->issues[] = "Public directory does not exist";
            return;
        }
        
        $isWritable = is_writable($publicDir);
        echo "📁 Public 目录: {$publicDir}\n";
        echo "🔐 可写状态: " . ($isWritable ? "✅ 是" : "❌ 否") . "\n";
        
        if (!$isWritable) {
            echo "🔧 尝试修复权限...\n";
            if (chmod($publicDir, 0755)) {
                echo "✅ 权限修复成功\n";
                $this->fixes[] = "Fixed public directory permissions";
            } else {
                echo "❌ 权限修复失败\n";
                $this->issues[] = "Cannot fix public directory permissions";
                echo "💡 请手动执行: chmod 755 {$publicDir}\n";
            }
        }
        echo "\n";
    }
    
    private function step4_clearCaches(): void
    {
        echo "4️⃣ 清理缓存\n";
        echo "=============\n";
        
        // 清理 Flarum 缓存
        $cacheCommand = "cd '{$this->flarumRoot}' && php flarum cache:clear";
        echo "🧹 清理 Flarum 缓存...\n";
        
        $output = [];
        $returnCode = 0;
        exec($cacheCommand . ' 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "✅ Flarum 缓存清理成功\n";
            $this->fixes[] = "Cleared Flarum cache";
        } else {
            echo "❌ Flarum 缓存清理失败\n";
            echo "输出: " . implode("\n", $output) . "\n";
            $this->issues[] = "Failed to clear Flarum cache";
        }
        echo "\n";
    }
    
    private function step5_testDeployment(): void
    {
        echo "5️⃣ 测试简单部署\n";
        echo "================\n";
        
        // 查找源文件
        $sourceFile = $this->findSourceFile();
        if (!$sourceFile) {
            echo "❌ 无法找到 Service Worker 源文件\n";
            $this->issues[] = "Cannot find Service Worker source file";
            return;
        }
        
        echo "📄 找到源文件: {$sourceFile}\n";
        
        // 简单复制
        $targetFile = $this->flarumRoot . '/public/service-worker.js';
        if (copy($sourceFile, $targetFile)) {
            chmod($targetFile, 0644);
            echo "✅ 简单部署成功\n";
            echo "📏 文件大小: " . filesize($targetFile) . " bytes\n";
            $this->fixes[] = "Successfully deployed Service Worker";
        } else {
            echo "❌ 简单部署失败\n";
            $this->issues[] = "Failed to deploy Service Worker";
        }
        echo "\n";
    }
    
    private function findSourceFile(): ?string
    {
        $candidates = [
            __DIR__ . '/service-worker.js',
            $this->flarumRoot . '/vendor/steperlin/flarum-service-worker-cache/service-worker.js',
        ];
        
        foreach ($candidates as $file) {
            if (file_exists($file) && is_readable($file)) {
                $content = file_get_contents($file);
                if (strpos($content, 'Flarum Service Worker') !== false) {
                    return $file;
                }
            }
        }
        
        return null;
    }
    
    private function detectFlarumRoot(): string
    {
        $cwd = getcwd();
        
        // 检查当前目录
        if (file_exists($cwd . '/flarum') || file_exists($cwd . '/public/index.php')) {
            return $cwd;
        }
        
        // 如果在插件目录，向上查找
        if (strpos($cwd, 'vendor/steperlin/flarum-service-worker-cache') !== false) {
            $parts = explode('/', $cwd);
            $vendorIndex = array_search('vendor', $parts);
            if ($vendorIndex !== false) {
                $flarumRoot = '/' . implode('/', array_slice($parts, 1, $vendorIndex - 1));
                if (file_exists($flarumRoot . '/flarum')) {
                    return $flarumRoot;
                }
            }
        }
        
        return $cwd;
    }
    
    private function printSummary(): void
    {
        echo "📊 修复摘要\n";
        echo "===========\n";
        
        echo "✅ 成功修复: " . count($this->fixes) . " 项\n";
        if (!empty($this->fixes)) {
            foreach ($this->fixes as $fix) {
                echo "   • {$fix}\n";
            }
        }
        
        echo "\n❌ 发现问题: " . count($this->issues) . " 项\n";
        if (!empty($this->issues)) {
            foreach ($this->issues as $issue) {
                echo "   • {$issue}\n";
            }
        }
        
        echo "\n🎯 下一步建议\n";
        echo "=============\n";
        
        if (empty($this->issues)) {
            echo "✅ 所有问题已修复！现在可以尝试:\n";
            echo "   1. 重新启用插件: php flarum extension:enable steperlin-service-worker-cache\n";
            echo "   2. 访问网站测试功能\n";
        } else {
            echo "⚠️ 仍有问题需要手动处理:\n";
            echo "   1. 检查服务器权限设置\n";
            echo "   2. 确认 Flarum 安装完整性\n";
            echo "   3. 联系系统管理员\n";
        }
        
        echo "\n🚀 如果问题解决，建议升级到最新版本以获得修复:\n";
        echo "   composer update steperlin/flarum-service-worker-cache\n";
    }
}

// 运行紧急修复
try {
    $fixer = new EmergencyFixer();
    $fixer->runEmergencyFix();
    
    echo "\n🎉 紧急修复程序完成！\n";
} catch (Exception $e) {
    echo "\n💥 紧急修复失败: " . $e->getMessage() . "\n";
    echo "请联系技术支持或手动检查问题。\n";
}