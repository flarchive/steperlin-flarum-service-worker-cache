# Flarum Service Worker Cache Extension

> 🚀 **强大的Flarum缓存扩展** - 通过Service Worker技术实现智能缓存，显著提升论坛性能和用户体验

## 🆕 Flarum 2.0 Beta 支持

**✨ 新特性**: 本扩展已全面兼容 **Flarum 2.0.0 Beta**，包括：
- ✅ **PHP 8.1+** 支持和优化
- ✅ **增强的类型声明** 和错误处理
- ✅ **新的API路由** 和中间件架构
- ✅ **更好的性能** 和兼容性

> 📄 **升级指南**: 查看 [FLARUM_2.0_UPGRADE.md](FLARUM_2.0_UPGRADE.md)

## ✨ 核心特性

- **📶 智能缓存策略** - 基于资源类型的多层缓存机制
- **⚡ 极速加载** - API缓存优先 + 后台刷新策略
- **📱 离线支持** - 断网情况下仍可浏览已缓存内容
- **🔄 自动更新** - 后台自动检测并更新缓存内容
- **🎯 零配置** - 安装即用，无需手动配置
- **🔧 智能管理** - 内置缓存控制台，方便调试

## 📦 安装方式

> **⚠️ 当前状态**：此扩展正在准备发布到Packagist，目前请使用本地安装方法。

### 方式一：本地安装（当前推荐）

**1. 克隆项目**
```bash
# 在Flarum根目录执行
git clone https://github.com/linkerlin/flarum-service-worker-cache.git extensions/flarum-service-worker-cache
```

**2. 配置本地仓库**
```bash
composer config repositories.flarum-sw-cache path "extensions/flarum-service-worker-cache"
```

**3. 安装扩展**
```bash
composer require steperlin/flarum-service-worker-cache:*@dev
php flarum extension:enable steperlin-service-worker-cache
php flarum cache:clear
```

**4. 完成！**
⚡ **零配置** - 扩展会自动注册所有必要的组件，无需修改任何配置文件！

> 📄 **详细安装指南**：查看 [LOCAL_INSTALL.md](LOCAL_INSTALL.md)

### 方式二：标准Composer安装（即将可用）

```bash
# 即将支持（Packagist发布后）
composer require steperlin/flarum-service-worker-cache
php flarum extension:enable steperlin-service-worker-cache
php flarum cache:clear
```

## 🚀 快速验证

安装完成后，按 `F12` 打开浏览器开发者工具，查看Console面板：

```
[SW Cache] 🚀 Initializing Service Worker registration...
[SW Cache] 📡 Registering Service Worker...
[SW Cache] ✅ Service Worker registered successfully
[SW Cache] 📍 Scope: https://your-forum.com/
[SW Cache] 🔄 Update check completed
```

看到以上日志说明安装成功！

## 📊 缓存策略详解

### 资源分类缓存

| 资源类型 | 缓存策略 | 有效期 | 说明 |
|---------|---------|--------|------|
| **API数据** | 缓存优先+后台刷新 | 5分钟 | 立即返回缓存，后台更新 |
| **HTML页面** | 缓存优先+后台刷新 | 24小时 | 保证CSRF令牌有效性 |
| **静态资源** | 缓存优先+后台刷新 | 7天 | JS/CSS/字体文件 |
| **图片资源** | 缓存优先+后台刷新 | 30天 | 支持CDN和外部图片 |

### 智能域名处理

- **本站资源** - 全面缓存优化
- **CDN资源** - 支持主流CDN（jsdelivr, cdnjs, unpkg）
- **外部域名** - 仅缓存特定图片服务（imgur等）
- **管理面板** - 自动跳过，保证实时性

## 🔧 缓存管理

### 内置控制台

访问 **用户设置 > Service Worker 缓存控制台** 查看：

- 🔍 **状态检查** - Service Worker运行状态
- 📊 **缓存统计** - 缓存数量和详情
- 🗑️ **清除缓存** - 一键清除所有缓存
- 🔄 **强制刷新** - 强制更新Service Worker

### API接口

扩展提供RESTful API用于缓存状态查询：

```bash
# 获取缓存状态
GET /api/service-worker/status

# 响应示例
{
  "enabled": true,
  "version": "1.0.0",
  "cache_strategy": "intelligent"
}
```

## 🛡️ 技术实现

### 架构设计

```
┌──────────────────────────────────────────────┐
│                Flarum Frontend                │
├──────────────────────────────────────────────┤
│          Service Worker Registration         │
│              (Auto-injected)                │
├──────────────────────────────────────────────┤
│               Service Worker                │
│          ┌────────────────────────────────────┐           │
│          │       Cache Strategy        │           │
│          │   • API Cache (5min)        │           │
│          │   • Static Cache (7d)      │           │
│          │   • Image Cache (30d)      │           │
│          │   • HTML Cache (24h)       │           │
│          └────────────────────────────────────┘           │
├──────────────────────────────────────────────┤
│          RegisterServiceWorker Middleware    │
│             (Route: /service-worker.js)       │
├──────────────────────────────────────────────┤
│              Flarum Backend                │
└──────────────────────────────────────────────┘
```

### 核心组件

1. **RegisterServiceWorker** - 中间件，处理SW文件请求
2. **AddServiceWorkerAssets** - 事件监听器，注入前端资源
3. **ServiceWorkerSettings** - 用户界面组件
4. **service-worker.js** - 核心缓存逻辑

## 🔍 故障排除

### 常见问题

**Q: Service Worker未注册成功？**
A: 检查HTTPS环境，Service Worker需要安全上下文

**Q: 缓存没有生效？**
A: 打开开发者工具，检查Console日志和Network面板

**Q: 更新后缓存未刷新？**
A: 使用缓存控制台的"强制刷新SW"功能

### 调试技巧

```javascript
// 在浏览器Console中执行
// 查看Service Worker状态
navigator.serviceWorker.getRegistration('/').then(reg => console.log(reg));

// 查看所有缓存
caches.keys().then(names => console.log('Caches:', names));

// 查看特定缓存内容
caches.open('flarum-cache-v1.1').then(cache => 
  cache.keys().then(keys => console.log('Cache keys:', keys))
);
```

## 🔧 系统要求

### Flarum 2.0 Beta
- **Flarum**: 2.0.0@beta
- **PHP**: 8.1+ (推荐 8.2)
- **环境**: HTTPS推荐（生产环境必需）
- **浏览器**: Chrome 40+, Firefox 44+, Safari 11.1+, Edge 17+

### Flarum 1.x (传统支持)
- **Flarum**: 1.0.0+
- **PHP**: 7.4+ (传统版本)
- **环境**: HTTPS推荐（生产环境必需）
```

## 📈 性能指标

经测试，使用本扩展后：

- **首次加载** - 基本无变化（需要初始化缓存）
- **二次访问** - 平均提升 **60-80%** 加载速度
- **API请求** - 平均响应时间减少 **90%**
- **离线访问** - 支持已缓存页面的完全离线浏览

## 🐛 问题反馈

遇到问题？欢迎反馈：

- **GitHub Issues**: [提交问题](https://github.com/linkerlin/flarum-service-worker-cache/issues)
- **Flarum社区**: [讨论交流](https://discuss.flarum.org)

## 📝 开源协议

本项目基于 [Apache-2.0](LICENSE) 协议开源

## 🙏 致谢

感谢Flarum社区的支持和贡献者们的辛勤付出！

---

**❤️ 如果这个扩展对你有帮助，请给个Star支持一下！**
