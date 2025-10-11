# 快速安装指南

## ⚠️ 重要说明

**当前状态**：此扩展尚未发布到Packagist，需要使用本地安装方法。

## 🚀 当前推荐安装方法

### 方法一：Git克隆安装（推荐）

```bash
# 1. 切换到Flarum根目录
cd /path/to/your/flarum

# 2. 克隆项目
git clone https://github.com/linkerlin/flarum-service-worker-cache.git extensions/flarum-service-worker-cache

# 3. 配置本地仓库
composer config repositories.flarum-sw-cache path "extensions/flarum-service-worker-cache"

# 4. 安装扩展
composer require steperlin/flarum-service-worker-cache:*@dev

# 5. 启用扩展
php flarum extension:enable steperlin-service-worker-cache

# 6. 清除缓存
php flarum cache:clear
```

### 方法二：下载安装

```bash
# 1. 创建扩展目录
mkdir -p extensions/flarum-service-worker-cache

# 2. 下载项目
wget https://github.com/linkerlin/flarum-service-worker-cache/archive/main.zip
unzip main.zip -d extensions/
mv extensions/flarum-service-worker-cache-main/* extensions/flarum-service-worker-cache/

# 3. 配置并安装
composer config repositories.flarum-sw-cache path "extensions/flarum-service-worker-cache"
composer require steperlin/flarum-service-worker-cache:*@dev
php flarum extension:enable steperlin-service-worker-cache
php flarum cache:clear
```

## 🔮 未来标准安装方法（Packagist发布后）

```bash
# 在您的Flarum根目录执行
composer require steperlin/flarum-service-worker-cache
php flarum extension:enable steperlin-service-worker-cache
php flarum cache:clear
```

## ✅ 验证安装

```bash
# 下载并运行验证脚本
wget https://raw.githubusercontent.com/linkerlin/flarum-service-worker-cache/main/install-verify.sh
chmod +x install-verify.sh
./install-verify.sh
```

## 🔧 手动验证

1. **检查浏览器控制台**
   ```
   按F12 → Console → 查找以下日志：
   [SW Cache] 🚀 Initializing Service Worker registration...
   [SW Cache] ✅ Service Worker registered successfully
   ```

2. **检查Service Worker**
   ```
   访问：https://yourdomain.com/service-worker.js
   应该返回JavaScript代码而不是404错误
   ```

3. **检查缓存功能**
   ```
   - 刷新页面，查看Network面板是否显示"from ServiceWorker"
   - 访问 用户设置 → Service Worker 缓存控制台
   ```

## ❗ 常见问题

**Q: Service Worker注册失败？**
```bash
# 检查HTTPS配置
curl -I https://yourdomain.com

# 检查文件权限
ls -la storage/
```

**Q: 缓存没有生效？**
```bash
# 清除Flarum缓存
php flarum cache:clear

# 检查扩展状态
php flarum extension:list
```

**Q: 页面出现错误？**
```bash
# 检查日志
tail -f storage/logs/flarum.log

# 禁用扩展（如需回滚）
php flarum extension:disable steperlin-service-worker-cache
```

## 📞 获取帮助

- 🐛 [报告Bug](https://github.com/linkerlin/flarum-service-worker-cache/issues)
- 💬 [讨论交流](https://discuss.flarum.org)
- 📧 [联系作者](mailto:steper.lin@icloud.com)