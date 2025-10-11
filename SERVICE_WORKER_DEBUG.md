# 🔧 Service Worker 调试指南

## 问题诊断

您遇到的 404 错误表明 Service Worker 文件没有被正确提供。以下是完整的诊断和解决方案。

## 🚀 立即解决方案

### 步骤 1：清除缓存并重启扩展

```bash
# 在您的 Flarum 根目录执行
php flarum cache:clear
php flarum extension:disable steperlin-service-worker-cache
php flarum extension:enable steperlin-service-worker-cache
php flarum cache:clear
```

### 步骤 2：验证扩展安装

```bash
# 检查扩展是否正确安装
php flarum info

# 应该看到类似输出
# Extensions:
#   steperlin-service-worker-cache v1.1.0
```

### 步骤 3：测试 Service Worker 端点

```bash
# 测试 Service Worker 是否可访问
curl -I "https://beiduofen.top/service-worker.js"

# 应该返回 200 状态码和正确的 Content-Type
# HTTP/1.1 200 OK
# Content-Type: application/javascript; charset=utf-8
# Service-Worker-Allowed: /
# X-Extension-Version: 1.1.0
```

## 🔍 深度诊断

### 检查文件权限和路径

```bash
# 进入扩展目录
cd vendor/steperlin/flarum-service-worker-cache
# 或者如果使用本地安装：
cd extensions/flarum-service-worker-cache

# 检查 service-worker.js 是否存在
ls -la service-worker.js

# 检查文件权限（应该是可读的）
ls -la service-worker.js

# 检查扩展结构
tree -L 3
```

### 验证中间件注册

创建测试脚本来验证中间件是否正确工作：

```bash
# 创建测试文件
cat > test_service_worker.php << 'EOF'
<?php
// 测试 Service Worker 中间件

// 模拟请求到 /service-worker.js
$_SERVER['REQUEST_URI'] = '/service-worker.js';
$_SERVER['REQUEST_METHOD'] = 'GET';

// 检查中间件类是否存在
$middlewareClass = 'SteperLin\\ServiceWorkerCache\\Middleware\\RegisterServiceWorker';
if (class_exists($middlewareClass)) {
    echo "✅ 中间件类存在: $middlewareClass\n";
    
    // 检查文件路径
    $reflector = new ReflectionClass($middlewareClass);
    $filename = $reflector->getFileName();
    echo "📁 中间件文件位置: $filename\n";
    
    // 检查 service-worker.js 相对路径
    $swPath = dirname($filename, 2) . '/service-worker.js';
    echo "🔍 预期 Service Worker 路径: $swPath\n";
    echo "📄 文件是否存在: " . (file_exists($swPath) ? '✅ 是' : '❌ 否') . "\n";
    
    if (file_exists($swPath)) {
        echo "📊 文件大小: " . filesize($swPath) . " 字节\n";
        echo "🕒 最后修改: " . date('Y-m-d H:i:s', filemtime($swPath)) . "\n";
    }
} else {
    echo "❌ 中间件类不存在: $middlewareClass\n";
    echo "🔧 请检查扩展是否正确安装\n";
}
EOF

# 运行测试
php test_service_worker.php

# 删除测试文件
rm test_service_worker.php
```

## 🛠️ 解决方案选项

### 方案 A：手动复制 Service Worker 文件（临时解决）

```bash
# 如果中间件不工作，手动复制文件到 public 目录
cd /path/to/your/flarum

# 从扩展复制文件到 public 目录
cp vendor/steperlin/flarum-service-worker-cache/service-worker.js public/

# 或者如果使用本地安装：
cp extensions/flarum-service-worker-cache/service-worker.js public/

# 设置正确的权限
chmod 644 public/service-worker.js

# 验证文件
ls -la public/service-worker.js
```

### 方案 B：创建符号链接（推荐）

