<?php

/**
 * Service Worker 部署诊断工具 v1.3.0
 * 
 * 专用于诊断和修复 Service Worker 文件部署问题
 * 提供详细的环境检查、路径分析和自动修复功能
 * 
 * 使用方法：
 * php diagnose-deployment-v1.3.0.php
 */

declare(strict_types=1);

class ServiceWorkerDiagnostic
{
    private array $results = [];
    private string $workingDir;
    private string $publicDir;
    private string $targetFile;
    
    public function __construct()
    {
        $this->workingDir = getcwd();
        $this->publicDir = $this->workingDir . '/public';
        $this->targetFile = $this->publicDir . '/service-worker.js';
    }
    
    public function runFullDiagnostic(): void
    {
        $this->printHeader();
        
        $this->checkEnvironment();
        $this->checkDirectories();
        $this->findSourceFiles();
        $this->checkTargetFile();
        $this->testExtensionStatus();
        $this->checkPermissions();
        $this->validateContent();
        
        $this->printSummary();
        $this->suggestSolutions();
    }
    
    private function printHeader(): void
    {
        echo "\n";
        echo "🔧 Service Worker 部署诊断工具 v1.3.0\n";
        echo "========================================\n";
        echo "诊断目标: {$this->targetFile}\n";
        echo "工作目录: {$this->workingDir}\n";
        echo "当前时间: " . date('Y-m-d H:i:s') . "\n\n";
    }
    
    private function checkEnvironment(): void
    {
        echo "🌍 环境检查\n";
        echo "-----------\n";
        
        // PHP 版本
        $phpVersion = phpversion();
        $this->log("PHP 版本", $phpVersion, version_compare($phpVersion, '8.1.0', '>='));
        
        // 检查是否在 Flarum 根目录
        $isFlarumRoot = file_exists($this->workingDir . '/flarum') && 
                       file_exists($this->workingDir . '/composer.json');
        $this->log("Flarum 根目录", $isFlarumRoot ? "是" : "否", $isFlarumRoot);
        
        // 检查扩展是否安装
        $extensionPath = $this->workingDir . '/vendor/steperlin/flarum-service-worker-cache';
        $isInstalled = is_dir($extensionPath);
        $this->log("扩展已安装", $isInstalled ? "是" : "否", $isInstalled);
        
        if ($isInstalled) {
            $composerJson = $extensionPath . '/composer.json';
            if (file_exists($composerJson)) {
                $content = json_decode(file_get_contents($composerJson), true);
                $version = $content['extra']['version'] ?? $content['version'] ?? '未知';
                $this->log("扩展版本", $version, true);
            }
        }
        
        echo "\n";
    }
    
    private function checkDirectories(): void
    {
        echo "📁 目录结构检查\n";
        echo "---------------\n";
        
        $directories = [
            'public' => $this->publicDir,
            'vendor' => $this->workingDir . '/vendor',
            'storage' => $this->workingDir . '/storage',
            'storage/logs' => $this->workingDir . '/storage/logs'
        ];
        
        foreach ($directories as $name => $path) {
            $exists = is_dir($path);
            $writable = $exists ? is_writable($path) : false;
            $status = $exists ? ($writable ? "存在且可写" : "存在但不可写") : "不存在";
            $this->log($name . " 目录", $status, $exists && $writable);
        }
        
        echo "\n";
    }
    
    private function findSourceFiles(): void
    {
        echo "🔍 查找源文件\n";
        echo "-------------\n";
        
        $possiblePaths = [
            'Vendor 标准路径' => $this->workingDir . '/vendor/steperlin/flarum-service-worker-cache/service-worker.js',
            'Extensions 目录' => $this->workingDir . '/extensions/flarum-service-worker-cache/service-worker.js',
            'Extensions 目录 2' => $this->workingDir . '/extensions/steperlin-service-worker-cache/service-worker.js',
        ];
        
        $foundFiles = [];
        
        foreach ($possiblePaths as $name => $path) {
            if (file_exists($path)) {
                $size = filesize($path);
                $readable = is_readable($path);
                $this->log($name, "找到 ({$size} bytes)", $readable);
                
                if ($readable) {
                    $content = file_get_contents($path);
                    $hasMarker = strpos($content, 'Flarum Service Worker') !== false;
                    $this->log("  - 内容验证", $hasMarker ? "有效" : "无效", $hasMarker);
                    
                    if ($hasMarker) {
                        $foundFiles[] = ['name' => $name, 'path' => $path, 'size' => $size];
                    }
                }
            } else {
                $this->log($name, "未找到", false);
            }
        }
        
        $this->results['source_files'] = $foundFiles;
        echo "\n";
    }
    
