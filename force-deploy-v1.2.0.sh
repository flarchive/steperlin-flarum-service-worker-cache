#!/bin/bash

# v1.2.0 强制部署修复脚本
# 专门针对自动部署系统失效的情况

set -e  # 遇到错误立即停止

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

log_info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
log_success() { echo -e "${GREEN}✅ $1${NC}"; }
log_warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
log_error() { echo -e "${RED}❌ $1${NC}"; }
log_highlight() { echo -e "${PURPLE}🎯 $1${NC}"; }

echo "🚀 Flarum Service Worker Cache v1.2.0 - 强制部署修复脚本"
echo "=============================================================="
echo ""

# 检查环境
log_info "检查 Flarum 环境..."
if [ ! -f "flarum" ] || [ ! -f "composer.json" ]; then
    log_error "请在 Flarum 根目录运行此脚本"
    exit 1
fi

# 检查扩展版本
log_info "验证扩展版本..."
if grep -q '"steperlin/flarum-service-worker-cache"' composer.lock; then
    VERSION=$(grep -A 5 '"steperlin/flarum-service-worker-cache"' composer.lock | grep '"version"' | cut -d'"' -f4)
    if [[ "$VERSION" == "1.2.0" ]]; then
        log_success "扩展版本正确: v1.2.0"
    else
        log_warning "扩展版本: $VERSION (期望 1.2.0)"
    fi
else
    log_error "扩展未正确安装"
    exit 1
fi

# 查找 Service Worker 源文件
log_info "查找 Service Worker 源文件..."
SW_SOURCE=""
POSSIBLE_PATHS=(
    "vendor/steperlin/flarum-service-worker-cache/service-worker.js"
    "extensions/flarum-service-worker-cache/service-worker.js"
    "extensions/steperlin-service-worker-cache/service-worker.js"
)

for path in "${POSSIBLE_PATHS[@]}"; do
    if [ -f "$path" ]; then
        SW_SOURCE="$path"
        log_success "找到源文件: $SW_SOURCE"
        
        # 验证文件版本
        if grep -q "v1.2.0" "$path"; then
            log_success "源文件版本正确: v1.2.0"
        else
            log_warning "源文件版本可能不正确"
        fi
        break
    fi
done

if [ -z "$SW_SOURCE" ]; then
    log_error "未找到 Service Worker 源文件"
    exit 1
fi

# 备份现有文件（如果存在）
log_info "处理现有部署文件..."
if [ -f "public/service-worker.js" ]; then
    BACKUP_NAME="service-worker.js.backup.$(date +%Y%m%d_%H%M%S)"
    mv "public/service-worker.js" "public/$BACKUP_NAME"
    log_success "现有文件已备份为: public/$BACKUP_NAME"
fi

