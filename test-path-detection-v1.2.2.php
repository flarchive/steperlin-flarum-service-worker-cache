<?php

/**
 * 路径检测测试工具 v1.2.2
 * 
 * 专门测试新的 Flarum 根目录检测逻辑
 * 可以在任何目录下运行，验证路径检测的准确性
 */

declare(strict_types=1);

echo "🔍 Flarum 根目录检测测试工具 v1.2.2\n";
echo "=====================================\n\n";

echo "📍 当前执行环境信息:\n";
echo "- 当前工作目录: " . getcwd() . "\n";
echo "- 脚本文件路径: " . __FILE__ . "\n";
echo "- PHP 版本: " . phpversion() . "\n";
echo "- 执行时间: " . date('Y-m-d H:i:s') . "\n\n";

/**
 * 模拟 ExtensionLifecycleListener 的路径检测逻辑
 */
class PathDetectionTester
{
    private array $logs = [];
    
    public function testPathDetection(): void
    {
        echo "🚀 开始测试路径检测逻辑...\n\n";
        
        $detectedRoot = $this->getWorkingDirectory();
        
        echo "📋 检测结果摘要:\n";
        echo "================\n";
        echo "检测到的 Flarum 根目录: {$detectedRoot}\n";
        echo "是否为有效的 Flarum 根目录: " . ($this->isFlarumRoot($detectedRoot) ? "✅ 是" : "❌ 否") . "\n\n";
        
        echo "📝 详细检测日志:\n";
        echo "================\n";
        foreach ($this->logs as $log) {
            echo $log . "\n";
        }
        
        $this->testTargetPaths($detectedRoot);
    }
    
    private function getWorkingDirectory(): string
    {
        $this->log('Starting Flarum root directory detection...');
        
        // 方法1: 从当前类文件位置向上查找 Flarum 根目录
        // 当前文件: vendor/steperlin/flarum-service-worker-cache/test-path-detection-v1.2.2.php
        // Flarum 根目录: ../../../ (向上3级)
        $currentFile = __FILE__;
        $extensionRoot = dirname($currentFile); // 回到扩展根目录
        $flarumRoot = dirname(dirname(dirname($extensionRoot))); // 向上3级到 Flarum 根目录
        
        $this->log("Method 1 - From class file location: {$flarumRoot}");
        if ($this->isFlarumRoot($flarumRoot)) {
            $this->log("✅ Found Flarum root via class file location: {$flarumRoot}");
            return $flarumRoot;
        }
        
        // 方法2: 从当前工作目录检测
        $cwd = getcwd();
        $this->log("Method 2 - Current working directory: {$cwd}");
        if ($cwd && $this->isFlarumRoot($cwd)) {
            $this->log("✅ Found Flarum root via current working directory: {$cwd}");
            return $cwd;
        }
        
        // 方法3: 从当前工作目录向上查找
        if ($cwd) {
            $pathParts = explode('/', trim($cwd, '/'));
            for ($i = count($pathParts); $i >= 1; $i--) {
                $testPath = '/' . implode('/', array_slice($pathParts, 0, $i));
                $this->log("Method 3 - Testing path: {$testPath}");
                if ($this->isFlarumRoot($testPath)) {
                    $this->log("✅ Found Flarum root via upward search: {$testPath}");
                    return $testPath;
                }
            }
        }
        
        // 方法4: 通过环境变量或脚本路径
        $scriptPath = $_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? '';
        if ($scriptPath) {
            $scriptDir = dirname($scriptPath);
            $this->log("Method 4 - From script path: {$scriptDir}");
            if ($this->isFlarumRoot($scriptDir)) {
                $this->log("✅ Found Flarum root via script path: {$scriptDir}");
                return $scriptDir;
            }
        }
        
        // 方法5: 特殊情况处理 - 如果在插件目录内运行诊断工具
        if ($cwd && strpos($cwd, 'vendor/steperlin/flarum-service-worker-cache') !== false) {
            // 从插件目录计算 Flarum 根目录
            $pluginPath = $cwd;
            $vendorPath = dirname(dirname($pluginPath)); // 回到 vendor 目录
            $flarumRoot = dirname($vendorPath); // 向上一级到 Flarum 根目录
            
            $this->log("Method 5 - From plugin directory: {$pluginPath} -> {$flarumRoot}");
            if ($this->isFlarumRoot($flarumRoot)) {
                $this->log("✅ Found Flarum root via plugin directory calculation: {$flarumRoot}");
                return $flarumRoot;
            }
        }
        
        // 如果所有方法都失败，返回当前目录作为后备
        $fallback = $cwd ?: '/tmp';
        $this->log("⚠️ Could not detect Flarum root, using fallback: {$fallback}");
        return $fallback;
    }
    