    private function checkTargetFile(): void
    {
        echo "🎯 目标文件检查\n";
        echo "---------------\n";
        
        if (file_exists($this->targetFile)) {
            $size = filesize($this->targetFile);
            $mtime = filemtime($this->targetFile);
            $readable = is_readable($this->targetFile);
            
            $this->log("目标文件存在", "是 ({$size} bytes)", true);
            $this->log("最后修改时间", date('Y-m-d H:i:s', $mtime), true);
            $this->log("文件可读", $readable ? "是" : "否", $readable);
            
            if ($readable) {
                $content = file_get_contents($this->targetFile);
                $hasMarker = strpos($content, 'Flarum Service Worker') !== false;
                $this->log("内容有效性", $hasMarker ? "有效" : "无效", $hasMarker);
                
                // 检查版本信息
                if (preg_match('/缓存版本\s+v([\d.]+)/', $content, $matches)) {
                    $this->log("文件版本", $matches[1], true);
                }
            }
            
            $this->results['target_exists'] = true;
        } else {
            $this->log("目标文件存在", "否", false);
            $this->results['target_exists'] = false;
        }
        
        echo "\n";
    }
    
    private function testExtensionStatus(): void
    {
        echo "🔌 扩展状态检查\n";
        echo "---------------\n";
        
        // 检查 extend.php
        $extendPath = $this->workingDir . '/vendor/steperlin/flarum-service-worker-cache/extend.php';
        if (file_exists($extendPath)) {
            $this->log("extend.php", "存在", true);
            
            // 尝试加载并检查配置
            try {
                $content = file_get_contents($extendPath);
                $hasListener = strpos($content, 'ExtensionLifecycleListener') !== false;
                $this->log("生命周期监听器", $hasListener ? "已配置" : "未配置", $hasListener);
            } catch (Exception $e) {
                $this->log("配置检查", "失败: " . $e->getMessage(), false);
            }
        } else {
            $this->log("extend.php", "不存在", false);
        }
        
        // 检查监听器类
        $listenerPath = $this->workingDir . '/vendor/steperlin/flarum-service-worker-cache/src/Listeners/ExtensionLifecycleListener.php';
        if (file_exists($listenerPath)) {
            $this->log("监听器类文件", "存在", true);
            
            // 检查语法
            $syntaxCheck = shell_exec("php -l '$listenerPath' 2>&1");
            $syntaxOk = strpos($syntaxCheck, 'No syntax errors') !== false;
            $this->log("监听器语法", $syntaxOk ? "正确" : "错误", $syntaxOk);
        } else {
            $this->log("监听器类文件", "不存在", false);
        }
        
        echo "\n";
    }
    
    private function checkPermissions(): void
    {
        echo "🔐 权限检查\n";
        echo "-----------\n";
        
        // 检查 public 目录权限
        if (is_dir($this->publicDir)) {
            $perms = fileperms($this->publicDir);
            $permStr = substr(sprintf('%o', $perms), -4);
            $writable = is_writable($this->publicDir);
            
            $this->log("Public 目录权限", $permStr, $writable);
            $this->log("Public 目录可写", $writable ? "是" : "否", $writable);
        }
        
        // 检查目标文件权限
        if (file_exists($this->targetFile)) {
            $perms = fileperms($this->targetFile);
            $permStr = substr(sprintf('%o', $perms), -4);
            $readable = is_readable($this->targetFile);
            
            $this->log("目标文件权限", $permStr, $readable);
            $this->log("目标文件可读", $readable ? "是" : "否", $readable);
        }
        
        echo "\n";
    }
    
