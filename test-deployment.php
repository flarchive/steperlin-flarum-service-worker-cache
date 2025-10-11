<?php

/**
 * 直接测试部署方法 - 不依赖 Flarum 事件类型
 */

echo "🔧 Testing Service Worker Deployment Method Directly\n";
echo "====================================================\n\n";

// 直接使用反射来测试私有方法
require_once 'src/Listeners/ExtensionLifecycleListener.php';

use SteperLin\ServiceWorkerCache\Listeners\ExtensionLifecycleListener;

$listener = new ExtensionLifecycleListener();

// 使用反射访问私有方法
$reflection = new ReflectionClass($listener);
$forceDeployMethod = $reflection->getMethod('forceDeploy');
$forceDeployMethod->setAccessible(true);

try {
    echo "🚀 Calling forceDeploy() method directly...\n\n";
    $forceDeployMethod->invoke($listener);
    
    echo "\n✅ Direct method call completed!\n\n";
    
    // 检查部署结果
    echo "🔍 Checking deployment results...\n";
    $publicFile = getcwd() . '/public/service-worker.js';
    
    if (file_exists($publicFile)) {
        $fileSize = filesize($publicFile);
        echo "✅ File exists: {$publicFile}\n";
        echo "📏 File size: {$fileSize} bytes\n";
        
        // 验证文件内容
        $content = file_get_contents($publicFile);
        if (strpos($content, 'Flarum Service Worker') !== false) {
            echo "✅ File content is valid (contains 'Flarum Service Worker')\n";
            echo "🎯 Deployment successful!\n";
        } else {
            echo "❌ File content is invalid - missing 'Flarum Service Worker'\n";
        }
        
        // 显示前几行内容
        $lines = explode("\n", $content);
        echo "\n📄 First few lines of deployed file:\n";
        for ($i = 0; $i < min(5, count($lines)); $i++) {
            echo "   " . ($i + 1) . ": " . $lines[$i] . "\n";
        }
        
    } else {
        echo "❌ File not found: {$publicFile}\n";
        echo "📁 Working directory: " . getcwd() . "\n";
        
        // 检查 public 目录是否存在
        $publicDir = getcwd() . '/public';
        if (is_dir($publicDir)) {
            echo "✅ Public directory exists\n";
            echo "📂 Public directory is " . (is_writable($publicDir) ? "writable" : "not writable") . "\n";
        } else {
            echo "❌ Public directory does not exist\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "📍 Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n🎯 Direct deployment test complete!\n";