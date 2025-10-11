# Flarum 根目录检测修复指南 v1.2.2

**问题**: 用户在插件目录内运行诊断工具，导致路径检测错误  
**版本**: v1.2.2  
**日期**: 2025年10月11日

## 🎯 问题分析

### 原始问题
用户报告的诊断结果显示：
```
诊断目标: /var/www/beiduofen/vendor/steperlin/flarum-service-worker-cache/public/service-worker.js
工作目录: /var/www/beiduofen/vendor/steperlin/flarum-service-worker-cache
```

**错误原因**: 诊断工具在插件目录内运行，错误地将插件目录识别为 Flarum 根目录。

### 正确的路径结构
```
Flarum 项目结构:
/var/www/beiduofen/                           # ← Flarum 根目录
├── public/                                   # ← 目标部署位置
│   └── service-worker.js                     # ← 应该部署到这里
├── vendor/
│   └── steperlin/
│       └── flarum-service-worker-cache/      # ← 插件目录
│           ├── service-worker.js             # ← 源文件
│           └── src/Listeners/...
├── flarum                                    # ← Flarum 命令行工具
└── extend.php                               # ← 可能存在的扩展配置
```

## 🔧 完整修复方案

### 步骤 1: 更新后的智能路径检测

v1.2.2 版本的 `ExtensionLifecycleListener` 现在包含增强的路径检测逻辑：

```php
/**
 * 获取 Flarum 根目录 - v1.2.2 增强版
 * 
 * 智能识别 Flarum 根目录，支持多种部署场景：
 * 1. 从插件目录向上查找（../../../）
 * 2. 从当前工作目录检测
 * 3. 通过特征文件识别（flarum 可执行文件）
 */
private function getWorkingDirectory(): string
{
    // 方法1: 从当前类文件位置向上查找
    $currentFile = __FILE__;
    $extensionRoot = dirname(dirname($currentFile));
    $flarumRoot = dirname(dirname(dirname($extensionRoot))); // 向上3级
    
    if ($this->isFlarumRoot($flarumRoot)) {
        return $flarumRoot;
    }
    
    // 方法2: 从当前工作目录检测
    $cwd = getcwd();
    if ($cwd && $this->isFlarumRoot($cwd)) {
        return $cwd;
    }
    
    // 方法3: 向上搜索直到找到 Flarum 根目录
    if ($cwd) {
        $pathParts = explode('/', trim($cwd, '/'));
        for ($i = count($pathParts); $i >= 1; $i--) {
            $testPath = '/' . implode('/', array_slice($pathParts, 0, $i));
            if ($this->isFlarumRoot($testPath)) {
                return $testPath;
            }
        }
    }
    
    // 方法4: 特殊情况 - 从插件目录计算
    if ($cwd && strpos($cwd, 'vendor/steperlin/flarum-service-worker-cache') !== false) {
        $pluginPath = $cwd;
        $vendorPath = dirname(dirname($pluginPath));
        $flarumRoot = dirname($vendorPath);
        
        if ($this->isFlarumRoot($flarumRoot)) {
            return $flarumRoot;
        }
    }
    
    return $cwd ?: '/tmp';
}

/**
 * 检查指定目录是否为 Flarum 根目录
 */
private function isFlarumRoot(string $path): bool
{
    if (!$path || !is_dir($path)) {
        return false;
    }
    
    $flarumIndicators = [
        $path . '/flarum',           // Flarum 可执行文件
        $path . '/public/index.php', // 入口文件
        $path . '/vendor/flarum/core', // Flarum 核心
        $path . '/extend.php',       // 扩展配置
    ];
    
    foreach ($flarumIndicators as $indicator) {
        if (file_exists($indicator)) {
            return true;
        }
    }
    
    return false;
}
```

### 步骤 2: 正确的使用方法

#### 2.1 诊断工具正确用法

**❌ 错误用法** (在插件目录内运行):
```bash
root@server:~/beiduofen/vendor/steperlin/flarum-service-worker-cache# 
php diagnose-deployment-v1.2.2.php
```

**✅ 正确用法** (在 Flarum 根目录运行):
```bash
# 方法1: 在 Flarum 根目录运行
root@server:~/beiduofen# php vendor/steperlin/flarum-service-worker-cache/diagnose-deployment-v1.2.2.php

# 方法2: 复制到根目录后运行
root@server:~/beiduofen# cp vendor/steperlin/flarum-service-worker-cache/diagnose-deployment-v1.2.2.php .
root@server:~/beiduofen# php diagnose-deployment-v1.2.2.php
```

#### 2.2 强制部署工具正确用法

**✅ 正确的修复命令**:
```bash
# 进入 Flarum 根目录
cd /var/www/beiduofen

# 运行强制部署修复
php vendor/steperlin/flarum-service-worker-cache/force-deploy-fix-v1.2.2.php

# 或者复制工具到根目录
cp vendor/steperlin/flarum-service-worker-cache/force-deploy-fix-v1.2.2.php .
php force-deploy-fix-v1.2.2.php
```

