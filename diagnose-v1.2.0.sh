#!/bin/bash

# v1.2.0 自动部署系统故障诊断脚本
# 深度分析自动部署机制的各个环节

echo "🔍 Flarum Service Worker Cache v1.2.0 - 自动部署系统诊断"
echo "================================================================"

# 检查当前目录
if [ ! -f "flarum" ] || [ ! -f "composer.json" ]; then
    echo "❌ 错误：请在 Flarum 根目录运行此脚本"
    exit 1
fi

echo ""
echo "📋 1. 基本环境检查"
echo "=================================="

# 检查扩展版本
echo "🔍 检查扩展版本..."
if grep -q '"steperlin/flarum-service-worker-cache"' composer.lock; then
    VERSION=$(grep -A 5 '"steperlin/flarum-service-worker-cache"' composer.lock | grep '"version"' | cut -d'"' -f4)
    echo "✅ 扩展版本: $VERSION"
    
    if [[ "$VERSION" == "1.2.0" ]]; then
        echo "✅ 版本正确: v1.2.0"
    else
        echo "⚠️  版本不匹配: 期望 1.2.0，实际 $VERSION"
    fi
else
    echo "❌ 扩展未安装或未锁定版本"
fi

# 检查扩展是否启用
echo ""
echo "🔍 检查扩展启用状态..."
if php flarum extension:list | grep -q "steperlin-service-worker-cache.*enabled"; then
    echo "✅ 扩展已启用"
else
    echo "❌ 扩展未启用"
    echo "💡 请运行: php flarum extension:enable steperlin-service-worker-cache"
fi

echo ""
echo "📋 2. 自动部署机制检查"
echo "=================================="

# 检查 Composer Scripts
echo "🔍 检查 Composer Scripts 配置..."
if grep -q "deploy-service-worker" composer.json; then
    echo "✅ Composer Scripts 已配置"
    if grep -q "post-install-cmd" composer.json && grep -q "post-update-cmd" composer.json; then
        echo "✅ 安装/更新钩子已设置"
    else
        echo "⚠️  安装/更新钩子可能缺失"
    fi
else
    echo "❌ Composer Scripts 未配置"
fi

# 检查 Installer 类
echo ""
echo "🔍 检查 Installer 类..."
INSTALLER_PATHS=(
    "vendor/steperlin/flarum-service-worker-cache/src/Installer.php"
    "extensions/flarum-service-worker-cache/src/Installer.php"
)

INSTALLER_FOUND=false
for path in "${INSTALLER_PATHS[@]}"; do
    if [ -f "$path" ]; then
        echo "✅ Installer 类存在: $path"
        INSTALLER_FOUND=true
        INSTALLER_PATH="$path"
        break
    fi
done

if [ "$INSTALLER_FOUND" = false ]; then
    echo "❌ Installer 类未找到"
fi

# 检查 Service Worker 源文件
echo ""
echo "🔍 检查 Service Worker 源文件..."
SW_PATHS=(
    "vendor/steperlin/flarum-service-worker-cache/service-worker.js"
    "extensions/flarum-service-worker-cache/service-worker.js"
)

SW_SOURCE=""
for path in "${SW_PATHS[@]}"; do
    if [ -f "$path" ]; then
        echo "✅ Service Worker 源文件存在: $path"
        SW_SOURCE="$path"
        FILE_SIZE=$(stat -c%s "$path" 2>/dev/null || stat -f%z "$path")
        echo "📊 文件大小: $FILE_SIZE 字节"
        
        # 检查文件内容版本
        if grep -q "v1.2.0" "$path"; then
            echo "✅ 文件版本标识正确: v1.2.0"
        else
            echo "⚠️  文件版本标识可能不正确"
        fi
        break
    fi
done

if [ -z "$SW_SOURCE" ]; then
    echo "❌ Service Worker 源文件未找到"
fi

echo ""
echo "📋 3. 部署状态检查"
echo "=================================="