    private function validateContent(): void
    {
        echo "📄 内容验证\n";
        echo "-----------\n";
        
        if (!empty($this->results['source_files'])) {
            $sourceFile = $this->results['source_files'][0]['path'];
            $sourceContent = file_get_contents($sourceFile);
            
            // 检查关键标识
            $markers = [
                'Flarum Service Worker' => strpos($sourceContent, 'Flarum Service Worker') !== false,
                'CACHE_NAME' => strpos($sourceContent, 'CACHE_NAME') !== false,
                'addEventListener' => strpos($sourceContent, 'addEventListener') !== false,
                'install' => strpos($sourceContent, "addEventListener('install'") !== false,
                'fetch' => strpos($sourceContent, "addEventListener('fetch'") !== false,
            ];
            
            foreach ($markers as $marker => $found) {
                $this->log("标识: $marker", $found ? "存在" : "缺失", $found);
            }
            
            // 检查版本信息
            if (preg_match('/缓存版本\s+v([\d.]+)/', $sourceContent, $matches)) {
                $this->log("源文件版本", $matches[1], true);
            }
        }
        
        echo "\n";
    }
    
    private function printSummary(): void
    {
        echo "📊 诊断摘要\n";
        echo "-----------\n";
        
        $issues = [];
        $successes = [];
        
        foreach ($this->results as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $successes[] = $key;
                } else {
                    $issues[] = $key;
                }
            }
        }
        
        echo "✅ 正常项目数: " . count($successes) . "\n";
        echo "❌ 问题项目数: " . count($issues) . "\n";
        
        if (!empty($issues)) {
            echo "\n🚨 发现的问题:\n";
            foreach ($issues as $issue) {
                echo "  - $issue\n";
            }
        }
        
        echo "\n";
    }
    
    private function suggestSolutions(): void
    {
        echo "💡 解决方案建议\n";
        echo "---------------\n";
        
        if (!$this->results['target_exists']) {
            echo "🔧 问题: Service Worker 文件不存在于 public 目录\n";
            echo "解决方案:\n";
            echo "1. 手动强制部署:\n";
            echo "   php -r \"require 'vendor/autoload.php'; \\\n";
            echo "   \$listener = new SteperLin\\ServiceWorkerCache\\Listeners\\ExtensionLifecycleListener(); \\\n";
            echo "   \$reflection = new ReflectionClass(\$listener); \\\n";
            echo "   \$method = \$reflection->getMethod('forceDeploy'); \\\n";
            echo "   \$method->setAccessible(true); \\\n";
            echo "   \$method->invoke(\$listener);\"\n\n";
            
            echo "2. 重新启用扩展:\n";
            echo "   php flarum extension:disable steperlin-service-worker-cache\n";
            echo "   php flarum extension:enable steperlin-service-worker-cache\n\n";
            
            if (!empty($this->results['source_files'])) {
                $sourceFile = $this->results['source_files'][0]['path'];
                echo "3. 手动复制文件:\n";
                echo "   cp '$sourceFile' '{$this->targetFile}'\n";
                echo "   chmod 644 '{$this->targetFile}'\n\n";
            }
        }
        
        if (!is_writable($this->publicDir)) {
            echo "🔧 问题: Public 目录不可写\n";
            echo "解决方案:\n";
            echo "   chmod 755 '{$this->publicDir}'\n\n";
        }
        
        echo "4. 验证部署结果:\n";
        echo "   ls -la '{$this->targetFile}'\n";
        echo "   curl -I 'https://beiduofen.top/service-worker.js'\n\n";
        
        echo "5. 检查 Flarum 缓存:\n";
        echo "   php flarum cache:clear\n\n";
    }
    
    private function log(string $item, string $status, bool $success): void
    {
        $icon = $success ? "✅" : "❌";
        printf("%-25s %s %s\n", $item . ":", $icon, $status);
        
        // 存储结果用于后续分析
        $key = strtolower(str_replace([' ', '-'], '_', $item));
        $this->results[$key] = $success;
    }
}

// 运行诊断
try {
    $diagnostic = new ServiceWorkerDiagnostic();
    $diagnostic->runFullDiagnostic();
} catch (Exception $e) {
    echo "❌ 诊断过程出错: " . $e->getMessage() . "\n";
    echo "错误详情: " . $e->getTraceAsString() . "\n";
}