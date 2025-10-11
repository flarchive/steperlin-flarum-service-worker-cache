# Service Worker Cache 紧急故障排除指南 v1.3.0

**问题**: 插件 disable 卡住，enable 后网站页面加载卡住超时  
**版本**: v1.3.0  
**紧急程度**: 🚨 高危险  

## 🚨 立即执行的紧急修复

### 步骤 1: 停止卡住的进程

```bash
# 检查是否有卡住的 Flarum 进程
ps aux | grep flarum

# 如果发现卡住的进程，强制终止
pkill -f "flarum extension"

# 检查 PHP 进程
ps aux | grep php | grep flarum
```

### 步骤 2: 运行紧急修复工具

```bash
# 进入 Flarum 根目录
cd /path/to/your/flarum/installation

# 运行紧急修复工具
php vendor/steperlin/flarum-service-worker-cache/emergency-fix-v1.3.0.php
```

### 步骤 3: 手动清理 Service Worker

```bash
# 进入 Flarum 根目录
cd /var/www/beiduofen  # 替换为您的实际路径

# 备份并删除现有的 Service Worker 文件
if [ -f public/service-worker.js ]; then
    cp public/service-worker.js public/service-worker.js.emergency-backup.$(date +%Y%m%d%H%M%S)
    rm public/service-worker.js
    echo "Service Worker 文件已清理"
fi

# 清理 Flarum 缓存
php flarum cache:clear

# 检查权限
chmod 755 public/
ls -ld public/
```

## 🔍 根本原因分析

基于代码分析，发现了导致卡住的主要问题：

### 1. **日志递归调用**
```php
// 问题代码（已修复）
private function log(string $message): void
{
    // 这里调用 getWorkingDirectory() 
    $logFile = $this->getWorkingDirectory() . '/storage/logs/service-worker.log';
    // 而 getWorkingDirectory() 中又调用 log()，形成递归
}
```

**修复**: 简化日志方法，避免递归调用。

### 2. **路径检测过度复杂**
```php
// 问题：5种检测方法，每种都可能触发文件系统操作
private function getWorkingDirectory(): string
{
    // 方法1: 类文件位置
    // 方法2: 工作目录
    // 方法3: 向上搜索 (可能很慢)
    // 方法4: 脚本路径
    // 方法5: 插件目录计算
}
```

**修复**: 简化为3种最可靠的方法，减少 IO 操作。

### 3. **前端脚本无限重试**
```javascript
// 问题代码
.catch(function(error) {
    // 注册失败后5秒重试，可能形成无限循环
    setTimeout(() => {
        registerServiceWorker(); // 递归调用
    }, 5000);
});
```

**修复**: 移除自动重试机制，避免页面卡住。

## 📋 完整修复方案

### 修复 1: 更新到修复版本

```bash
# 更新扩展到最新修复版本
composer update steperlin/flarum-service-worker-cache

# 验证版本（应该是 v1.3.0 或更高）
composer show steperlin/flarum-service-worker-cache
```

### 修复 2: 安全地重新启用插件

```bash
# 确保在 Flarum 根目录
cd /var/www/beiduofen

# 先确保插件完全禁用
php flarum extension:disable steperlin-service-worker-cache

# 清理缓存
php flarum cache:clear

# 检查是否有遗留的 Service Worker 文件
ls -la public/service-worker.js*

# 重新启用插件（现在应该不会卡住）
php flarum extension:enable steperlin-service-worker-cache
```

### 修复 3: 验证部署结果

```bash
# 检查文件是否正确部署
ls -la public/service-worker.js

# 测试 HTTP 访问
curl -I https://beiduofen.top/service-worker.js

# 检查文件内容
head -5 public/service-worker.js
```

## 🛡️ 预防措施

### 1. 监控脚本

创建监控脚本防止类似问题：

