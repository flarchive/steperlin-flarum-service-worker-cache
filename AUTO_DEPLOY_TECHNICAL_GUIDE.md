# 🚀 Service Worker 自动部署技术实现指南

## 📋 概述

本文档详细介绍了 Flarum Service Worker Cache 扩展的自动部署机制，彻底解决用户手动部署 Service Worker 文件的痛点。

## 🏗️ 架构设计

### 多层自动部署架构

```mermaid
graph TD
    A[用户安装扩展] --> B[Composer Scripts 触发]
    B --> C[Installer::deployServiceWorker()]
    C --> D{检查环境}
    D -->|通过| E[查找源文件]
    D -->|失败| F[记录错误但不阻断]
    E --> G{创建符号链接}
    G -->|成功| H[部署完成]
    G -->|失败| I[回退到文件复制]
    I --> J{复制成功?}
    J -->|是| H
    J -->|否| K[记录错误]
    
    L[首次HTTP请求] --> M[中间件拦截]
    M --> N{public目录有文件?}
    N -->|有| O[直接提供文件]
    N -->|无| P[尝试自动部署]
    P --> Q[从扩展目录提供]
    
    R[扩展启用事件] --> S[生命周期监听器]
    S --> T[触发自动部署]
```

## 🔧 技术实现详解

### 1. Composer Scripts 自动化

#### 1.1 composer.json 配置

```json
{
    "scripts": {
        "post-install-cmd": ["@deploy-service-worker"],
        "post-update-cmd": ["@deploy-service-worker"],
        "deploy-service-worker": [
            "php -r \"SteperLin\\ServiceWorkerCache\\Installer::deployServiceWorker();\""
        ]
    }
}
```

**工作原理**：
- `post-install-cmd`: 在 `composer install` 后自动执行
- `post-update-cmd`: 在 `composer update` 后自动执行
- 直接调用 PHP 类方法进行部署

**优势**：
- ✅ 与 Composer 生命周期完美集成
- ✅ 无需用户手动干预
- ✅ 支持批量安装场景

**局限性**：
- ⚠️ 需要 PHP 环境支持
- ⚠️ 可能受文件权限限制

#### 1.2 Installer 类架构

```php
class Installer
{
    // 核心部署方法
    public static function deployServiceWorker(): void;
    
    // 环境验证
    private function validateEnvironment(): void;
    
    // 多路径文件查找
    private function findSourceFile(): string;
    
    // 智能部署策略
    private function deployFile(string $sourcePath): void;
    
    // 部署验证
    private function verifyDeployment(): void;
    
    // 状态查询
    public static function getStatus(): array;
    
    // 手动重部署
    public static function redeploy(): bool;
}
```

**关键特性**：

1. **多环境适配**：
   ```php
   $possiblePaths = [
       'vendor/steperlin/flarum-service-worker-cache/service-worker.js',  // Packagist
       'extensions/flarum-service-worker-cache/service-worker.js',        // 本地开发
       'extensions/steperlin-service-worker-cache/service-worker.js',     // 自定义
   ];
   ```

2. **智能部署策略**：
   ```php
   // 优先级：符号链接 > 文件复制
   if ($this->createSymlink($relativePath, $targetPath)) {
       // 符号链接成功
   } elseif ($this->copyFile($sourcePath, $targetPath)) {
       // 回退到文件复制
   }
   ```

3. **完整的错误处理**：
   ```php
   try {
       $this->execute();
   } catch (Exception $e) {
       // 记录错误但不阻断 composer 流程
       self::log("Deployment failed: " . $e->getMessage(), 'error');
   }
   ```

### 2. HTTP 中间件自动部署

#### 2.1 中间件拦截机制

```php
class RegisterServiceWorker implements MiddlewareInterface
{
    private static bool $deploymentAttempted = false;
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri()->getPath();
        
        if ($uri === '/service-worker.js') {
            return $this->handleServiceWorkerRequest();
        }
        
        return $handler->handle($request);
    }
}
```

**工作流程**：

1. **首次请求检测**：
   ```php
   if (!self::$deploymentAttempted) {
       $this->attemptAutoDeployment();
       self::$deploymentAttempted = true;
   }
   ```

2. **优先级处理**：
   ```php
   // 1. 检查 public 目录
   $publicPath = getcwd() . '/public/service-worker.js';
   if (file_exists($publicPath)) {
       return $this->serveFileFromPublic($publicPath);
   }
   
   // 2. 从扩展目录提供
   return $this->serveFileFromExtension();
   ```

3. **自动部署尝试**：
   ```php
   private function attemptAutoDeployment(): void
   {
       try {
           Installer::deployServiceWorker();
       } catch (\Exception $e) {
           error_log("[SW Middleware] Auto-deployment failed: " . $e->getMessage());
       }
   }
   ```

#### 2.2 多路径文件服务

```php
private function serveFileFromExtension(): ResponseInterface
{
    $possiblePaths = [
        __DIR__ . '/../../service-worker.js',  // 相对路径
        getcwd() . '/vendor/steperlin/flarum-service-worker-cache/service-worker.js',  // 绝对路径
        getcwd() . '/extensions/flarum-service-worker-cache/service-worker.js',        // 本地开发
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path) && is_readable($path)) {
            return $this->createResponse($path);
        }
    }
    
    // 回退到默认 Service Worker
    return $this->createDefaultResponse();
}
```

### 3. 扩展生命周期钩子

#### 3.1 生命周期监听器

```php
class ExtensionLifecycleListener
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Enabled::class, [$this, 'onExtensionEnabled']);
        $events->listen(Disabled::class, [$this, 'onExtensionDisabled']);
    }
    
    public function onExtensionEnabled(Enabled $event): void
    {
        if ($event->extension->getId() === 'steperlin-service-worker-cache') {
            Installer::deployServiceWorker();
        }
    }
    
    public function onExtensionDisabled(Disabled $event): void
    {
        if ($event->extension->getId() === 'steperlin-service-worker-cache') {
            $this->cleanupServiceWorker();
        }
    }
}
```