```bash
# 创建符号链接（更优雅的解决方案）
cd /path/to/your/flarum/public

# 删除可能存在的旧文件
rm -f service-worker.js

# 创建符号链接
ln -s ../vendor/steperlin/flarum-service-worker-cache/service-worker.js service-worker.js

# 或者如果使用本地安装：
ln -s ../extensions/flarum-service-worker-cache/service-worker.js service-worker.js

# 验证链接
ls -la service-worker.js
```

### 方案 C：检查 Web 服务器配置

```bash
# 检查 Nginx 配置（如果使用 Nginx）
sudo nginx -t

# 检查是否有重写规则阻止 .js 文件访问
# 在 Nginx 配置中应该有：
# location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
#     expires 1y;
#     add_header Cache-Control "public, immutable";
# }

# 如果使用 Apache，检查 .htaccess
cat .htaccess | grep -i "\.js"
```

## 🧪 测试和验证

### 浏览器控制台测试

打开浏览器开发者工具，在控制台运行：

```javascript
// 测试 Service Worker 注册
navigator.serviceWorker.register('/service-worker.js')
  .then(registration => {
    console.log('✅ Service Worker 注册成功', registration);
  })
  .catch(error => {
    console.error('❌ Service Worker 注册失败', error);
  });

// 检查当前 Service Worker 状态
navigator.serviceWorker.getRegistrations()
  .then(registrations => {
    console.log('📋 当前注册的 Service Workers:', registrations);
  });
```

### 网络请求测试

```bash
# 使用 curl 测试不同的端点
curl -v "https://beiduofen.top/service-worker.js"
curl -v "https://beiduofen.top/forum/service-worker.js"

# 检查响应头
curl -I "https://beiduofen.top/service-worker.js"
```

## 🔄 完整重置流程

如果问题持续存在，执行完整重置：

```bash
# 1. 完全禁用扩展
php flarum extension:disable steperlin-service-worker-cache

# 2. 清除所有缓存
php flarum cache:clear
rm -rf storage/cache/*
rm -rf storage/views/*

# 3. 清除浏览器 Service Worker 缓存
# 在浏览器开发者工具 > Application > Service Workers
# 点击 "Unregister" 注销所有 Service Workers

# 4. 重新启用扩展
php flarum extension:enable steperlin-service-worker-cache

# 5. 再次清除缓存
php flarum cache:clear

# 6. 重启 Web 服务器
sudo systemctl restart nginx  # 或者 apache2
sudo systemctl restart php8.1-fpm  # 根据您的 PHP 版本
```

## 🚨 紧急备用方案

如果所有方法都不工作，使用内联 Service Worker：

```bash
# 创建紧急修复脚本
cat > emergency_fix.php << 'EOF'
<?php
// 紧急修复：将 Service Worker 内容注入到页面中

// 在 extend.php 的前端脚本中添加内联 Service Worker
$serviceWorkerContent = file_get_contents('vendor/steperlin/flarum-service-worker-cache/service-worker.js');
$serviceWorkerContent = str_replace(['"', "\n"], ['\"', '\\n'], $serviceWorkerContent);

echo "// 将以下代码添加到 extend.php 的前端脚本中:\n";
echo "const sw = new Blob([\"$serviceWorkerContent\"], {type: 'application/javascript'});\n";
echo "const swUrl = URL.createObjectURL(sw);\n";
echo "navigator.serviceWorker.register(swUrl);\n";
EOF

php emergency_fix.php
```

## 📞 支持和反馈

如果问题仍然存在，请提供以下信息：

1. **服务器环境**：
   ```bash
   php -v
   nginx -v  # 或 apache2 -v
   ```

2. **Flarum 版本**：
   ```bash
   php flarum info
   ```

3. **文件权限**：
   ```bash
   ls -la vendor/steperlin/flarum-service-worker-cache/
   ```

4. **错误日志**：
   ```bash
   tail -n 50 /var/log/nginx/error.log
   tail -n 50 storage/logs/flarum.log
   ```

请将这些信息发送到：steper.lin@icloud.com

---

**快速解决**：最可能有效的方案是**方案 B（符号链接）**，请先尝试这个方法！