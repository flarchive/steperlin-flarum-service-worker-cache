# 本地安装指南 - Flarum Service Worker Cache

## 🚀 方法一：Git克隆 + 本地路径安装

### 步骤1：克隆项目到Flarum根目录
```bash
# 切换到Flarum根目录
cd /path/to/your/flarum

# 克隆项目到extensions目录
git clone https://github.com/linkerlin/flarum-service-worker-cache.git extensions/flarum-service-worker-cache
```

### 步骤2：配置Composer本地仓库
```bash
# 添加本地路径仓库
composer config repositories.flarum-sw-cache path "extensions/flarum-service-worker-cache"
```

### 步骤3：安装扩展
```bash
# 安装扩展（开发版本）
composer require steperlin/flarum-service-worker-cache:*@dev

# 启用扩展
php flarum extension:enable steperlin-service-worker-cache

# 清除缓存
php flarum cache:clear
```

## 🔄 方法二：直接下载安装

### 步骤1：下载项目文件
```bash
# 创建扩展目录
mkdir -p extensions/flarum-service-worker-cache

# 下载并解压
wget https://github.com/linkerlin/flarum-service-worker-cache/archive/main.zip
unzip main.zip -d extensions/
mv extensions/flarum-service-worker-cache-main/* extensions/flarum-service-worker-cache/
rm -rf extensions/flarum-service-worker-cache-main main.zip
```

### 步骤2：配置并安装
```bash
# 配置本地仓库
composer config repositories.flarum-sw-cache path "extensions/flarum-service-worker-cache"

# 安装扩展
composer require steperlin/flarum-service-worker-cache:*@dev

# 启用扩展
php flarum extension:enable steperlin-service-worker-cache

# 清除缓存
php flarum cache:clear
```

## ✅ 验证安装

### 检查扩展状态
```bash
# 查看已安装扩展
php flarum extension:list

# 运行验证脚本
wget https://raw.githubusercontent.com/linkerlin/flarum-service-worker-cache/main/install-verify.sh
chmod +x install-verify.sh
./install-verify.sh
```

### 浏览器验证
1. 访问您的Flarum论坛
2. 按F12打开开发者工具
3. 查看Console面板，应该看到：
```
[SW Cache] 🚀 Initializing Service Worker registration...
[SW Cache] ✅ Service Worker registered successfully
```

### 缓存控制台验证
1. 登录论坛
2. 访问 **用户设置 → Service Worker 缓存控制台**
3. 点击"检查状态"按钮
4. 确认Service Worker运行正常

## 🚨 常见问题解决

### 问题1：权限错误
```bash
# 不要使用sudo运行composer
# 如果需要权限，请更改目录所有者
sudo chown -R $USER:$USER /path/to/your/flarum
```

### 问题2：Composer缓存问题
```bash
# 清除Composer缓存
composer clear-cache

# 重新安装
composer install --no-cache
```

### 问题3：扩展无法启用
```bash
# 检查PHP错误日志
tail -f storage/logs/flarum.log

# 检查权限
ls -la storage/
chmod -R 755 storage/
```

### 问题4：Service Worker注册失败
```bash
# 检查HTTPS配置
curl -I https://your-domain.com

# 确保不是localhost环境的HTTPS问题
# 在Chrome中访问：chrome://flags/#allow-insecure-localhost
```

## 📊 性能验证

安装成功后，您可以验证缓存性能：

### 网络面板检查
1. 打开F12开发者工具
2. 切换到Network面板
3. 刷新页面
4. 查看请求是否显示"from ServiceWorker"标记

### 缓存详情查看
```javascript
// 在浏览器Console中执行
// 查看Service Worker状态
navigator.serviceWorker.getRegistration('/').then(reg => console.log(reg));

// 查看所有缓存
caches.keys().then(names => console.log('缓存列表:', names));

// 查看特定缓存内容
caches.open('flarum-cache-v1.2').then(cache => 
  cache.keys().then(keys => console.log('缓存项目:', keys))
);
```

## 🔄 卸载方法

如果需要卸载扩展：

```bash
# 禁用扩展
php flarum extension:disable steperlin-service-worker-cache

# 移除扩展
composer remove steperlin/flarum-service-worker-cache

# 清理本地仓库配置
composer config --unset repositories.flarum-sw-cache

# 删除本地文件
rm -rf extensions/flarum-service-worker-cache

# 清除缓存
php flarum cache:clear
```

## 💡 开发模式

对于开发者，可以启用开发模式进行调试：

```bash
# 以开发模式安装
composer require steperlin/flarum-service-worker-cache:*@dev --prefer-source

# 启用Flarum调试模式
# 编辑 config.php
'debug' => true,

# 查看详细日志
tail -f storage/logs/flarum.log
```

## 📞 获取支持

如果遇到问题：

1. **检查日志** - `storage/logs/flarum.log`
2. **运行验证脚本** - `./install-verify.sh`
3. **查看GitHub Issues** - [报告问题](https://github.com/linkerlin/flarum-service-worker-cache/issues)
4. **联系开发者** - steper.lin@icloud.com

---

**注意**：这是临时的本地安装方法。一旦包发布到Packagist，您就可以使用标准的`composer require`命令进行安装。