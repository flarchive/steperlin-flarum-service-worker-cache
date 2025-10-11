# 故障排除指南

## 🚨 安装问题

### 问题1：包未找到错误
```
Could not find a matching version of package steperlin/flarum-service-worker-cache
```

**原因**: 包尚未发布到Packagist或使用了错误的包名

**解决方案**:
```bash
# 方法1：使用本地安装
git clone https://github.com/linkerlin/flarum-service-worker-cache.git extensions/flarum-service-worker-cache
composer config repositories.flarum-sw-cache path "extensions/flarum-service-worker-cache"
composer require steperlin/flarum-service-worker-cache:*@dev

# 方法2：等待Packagist发布
# 查看发布状态：https://packagist.org/packages/steperlin/flarum-service-worker-cache
```

### 问题2：Composer权限警告
```
Do not run Composer as root/super user!
```

**原因**: 使用sudo或root权限运行Composer

**解决方案**:
```bash
# 不要使用sudo
# ❌ 错误
sudo composer require steperlin/flarum-service-worker-cache

# ✅ 正确
composer require steperlin/flarum-service-worker-cache

# 如果权限不足，更改目录所有者
sudo chown -R $USER:$USER /path/to/your/flarum
```

### 问题3：稳定性约束错误
```
matches your minimum-stability (beta)
```

**原因**: Composer稳定性设置与包版本不匹配

**解决方案**:
```bash
# 方法1：允许开发版本
composer require steperlin/flarum-service-worker-cache:*@dev

# 方法2：修改最小稳定性
composer config minimum-stability dev
composer config prefer-stable true

# 方法3：临时覆盖稳定性
composer require steperlin/flarum-service-worker-cache --with-dependencies
```

## 🔧 扩展启用问题

### 问题4：扩展启用失败
```
Extension not found or invalid
```

**原因**: 扩展未正确安装或文件损坏

**解决方案**:
```bash
# 检查扩展是否存在
ls -la vendor/steperlin/flarum-service-worker-cache/

# 检查composer.json是否包含扩展
grep "steperlin/flarum-service-worker-cache" composer.json

# 重新安装扩展
composer remove steperlin/flarum-service-worker-cache
composer require steperlin/flarum-service-worker-cache:*@dev

# 清除缓存后重试
php flarum cache:clear
php flarum extension:enable steperlin-service-worker-cache
```

### 问题5：数据库连接错误
```
Database connection failed
```

**原因**: 数据库配置问题或权限不足

**解决方案**:
```bash
# 检查数据库配置
cat config.php | grep database

# 检查数据库连接
php flarum info

# 修复数据库权限
# MySQL示例
GRANT ALL PRIVILEGES ON flarum_db.* TO 'flarum_user'@'localhost';
FLUSH PRIVILEGES;
```

## ⚡ Service Worker问题

### 问题6：Service Worker注册失败
```
Service Worker registration failed
```

**原因**: HTTPS要求、路径错误或浏览器不支持

**解决方案**:
```bash
# 检查HTTPS配置
curl -I https://your-domain.com
# 应该返回200状态码

# 检查Service Worker文件
curl https://your-domain.com/service-worker.js
# 应该返回JavaScript代码

# 本地开发环境解决方案
# Chrome: 访问 chrome://flags/#allow-insecure-localhost
# 启用 "Allow invalid certificates for resources loaded from localhost"
```

**浏览器兼容性检查**:
```javascript
// 在浏览器控制台执行
if ('serviceWorker' in navigator) {
    console.log('✅ Service Worker支持');
} else {
    console.log('❌ Service Worker不支持');
}
```

### 问题7：缓存不生效
```
缓存未命中，请求仍然很慢
```

**原因**: Service Worker未正确注册或缓存策略配置问题

**解决方案**:
```javascript
// 在浏览器控制台检查
// 1. 检查Service Worker状态
navigator.serviceWorker.getRegistration('/').then(reg => {
    if (reg) {
        console.log('✅ Service Worker已注册:', reg);
        console.log('状态:', reg.active ? '活跃' : '未活跃');
    } else {
        console.log('❌ Service Worker未注册');
    }
});

// 2. 检查缓存
caches.keys().then(names => {
    console.log('可用缓存:', names);
    names.forEach(name => {
        caches.open(name).then(cache => {
            cache.keys().then(keys => {
                console.log(`${name} 缓存项目数:`, keys.length);
            });
        });
    });
});

// 3. 强制重新注册
navigator.serviceWorker.getRegistration('/').then(reg => {
    if (reg) {
        return reg.unregister();
    }
}).then(() => {
    return navigator.serviceWorker.register('/service-worker.js', {scope: '/'});
}).then(reg => {
    console.log('🔄 Service Worker重新注册成功');
});
```

## 🔍 调试技巧

### 调试工具使用