# 检查 public 目录
echo "🔍 检查 public 目录状态..."
if [ -f "public/service-worker.js" ]; then
    echo "✅ public/service-worker.js 存在"
    
    # 检查是否是符号链接
    if [ -L "public/service-worker.js" ]; then
        echo "🔗 文件类型: 符号链接"
        TARGET=$(readlink "public/service-worker.js")
        echo "🎯 链接目标: $TARGET"
        
        if [ -f "public/service-worker.js" ]; then
            echo "✅ 符号链接有效"
        else
            echo "❌ 符号链接损坏"
        fi
    else
        echo "📄 文件类型: 普通文件"
    fi
    
    # 检查文件权限
    PERMISSIONS=$(ls -la "public/service-worker.js" | awk '{print $1}')
    echo "🔐 文件权限: $PERMISSIONS"
    
    # 检查文件大小和内容
    PUBLIC_SIZE=$(stat -c%s "public/service-worker.js" 2>/dev/null || stat -f%z "public/service-worker.js")
    echo "📊 文件大小: $PUBLIC_SIZE 字节"
    
    if grep -q "v1.2.0" "public/service-worker.js" 2>/dev/null; then
        echo "✅ public 文件版本正确: v1.2.0"
    else
        echo "⚠️  public 文件版本可能不正确"
    fi
else
    echo "❌ public/service-worker.js 不存在"
fi

echo ""
echo "📋 4. HTTP 访问测试"
echo "=================================="