# 尝试创建符号链接
log_info "尝试创建符号链接..."
RELATIVE_PATH=$(python -c "
import os
print(os.path.relpath('$SW_SOURCE', 'public'))
" 2>/dev/null || echo "../$SW_SOURCE")

if ln -s "$RELATIVE_PATH" "public/service-worker.js" 2>/dev/null; then
    log_success "符号链接创建成功"
    DEPLOY_METHOD="symlink"
else
    log_warning "符号链接创建失败，尝试文件复制..."
    
    if cp "$SW_SOURCE" "public/service-worker.js"; then
        chmod 644 "public/service-worker.js"
        log_success "文件复制成功"
        DEPLOY_METHOD="copy"
    else
        log_error "文件复制失败"
        exit 1
    fi
fi

# 验证部署
log_info "验证部署结果..."
if [ -f "public/service-worker.js" ]; then
    FILE_SIZE=$(stat -c%s "public/service-worker.js" 2>/dev/null || stat -f%z "public/service-worker.js")
    log_success "部署验证通过"
    log_info "文件大小: $FILE_SIZE 字节"
    log_info "部署方式: $DEPLOY_METHOD"
    
    # 检查内容
    if grep -q "v1.2.0" "public/service-worker.js"; then
        log_success "文件内容版本正确: v1.2.0"
    else
        log_warning "文件内容版本可能不正确"
    fi
else
    log_error "部署验证失败"
    exit 1
fi

# 测试自动部署 API（如果可用）
log_info "测试自动部署功能..."
if php -r "
require_once 'vendor/autoload.php';
try {
    \$status = SteperLin\\ServiceWorkerCache\\Installer::getStatus();
    echo 'API Status: ' . json_encode(\$status) . PHP_EOL;
} catch (Exception \$e) {
    echo 'API Error: ' . \$e->getMessage() . PHP_EOL;
}
" 2>/dev/null; then
    log_success "自动部署 API 可用"
else
    log_warning "自动部署 API 不可用"
fi

# 重启扩展
log_info "重启扩展以确保配置生效..."
php flarum extension:disable steperlin-service-worker-cache >/dev/null 2>&1 || true
sleep 1
php flarum cache:clear >/dev/null 2>&1
sleep 1
php flarum extension:enable steperlin-service-worker-cache >/dev/null 2>&1
sleep 1
php flarum cache:clear >/dev/null 2>&1

log_success "扩展重启完成"

# HTTP 访问测试
log_info "测试 HTTP 访问..."
if command -v curl >/dev/null 2>&1; then
    # 获取网站 URL
    if [ -f "config.php" ]; then
        URL=$(php -r "
        \$config = include 'config.php';
        echo isset(\$config['url']) ? \$config['url'] : 'http://localhost';
        " 2>/dev/null || echo "http://localhost")
    else
        URL="http://localhost"
    fi
    
    log_info "测试 URL: $URL/service-worker.js"
    
    # 执行 HTTP 测试
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$URL/service-worker.js" 2>/dev/null || echo "000")
    
    if [ "$HTTP_CODE" = "200" ]; then
        log_success "HTTP 访问测试通过 (状态码: $HTTP_CODE)"
        
        # 获取响应头信息
        HEADERS=$(curl -I -s "$URL/service-worker.js" 2>/dev/null)
        if echo "$HEADERS" | grep -q "X-Extension-Version: 1.2.0"; then
            log_success "响应头版本正确: v1.2.0"
        fi
        
        if echo "$HEADERS" | grep -q "X-SW-Source"; then
            SW_SOURCE_HEADER=$(echo "$HEADERS" | grep "X-SW-Source" | cut -d: -f2 | tr -d ' \r')
            log_info "Service Worker 来源: $SW_SOURCE_HEADER"
        fi
        
    else
        log_warning "HTTP 访问测试失败 (状态码: $HTTP_CODE)"
        log_info "可能需要检查 Web 服务器配置"
    fi
else
    log_warning "curl 不可用，跳过 HTTP 测试"
fi

# 创建验证脚本
log_info "创建持续验证脚本..."
cat > verify-service-worker.sh << 'EOF'
#!/bin/bash
echo "🔍 Service Worker 状态验证"
echo "========================="

# 检查文件
if [ -f "public/service-worker.js" ]; then
    if [ -L "public/service-worker.js" ]; then
        echo "✅ 文件存在 (符号链接)"
    else
        echo "✅ 文件存在 (普通文件)"
    fi
    
    SIZE=$(stat -c%s "public/service-worker.js" 2>/dev/null || stat -f%z "public/service-worker.js")
    echo "📊 文件大小: $SIZE 字节"
else
    echo "❌ 文件不存在"
fi

# HTTP 测试
if command -v curl >/dev/null 2>&1; then
    URL=$(php -r "
    \$config = include 'config.php';
    echo isset(\$config['url']) ? \$config['url'] : 'http://localhost';
    " 2>/dev/null || echo "http://localhost")
    
    CODE=$(curl -s -o /dev/null -w "%{http_code}" "$URL/service-worker.js")
    echo "🌐 HTTP 状态: $CODE"
fi
EOF

chmod +x verify-service-worker.sh
log_success "验证脚本已创建: verify-service-worker.sh"

echo ""
log_highlight "🎉 强制部署修复完成！"
echo "=================================="
echo "✅ Service Worker 已成功部署到 public 目录"
echo "✅ 扩展已重新启用并清除缓存"
echo "✅ 部署方式: $DEPLOY_METHOD"
echo ""
echo "🔍 后续验证步骤:"
echo "1. 刷新浏览器页面"
echo "2. 检查浏览器控制台，应看到:"
echo "   [SW Cache] ✅ Service Worker registered successfully"
echo "3. 运行验证脚本: ./verify-service-worker.sh"
echo ""
echo "💡 如果问题仍然存在，可能的原因:"
echo "   - 浏览器缓存了旧的错误"
echo "   - Web 服务器配置问题"
echo "   - CDN 或代理缓存问题"
echo ""
echo "🆘 紧急情况下，可以清理浏览器缓存并强制刷新页面 (Ctrl+F5)"

# 最后提示
echo ""
log_info "部署详情:"
echo "   源文件: $SW_SOURCE"
echo "   目标文件: public/service-worker.js"
echo "   部署方式: $DEPLOY_METHOD"
echo "   文件大小: $FILE_SIZE 字节"