**浏览器开发者工具**:
1. 按F12打开开发者工具
2. **Console面板** - 查看Service Worker日志
3. **Network面板** - 检查请求是否来自缓存
4. **Application面板** - 查看Service Worker状态和缓存详情

**Flarum日志**:
```bash
# 查看Flarum错误日志
tail -f storage/logs/flarum.log

# 启用调试模式（config.php）
'debug' => true,
```

### 性能测试

**网络性能测试**:
```bash
# 使用curl测试响应时间
time curl -s https://your-domain.com/ > /dev/null

# 使用浏览器Network面板
# 1. 打开F12 → Network
# 2. 清除缓存并硬性重新加载（Ctrl+Shift+R）
# 3. 查看DOMContentLoaded和Load时间
# 4. 再次刷新，对比缓存效果
```

**缓存命中率测试**:
```javascript
// 在浏览器控制台执行
let cacheHits = 0;
let totalRequests = 0;

// 监听网络请求
const observer = new PerformanceObserver((list) => {
    list.getEntries().forEach((entry) => {
        totalRequests++;
        if (entry.transferSize === 0 && entry.decodedBodySize > 0) {
            cacheHits++;
        }
    });
    console.log(`缓存命中率: ${(cacheHits/totalRequests*100).toFixed(2)}%`);
});

observer.observe({entryTypes: ['resource']});
```

## 🛠️ 常见配置问题

### 问题8：权限配置
```
Permission denied
```

**解决方案**:
```bash
# 检查目录权限
ls -la storage/
ls -la vendor/

# 修复权限
chmod -R 755 storage/
chmod -R 644 storage/logs/
chown -R www-data:www-data storage/  # Apache
chown -R nginx:nginx storage/        # Nginx
```

### 问题9：内存或时间限制
```
Maximum execution time exceeded
Fatal error: Allowed memory size exhausted
```

**解决方案**:
```php
// 在config.php中添加
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);

// 或在.htaccess中添加
php_value memory_limit 256M
php_value max_execution_time 300
```

### 问题10：URL重写问题
```
404 Not Found for /service-worker.js
```

**解决方案**:
```apache
# Apache (.htaccess)
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

```nginx
# Nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location = /service-worker.js {
    try_files $uri /index.php?$query_string;
}
```

## 📊 性能监控

### 设置监控指标

**Service Worker性能监控**:
```javascript
// 添加到页面中监控缓存性能
window.swCacheStats = {
    cacheHits: 0,
    cacheMisses: 0,
    avgResponseTime: 0
};

// 监听Service Worker消息
navigator.serviceWorker.addEventListener('message', (event) => {
    if (event.data.type === 'CACHE_STATS') {
        Object.assign(window.swCacheStats, event.data.stats);
        console.log('缓存统计:', window.swCacheStats);
    }
});
```

**服务器端监控**:
```bash
# 使用htop监控系统资源
htop

# 监控磁盘使用
df -h

# 监控网络连接
netstat -an | grep :80 | wc -l
```

## 🆘 获取帮助

### 自助诊断工具

**使用验证脚本**:
```bash
# 下载并运行诊断脚本
wget https://raw.githubusercontent.com/linkerlin/flarum-service-worker-cache/main/install-verify.sh
chmod +x install-verify.sh
./install-verify.sh
```

**手动诊断清单**:
- [ ] PHP版本 ≥ 7.4
- [ ] Flarum版本 ≥ 1.0.0
- [ ] HTTPS配置正确
- [ ] 目录权限正确
- [ ] Service Worker文件可访问
- [ ] 浏览器支持Service Worker
- [ ] 扩展正确启用

### 社区支持

**报告问题时请提供**:
1. **环境信息**:
   - Flarum版本
   - PHP版本
   - Web服务器类型和版本
   - 操作系统

2. **错误信息**:
   - 完整的错误消息
   - 浏览器控制台日志
   - Flarum错误日志

3. **复现步骤**:
   - 详细的操作步骤
   - 预期结果vs实际结果

**获取帮助渠道**:
- **GitHub Issues**: [报告问题](https://github.com/linkerlin/flarum-service-worker-cache/issues)
- **Flarum社区**: [讨论区](https://discuss.flarum.org)
- **邮件支持**: steper.lin@icloud.com

### 问题模板

```markdown
## 环境信息
- Flarum版本: 
- PHP版本: 
- Web服务器: 
- 操作系统: 
- 浏览器: 

## 问题描述
<!-- 详细描述问题 -->

## 复现步骤
1. 
2. 
3. 

## 预期结果
<!-- 应该发生什么 -->

## 实际结果
<!-- 实际发生了什么 -->

## 错误日志
<!-- 粘贴相关的错误日志 -->

## 其他信息
<!-- 任何其他可能有用的信息 -->
```

---

**记住**: 大多数问题都有解决方案，不要放弃！🚀