# 获取网站 URL
if [ -f "config.php" ]; then
    URL=$(php -r "
    \$config = include 'config.php';
    echo isset(\$config['url']) ? \$config['url'] : 'http://localhost';
    " 2>/dev/null)
else
    URL="http://localhost"
fi

echo "🌐 测试 URL: $URL/service-worker.js"

# HTTP 测试
if command -v curl >/dev/null 2>&1; then
    echo ""
    echo "🔍 执行 HTTP 测试..."
    
    # 详细的 curl 测试
    HTTP_RESPONSE=$(curl -I -s "$URL/service-worker.js" 2>&1)
    HTTP_CODE=$(echo "$HTTP_RESPONSE" | head -n1 | grep -o '[0-9]\{3\}' | head -n1)
    
    echo "📡 HTTP 状态码: $HTTP_CODE"
    
    if [ "$HTTP_CODE" = "200" ]; then
        echo "✅ HTTP 访问成功"
        
        # 检查响应头
        echo ""
        echo "📋 响应头信息:"
        echo "$HTTP_RESPONSE" | grep -i "content-type\|x-extension-version\|x-sw-source"
        
    elif [ "$HTTP_CODE" = "404" ]; then
        echo "❌ HTTP 404 错误 - 文件无法访问"
    else
        echo "⚠️  HTTP 状态码异常: $HTTP_CODE"
    fi
    
    echo ""
    echo "📋 完整响应头:"
    echo "$HTTP_RESPONSE"
else
    echo "⚠️  curl 不可用，跳过 HTTP 测试"
fi

echo ""
echo "📋 5. 中间件状态检查"
echo "=================================="

# 检查中间件类
MIDDLEWARE_PATHS=(
    "vendor/steperlin/flarum-service-worker-cache/src/Middleware/RegisterServiceWorker.php"
    "extensions/flarum-service-worker-cache/src/Middleware/RegisterServiceWorker.php"
)

MIDDLEWARE_FOUND=false
for path in "${MIDDLEWARE_PATHS[@]}"; do
    if [ -f "$path" ]; then
        echo "✅ 中间件类存在: $path"
        MIDDLEWARE_FOUND=true
        
        # 检查是否包含自动部署功能
        if grep -q "attemptAutoDeployment" "$path"; then
            echo "✅ 中间件包含自动部署功能"
        else
            echo "⚠️  中间件可能缺少自动部署功能"
        fi
        
        if grep -q "1.2.0" "$path"; then
            echo "✅ 中间件版本正确: v1.2.0"
        else
            echo "⚠️  中间件版本可能不正确"
        fi
        break
    fi
done

if [ "$MIDDLEWARE_FOUND" = false ]; then
    echo "❌ 中间件类未找到"
fi

echo ""
echo "📋 6. API 状态查询"
echo "=================================="

# 测试 API 状态接口
echo "🔍 测试 API 状态接口..."
if command -v curl >/dev/null 2>&1; then
    API_RESPONSE=$(curl -s "$URL/api/service-worker/status" 2>&1)
    
    if echo "$API_RESPONSE" | grep -q '"enabled"'; then
        echo "✅ API 接口响应正常"
        echo "📊 API 响应内容:"
        echo "$API_RESPONSE" | python -m json.tool 2>/dev/null || echo "$API_RESPONSE"
    else
        echo "❌ API 接口无响应或错误"
        echo "📋 响应内容: $API_RESPONSE"
    fi
else
    echo "⚠️  curl 不可用，跳过 API 测试"
fi

echo ""
echo "📋 7. 手动部署测试"
echo "=================================="

echo "🔍 尝试手动触发部署..."
if [ "$INSTALLER_FOUND" = true ] && [ -n "$SW_SOURCE" ]; then
    echo "🔧 执行 Installer::deployServiceWorker()..."
    
    php -r "
    require_once 'vendor/autoload.php';
    try {
        SteperLin\\ServiceWorkerCache\\Installer::deployServiceWorker();
        echo '✅ 手动部署执行成功' . PHP_EOL;
    } catch (Exception \$e) {
        echo '❌ 手动部署失败: ' . \$e->getMessage() . PHP_EOL;
    }
    " 2>&1
    
    # 再次检查 public 文件
    if [ -f "public/service-worker.js" ]; then
        echo "✅ 手动部署后 public 文件存在"
    else
        echo "❌ 手动部署后 public 文件仍不存在"
    fi
else
    echo "❌ 无法执行手动部署（缺少必要组件）"
fi

echo ""
echo "📋 8. 诊断总结和建议"
echo "=================================="

# 分析问题并给出建议
ISSUES_FOUND=0
SUGGESTIONS=()

if [ ! -f "public/service-worker.js" ]; then
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
    SUGGESTIONS+=("🔧 立即修复: 手动创建符号链接或复制文件到 public 目录")
fi

if [ "$HTTP_CODE" != "200" ]; then
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
    SUGGESTIONS+=("🌐 检查 Web 服务器配置，确保 .js 文件能正确提供")
fi

if [ "$INSTALLER_FOUND" = false ]; then
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
    SUGGESTIONS+=("📦 重新安装扩展: composer reinstall steperlin/flarum-service-worker-cache")
fi

echo "🎯 发现 $ISSUES_FOUND 个潜在问题"
echo ""

if [ ${#SUGGESTIONS[@]} -gt 0 ]; then
    echo "💡 建议的解决方案:"
    for suggestion in "${SUGGESTIONS[@]}"; do
        echo "   $suggestion"
    done
else
    echo "✅ 未发现明显问题，可能是缓存或网络问题"
fi

echo ""
echo "🚀 快速修复命令:"
echo "=================================="
echo "# 1. 立即修复部署"
echo "rm -f public/service-worker.js"
if [ -n "$SW_SOURCE" ]; then
    echo "ln -s ../$SW_SOURCE public/service-worker.js"
else
    echo "# 找到源文件路径后执行符号链接"
fi
echo ""
echo "# 2. 重启扩展"
echo "php flarum extension:disable steperlin-service-worker-cache"
echo "php flarum cache:clear"
echo "php flarum extension:enable steperlin-service-worker-cache"
echo "php flarum cache:clear"
echo ""
echo "# 3. 验证修复"
echo "curl -I \"$URL/service-worker.js\""

echo ""
echo "📞 如需进一步支持，请提供以上诊断信息。"