### 步骤 3: 验证修复结果

```bash
# 1. 检查 Flarum 根目录
pwd
# 应该显示: /var/www/beiduofen

# 2. 验证 Flarum 特征文件存在
ls -la flarum public/index.php vendor/flarum/

# 3. 运行更新后的诊断工具
php vendor/steperlin/flarum-service-worker-cache/diagnose-deployment-v1.2.2.php

# 4. 检查目标文件是否正确部署
ls -la public/service-worker.js

# 5. 测试 HTTP 访问
curl -I https://beiduofen.top/service-worker.js
```

### 步骤 4: 扩展重新启用（确保监听器触发）

```bash
# 在 Flarum 根目录执行
cd /var/www/beiduofen

# 禁用扩展
php flarum extension:disable steperlin-service-worker-cache

# 重新启用扩展（触发自动部署）
php flarum extension:enable steperlin-service-worker-cache

# 清除缓存
php flarum cache:clear
```

## 🧪 测试新的路径检测逻辑

使用包含的测试工具验证路径检测：

```bash
# 在 Flarum 根目录运行测试
cd /var/www/beiduofen
php vendor/steperlin/flarum-service-worker-cache/test-path-detection-v1.2.2.php
```

**预期输出**:
```
🔍 Flarum 根目录检测测试工具 v1.2.2
=====================================

📋 检测结果摘要:
检测到的 Flarum 根目录: /var/www/beiduofen
是否为有效的 Flarum 根目录: ✅ 是

🎯 测试目标路径:
Public 目录: /var/www/beiduofen/public
- 存在: ✅ 是
- 可写: ✅ 是

目标文件: /var/www/beiduofen/public/service-worker.js
- 存在: ✅ 是 (修复后)
```

## 🔍 高级故障排除

### 问题 1: 路径检测仍然失败

**症状**: 即使使用新版本，路径检测仍然错误

**解决方案**:
```bash
# 1. 确认当前目录
pwd

# 2. 手动检查 Flarum 特征文件
ls -la flarum public/index.php vendor/flarum/

# 3. 如果特征文件不存在，可能不在正确的 Flarum 安装目录
find / -name "flarum" -type f 2>/dev/null | head -5
```

### 问题 2: 权限问题

**症状**: public 目录不可写

**解决方案**:
```bash
# 检查目录权限
ls -ld public/

# 设置正确权限
chmod 755 public/
chown www-data:www-data public/ # 根据服务器配置调整

# 如果需要，创建目录
mkdir -p public/
```

### 问题 3: 多个 Flarum 安装

**症状**: 系统中有多个 Flarum 安装，工具检测到错误的实例

**解决方案**:
```bash
# 1. 确认正确的安装位置
find /var/www -name "flarum" -type f 2>/dev/null

# 2. 进入正确的目录
cd /var/www/beiduofen  # 替换为正确路径

# 3. 验证这是目标安装
cat config.php | grep url
```

## 📋 完整验证清单

执行以下检查确保修复完成：

- [ ] **环境准备**
  - [ ] 在 Flarum 根目录 (`/var/www/beiduofen`)
  - [ ] 确认 `flarum` 命令文件存在
  - [ ] 确认 `public/index.php` 存在

- [ ] **工具执行**
  - [ ] 运行路径检测测试工具
  - [ ] 运行诊断工具（应显示正确路径）
  - [ ] 运行强制部署工具（如需要）

- [ ] **扩展管理**
  - [ ] 禁用扩展
  - [ ] 重新启用扩展
  - [ ] 清除缓存

- [ ] **结果验证**
  - [ ] `public/service-worker.js` 文件存在
  - [ ] 文件大小正常（> 30KB）
  - [ ] HTTP 访问返回 200
  - [ ] 浏览器控制台显示注册成功

## 💡 防止再次出现的建议

1. **总是在 Flarum 根目录运行诊断工具**
2. **创建便捷的部署脚本**:
   ```bash
   #!/bin/bash
   # deploy-service-worker.sh
   cd /var/www/beiduofen
   php vendor/steperlin/flarum-service-worker-cache/force-deploy-fix-v1.2.2.php
   ```
3. **设置监控脚本定期检查**:
   ```bash
   #!/bin/bash
   # check-service-worker.sh
   if [ ! -f /var/www/beiduofen/public/service-worker.js ]; then
     echo "Service Worker file missing, triggering redeploy..."
     cd /var/www/beiduofen
     php flarum extension:disable steperlin-service-worker-cache
     php flarum extension:enable steperlin-service-worker-cache
   fi
   ```

---

通过这些增强的路径检测和修复机制，v1.2.2 版本确保了在各种部署环境中都能正确识别 Flarum 根目录并成功部署 Service Worker 文件。