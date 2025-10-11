#!/usr/bin/env php
<?php

/**
 * Test Script for ExtensionLifecycleListener
 * 
 * 测试扩展生命周期监听器，验证非阻塞性能
 */

echo "=== Extension Lifecycle Listener Test ===\n\n";

// 模拟加载类
require_once __DIR__ . '/src/Listeners/ExtensionLifecycleListener.php';

use SteperLin\ServiceWorkerCache\Listeners\ExtensionLifecycleListener;

// 创建模拟的事件类
class MockExtension {
    public function getId(): string {
        return 'steperlin-service-worker-cache';
    }
}

class MockEnabledEvent {
    public $extension;
    
    public function __construct() {
        $this->extension = new MockExtension();
    }
}

class MockDisabledEvent {
    public $extension;
    
    public function __construct() {
        $this->extension = new MockExtension();
    }
}

// 测试 Listener
$listener = new ExtensionLifecycleListener();

echo "1. 测试扩展启用事件处理...\n";
$startTime = microtime(true);

$enabledEvent = new MockEnabledEvent();
$listener->onExtensionEnabled($enabledEvent);

$enabledTime = microtime(true) - $startTime;
echo "   启用事件处理耗时: " . number_format($enabledTime * 1000, 2) . " ms\n";

if ($enabledTime < 0.01) { // 小于 10ms
    echo "   ✅ 启用事件处理速度正常 (非阻塞)\n";
} else {
    echo "   ⚠️ 启用事件处理可能存在阻塞\n";
}

echo "\n2. 测试扩展禁用事件处理...\n";
$startTime = microtime(true);

$disabledEvent = new MockDisabledEvent();
$listener->onExtensionDisabled($disabledEvent);

$disabledTime = microtime(true) - $startTime;
echo "   禁用事件处理耗时: " . number_format($disabledTime * 1000, 2) . " ms\n";

if ($disabledTime < 0.01) { // 小于 10ms
    echo "   ✅ 禁用事件处理速度正常 (非阻塞)\n";
} else {
    echo "   ⚠️ 禁用事件处理可能存在阻塞\n";
}

echo "\n3. 等待后台任务完成...\n";
sleep(1); // 等待 register_shutdown_function 执行

echo "\n4. 检查文件操作结果...\n";

// 检查是否存在 service-worker.js 文件
$publicDir = getcwd() . '/public';
$targetFile = $publicDir . '/service-worker.js';

if (is_dir($publicDir)) {
    echo "   Public 目录: 存在\n";
    
    if (file_exists($targetFile)) {
        echo "   Service Worker 文件: 存在\n";
        $fileSize = filesize($targetFile);
        echo "   文件大小: {$fileSize} bytes\n";
    } else {
        echo "   Service Worker 文件: 不存在 (可能是源文件未找到)\n";
    }
} else {
    echo "   Public 目录: 不存在 (正常，这是测试环境)\n";
}

echo "\n5. 测试结果总结:\n";

$totalTime = $enabledTime + $disabledTime;
echo "   总响应时间: " . number_format($totalTime * 1000, 2) . " ms\n";

if ($totalTime < 0.02) {
    echo "   ✅ 生命周期监听器性能优秀，不会导致卡顿\n";
} elseif ($totalTime < 0.05) {
    echo "   🟡 生命周期监听器性能良好\n";
} else {
    echo "   ❌ 生命周期监听器可能导致性能问题\n";
}

echo "\n=== 测试完成 ===\n";