```bash
#!/bin/bash
# monitor-service-worker.sh

FLARUM_ROOT="/var/www/beiduofen"
SW_FILE="$FLARUM_ROOT/public/service-worker.js"
LOG_FILE="$FLARUM_ROOT/storage/logs/sw-monitor.log"

# 检查文件状态
if [ -f "$SW_FILE" ]; then
    SIZE=$(stat -f%z "$SW_FILE" 2>/dev/null || stat -c%s "$SW_FILE" 2>/dev/null)
    if [ "$SIZE" -lt 1000 ]; then
        echo "$(date): WARNING - Service Worker file too small ($SIZE bytes)" >> "$LOG_FILE"
    else
        echo "$(date): OK - Service Worker file normal ($SIZE bytes)" >> "$LOG_FILE"
    fi
else
    echo "$(date): ERROR - Service Worker file missing" >> "$LOG_FILE"
fi

# 检查是否有卡住的进程
STUCK_PROCESSES=$(ps aux | grep "[f]larum extension" | wc -l)
if [ "$STUCK_PROCESSES" -gt 0 ]; then
    echo "$(date): WARNING - Found $STUCK_PROCESSES stuck Flarum processes" >> "$LOG_FILE"
fi
```

### 2. 系统级超时设置

在 `php.ini` 中设置合理的超时：

```ini
; 设置合理的执行时间限制
max_execution_time = 30

; 设置内存限制
memory_limit = 256M

; 避免长时间等待
default_socket_timeout = 10
```

### 3. Web 服务器配置

**Nginx 配置**:
```nginx
location = /service-worker.js {
    expires 0;
    add_header Cache-Control "no-cache, no-store, must-revalidate";
    try_files $uri =404;
    
    # 添加超时保护
    proxy_read_timeout 10s;
    proxy_connect_timeout 5s;
}
```

**Apache 配置**:
```apache
<Files "service-worker.js">
    Header set Cache-Control "no-cache, no-store, must-revalidate"
    Header set Pragma "no-cache"
    Header set Expires "0"
    
    # 添加超时保护
    Timeout 10
</Files>
```

## 🔧 高级故障排除

### 如果问题持续存在

#### 选项 1: 手动部署
```bash
# 手动复制 Service Worker 文件
cd /var/www/beiduofen
cp vendor/steperlin/flarum-service-worker-cache/service-worker.js public/
chmod 644 public/service-worker.js
```

#### 选项 2: 禁用自动部署
临时禁用自动部署机制：

```bash
# 编辑 extend.php，注释掉事件监听器
# 找到这行：->subscribe(ExtensionLifecycleListener::class),
# 改为：// ->subscribe(ExtensionLifecycleListener::class),
```

#### 选项 3: 完全重置
```bash
# 完全移除和重新安装
composer remove steperlin/flarum-service-worker-cache
rm -f public/service-worker.js*
php flarum cache:clear

# 重新安装
composer require steperlin/flarum-service-worker-cache
php flarum extension:enable steperlin-service-worker-cache
```

## 🎯 版本升级路径

### 从受影响版本升级

如果您当前使用的是有问题的版本：

1. **v1.2.0 - v1.2.2**: 立即升级到 v1.3.0
2. **v1.3.0**: 应用最新的修复补丁

```bash
# 升级命令
composer update steperlin/flarum-service-worker-cache

# 验证版本
php vendor/steperlin/flarum-service-worker-cache/diagnose-deployment-v1.3.0.php
```

## 📞 技术支持

如果问题仍然存在：

1. **收集诊断信息**:
   ```bash
   # 运行完整诊断
   php vendor/steperlin/flarum-service-worker-cache/diagnose-deployment-v1.3.0.php > diagnosis.log 2>&1
   
   # 收集系统信息
   php --version > system-info.log
   ls -la public/ >> system-info.log
   ps aux | grep php >> system-info.log
   ```

2. **联系支持**:
   - GitHub Issues: https://github.com/linkerlin/flarum-service-worker-cache/issues
   - 邮箱: steper.lin@icloud.com
   - 提供 `diagnosis.log` 和 `system-info.log`

## ⚡ 快速恢复清单

- [ ] 停止卡住的进程 (`pkill -f "flarum extension"`)
- [ ] 运行紧急修复工具 (`emergency-fix-v1.3.0.php`)
- [ ] 清理 Service Worker 文件
- [ ] 检查和修复权限 (`chmod 755 public/`)
- [ ] 清理 Flarum 缓存 (`php flarum cache:clear`)
- [ ] 更新到最新版本
- [ ] 安全地重新启用插件
- [ ] 验证网站功能正常

---

**紧急情况下，优先保证网站可访问性，然后再考虑 Service Worker 功能！**