    private function isFlarumRoot(string $path): bool
    {
        if (!$path || !is_dir($path)) {
            return false;
        }
        
        // 检查 Flarum 特征文件
        $flarumIndicators = [
            $path . '/flarum',           // Flarum 可执行文件
            $path . '/public/index.php', // 入口文件
            $path . '/vendor/flarum/core', // Flarum 核心
            $path . '/extend.php',       // 可能存在的扩展配置
        ];
        
        foreach ($flarumIndicators as $indicator) {
            if (file_exists($indicator)) {
                $this->log("Found Flarum indicator: {$indicator}");
                return true;
            }
        }
        
        return false;
    }
    
    private function testTargetPaths(string $flarumRoot): void
    {
        echo "\n🎯 测试目标路径:\n";
        echo "================\n";
        
        $publicDir = $flarumRoot . '/public';
        $targetFile = $publicDir . '/service-worker.js';
        
        echo "Public 目录: {$publicDir}\n";
        echo "- 存在: " . (is_dir($publicDir) ? "✅ 是" : "❌ 否") . "\n";
        echo "- 可写: " . (is_writable($publicDir) ? "✅ 是" : "❌ 否") . "\n\n";
        
        echo "目标文件: {$targetFile}\n";
        echo "- 存在: " . (file_exists($targetFile) ? "✅ 是" : "❌ 否") . "\n";
        if (file_exists($targetFile)) {
            echo "- 大小: " . filesize($targetFile) . " bytes\n";
            echo "- 可读: " . (is_readable($targetFile) ? "✅ 是" : "❌ 否") . "\n";
        }
        
        // 测试源文件查找
        echo "\n🔍 测试源文件查找:\n";
        echo "==================\n";
        $this->testSourceFileDetection($flarumRoot);
    }
    
    private function testSourceFileDetection(string $flarumRoot): void
    {
        $possiblePaths = [
            // 最常见：Composer 标准安装路径
            $flarumRoot . '/vendor/steperlin/flarum-service-worker-cache/service-worker.js',
            
            // 本地开发安装
            $flarumRoot . '/extensions/flarum-service-worker-cache/service-worker.js',
            $flarumRoot . '/extensions/steperlin-service-worker-cache/service-worker.js',
            
            // 相对路径（从当前类文件位置）
            __DIR__ . '/service-worker.js',
            
            // 绝对路径（解析后）
            realpath(__DIR__ . '/service-worker.js'),
            
            // 如果在插件目录内运行，直接查找
            getcwd() . '/service-worker.js',
        ];
        
        $foundFiles = [];
        
        foreach ($possiblePaths as $index => $path) {
            echo "路径 #{$index}: {$path}\n";
            
            if (!$path) {
                echo "  ⚠️ 路径为空\n";
                continue;
            }
            
            if (!file_exists($path)) {
                echo "  ❌ 文件不存在\n";
                continue;
            }
            
            if (!is_readable($path)) {
                echo "  ❌ 文件不可读\n";
                continue;
            }
            
            // 验证文件内容
            $content = file_get_contents($path);
            if (!$content) {
                echo "  ❌ 文件为空或无法读取\n";
                continue;
            }
            
            // 检查关键标识符
            if (strpos($content, 'Flarum Service Worker') === false) {
                echo "  ❌ 文件不包含 'Flarum Service Worker' 标识\n";
                continue;
            }
            
            // 额外的内容验证
            $hasRequiredContent = (
                strpos($content, 'CACHE_NAME') !== false &&
                strpos($content, "addEventListener('install'") !== false &&
                strpos($content, "addEventListener('fetch'") !== false
            );
            
            if (!$hasRequiredContent) {
                echo "  ❌ 文件缺少必需的 Service Worker 内容\n";
                continue;
            }
            
            $fileSize = strlen($content);
            echo "  ✅ 有效的 Service Worker 文件! 大小: {$fileSize} bytes\n";
            
            // 检查版本信息
            if (preg_match('/缓存版本\\s+v([\\d.]+)/', $content, $matches)) {
                echo "  📦 版本: {$matches[1]}\n";
            }
            
            $foundFiles[] = $path;
        }
        
        echo "\n📊 源文件查找结果:\n";
        echo "找到 " . count($foundFiles) . " 个有效的源文件\n";
        if (!empty($foundFiles)) {
            echo "推荐使用: " . $foundFiles[0] . "\n";
        }
    }
    
    private function log(string $message): void
    {
        $this->logs[] = $message;
    }
}

// 运行测试
try {
    $tester = new PathDetectionTester();
    $tester->testPathDetection();
    
    echo "\n🎉 路径检测测试完成!\n";
} catch (Exception $e) {
    echo "\n❌ 测试过程出错: " . $e->getMessage() . "\n";
    echo "错误详情: " . $e->getTraceAsString() . "\n";
}