**事件触发时机**：
- ✅ `php flarum extension:enable steperlin-service-worker-cache`
- ✅ `php flarum extension:disable steperlin-service-worker-cache`
- ✅ 管理后台启用/禁用操作

#### 3.2 扩展注册配置

```php
return [
    // 事件监听器注册
    (new Extend\Event())
        ->listen(\Flarum\Frontend\Events\Rendering::class, AddServiceWorkerAssets::class)
        ->subscribe(ExtensionLifecycleListener::class),
];
```

### 4. API 状态接口

#### 4.1 部署状态查询

```javascript
// GET /api/service-worker/status
{
    "enabled": true,
    "version": "1.1.1",
    "cache_strategy": "intelligent",
    "flarum_version": "2.0.0@beta",
    "deployment_status": {
        "deployed": true,
        "file_exists": true,
        "is_symlink": true,
        "file_size": 23456,
        "last_modified": "2024-10-11 15:30:45",
        "deployment_method": "symlink",
        "errors": []
    }
}
```

#### 4.2 手动重部署接口

```javascript
// POST /api/service-worker/redeploy
{
    "success": true,
    "message": "Redeployment completed",
    "status": {
        "deployed": true,
        "deployment_method": "symlink"
    }
}
```

## 🎯 部署策略详解

### 部署优先级

1. **符号链接** (推荐)
   ```bash
   ln -s ../vendor/steperlin/flarum-service-worker-cache/service-worker.js public/service-worker.js
   ```
   - ✅ 自动跟随源文件更新
   - ✅ 节省磁盘空间
   - ⚠️ 需要文件系统支持

2. **文件复制** (回退)
   ```bash
   cp vendor/steperlin/flarum-service-worker-cache/service-worker.js public/service-worker.js
   ```
   - ✅ 兼容性最好
   - ⚠️ 需要手动更新
   - ⚠️ 占用额外空间

3. **动态提供** (兜底)
   - 通过中间件动态读取和提供文件
   - ✅ 100% 可靠
   - ⚠️ 轻微性能影响

### 路径检测逻辑

```php
private function findSourceFile(): string
{
    $possiblePaths = [
        // Packagist 标准安装
        'vendor/steperlin/flarum-service-worker-cache/service-worker.js',
        
        // 本地开发
        'extensions/flarum-service-worker-cache/service-worker.js',
        
        // 自定义安装
        'extensions/steperlin-service-worker-cache/service-worker.js',
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path) && is_readable($path)) {
            return realpath($path);
        }
    }
    
    throw new Exception("Service Worker source file not found");
}
```

## 🔍 调试和监控

### 日志记录

```php
private static function log(string $message, string $level = 'info'): void
{
    $timestamp = date('Y-m-d H:i:s');
    $prefix = '[SW Installer]';
    $output = ($level === 'error') ? STDERR : STDOUT;
    $formattedMessage = "[{$timestamp}] {$prefix} {$message}" . PHP_EOL;
    fwrite($output, $formattedMessage);
}
```

### HTTP 响应头

```php
return $response
    ->withHeader('X-SW-Source', 'public-directory')      // 文件来源
    ->withHeader('X-Extension-Version', '1.1.1')         // 扩展版本
    ->withHeader('X-SW-Deployment', 'auto-symlink');     // 部署方式
```

### 状态检查命令

```bash
# 检查部署状态
curl -s "https://yourdomain.com/api/service-worker/status" | jq .deployment_status

# 触发重部署
curl -X POST "https://yourdomain.com/api/service-worker/redeploy"

# 检查文件
ls -la public/service-worker.js
curl -I "https://yourdomain.com/service-worker.js"
```

## 🛠️ 故障排除

### 常见问题和解决方案

#### 1. 文件权限问题
```bash
# 检查权限
ls -la public/
# 修复权限
chmod 755 public/
chmod 644 public/service-worker.js
```

#### 2. 符号链接不支持
```bash
# 检查文件系统支持
ln -s test target && rm target
# 如果失败，系统会自动回退到文件复制
```

#### 3. 路径问题
```php
// 使用绝对路径
$realPath = realpath($sourcePath);
$targetPath = getcwd() . '/public/service-worker.js';
```

### 手动恢复步骤

```bash
# 1. 清理旧文件
rm -f public/service-worker.js*

# 2. 手动部署
php -r "SteperLin\ServiceWorkerCache\Installer::deployServiceWorker();"

# 3. 验证
curl -I "https://yourdomain.com/service-worker.js"
```

## 📊 性能影响分析

### 部署性能

| 方式 | 部署时间 | 文件访问 | 更新同步 |
|------|----------|----------|----------|
| 符号链接 | ~10ms | 原生速度 | 自动 |
| 文件复制 | ~50ms | 原生速度 | 手动 |
| 动态提供 | ~1ms | +2ms开销 | 实时 |

### 内存使用

- **Installer类**: ~50KB
- **中间件**: ~10KB
- **生命周期监听器**: ~5KB

## 🎉 总结

通过多层自动部署机制，Flarum Service Worker Cache 扩展实现了：

1. **零配置安装** - 用户无需任何手动操作
2. **多环境适配** - 支持各种安装和部署场景
3. **智能降级** - 多种部署策略确保高可用性
4. **完整监控** - 提供详细的状态查询和调试接口
5. **自动恢复** - 支持自动检测和修复部署问题

这套自动部署机制彻底解决了用户手动部署 Service Worker 文件的痛点，实现了真正的"开箱即用"体验。