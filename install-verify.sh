#!/bin/bash

# Flarum Service Worker Cache Extension - Installation Verification Script
# 用于验证扩展是否正确安装和配置

set -e

echo "🚀 Flarum Service Worker Cache Extension - Installation Verification"
echo "=================================================================="

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 检查函数
check_status() {
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ $1${NC}"
    else
        echo -e "${RED}❌ $1${NC}"
        exit 1
    fi
}

warn() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# 检查PHP版本
echo "📋 Checking PHP version..."
php_version=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1-2)
php_major=$(echo "$php_version" | cut -d "." -f 1)
php_minor=$(echo "$php_version" | cut -d "." -f 2)

# 检查是否支持Flarum 2.0
if [ "$php_major" -gt 8 ] || ([ "$php_major" -eq 8 ] && [ "$php_minor" -ge 1 ]); then
    check_status "PHP version $php_version supports Flarum 2.0 (PHP 8.1+ required)"
    FLARUM_2_COMPATIBLE=true
elif [ "$(echo "$php_version >= 7.4" | bc 2>/dev/null || echo 0)" -eq 1 ]; then
    check_status "PHP version $php_version supports Flarum 1.x (legacy support)"
    warn "For Flarum 2.0 support, upgrade to PHP 8.1+"
    FLARUM_2_COMPATIBLE=false
else
    echo -e "${RED}❌ PHP version $php_version is not supported. Requires PHP 7.4+ (legacy) or PHP 8.1+ (Flarum 2.0)${NC}"
    exit 1
fi

# 检查Composer
echo "📦 Checking Composer..."
if command -v composer &> /dev/null; then
    check_status "Composer is installed"
else
    echo -e "${RED}❌ Composer is not installed${NC}"
    exit 1
fi

# 检查Flarum
echo "🏛️ Checking Flarum..."
# 检查Flarum
echo "🏗️ Checking Flarum..."
if [ -f "composer.json" ] && grep -q "flarum/core" composer.json; then
    flarum_version=$(grep '"flarum/core"' composer.lock 2>/dev/null | head -n 1 | sed 's/.*"version": "\([^"]*\)".*/\1/' || echo "unknown")
    flarum_constraint=$(grep '"flarum/core"' composer.json | sed 's/.*"flarum\/core": "\([^"]*\)".*/\1/')
    
    if echo "$flarum_constraint" | grep -q "2.0"; then
        check_status "Flarum 2.0 Beta detected (constraint: $flarum_constraint)"
        if [ "$FLARUM_2_COMPATIBLE" = "true" ]; then
            check_status "PHP version compatible with Flarum 2.0"
        else
            warn "PHP version may not be optimal for Flarum 2.0. Upgrade to PHP 8.1+ recommended"
        fi
    else
        check_status "Flarum 1.x detected (constraint: $flarum_constraint, version: $flarum_version)"
        if [ "$FLARUM_2_COMPATIBLE" = "true" ]; then
            info "Your PHP version supports Flarum 2.0 upgrade when ready"
        fi
    fi
else
    warn "Flarum not detected in current directory"
    echo "Please run this script from your Flarum root directory"
fi

# 检查扩展是否已安装
echo "🔍 Checking extension installation..."
if grep -q "steperlin/flarum-service-worker-cache" composer.json; then
    check_status "Extension found in composer.json"
else
    warn "Extension not found in composer.json"
    echo "To install: composer require steperlin/flarum-service-worker-cache"
fi

# 检查vendor目录
if [ -d "vendor/steperlin/flarum-service-worker-cache" ]; then
    check_status "Extension files found in vendor directory"
    
    # 检查关键文件
    extension_path="vendor/steperlin/flarum-service-worker-cache"
    
    if [ -f "$extension_path/extend.php" ]; then
        check_status "Extension configuration file exists"
    fi
    
    if [ -f "$extension_path/service-worker.js" ]; then
        check_status "Service Worker file exists"
    fi
    
    if [ -d "$extension_path/src" ]; then
        check_status "Source directory exists"
    fi
else
    warn "Extension files not found in vendor directory"
fi

# 检查扩展是否已启用
echo "⚡ Checking extension status..."
if [ -f "storage/flarum.db" ]; then
    # SQLite数据库检查
    if command -v sqlite3 &> /dev/null; then
        enabled=$(sqlite3 storage/flarum.db "SELECT name FROM extensions WHERE name='steperlin-service-worker-cache';" 2>/dev/null || echo "")
        if [ ! -z "$enabled" ]; then
            check_status "Extension is enabled in database"
        else
            warn "Extension is not enabled. Run: php flarum extension:enable steperlin-service-worker-cache"
        fi
    else
        warn "sqlite3 command not found, cannot check extension status"
    fi
elif [ -f "config.php" ]; then
    # 检查MySQL连接信息（更复杂，这里简化处理）
    info "MySQL database detected, manual status check recommended"
else
    warn "Database not found, cannot check extension status"
fi

# 检查Web服务器配置
echo "🌐 Checking web server configuration..."
if [ -f ".htaccess" ]; then
    check_status "Apache .htaccess file found"
elif [ -f "public/.htaccess" ]; then
    check_status "Apache .htaccess file found in public directory"
else
    warn "Web server configuration not detected"
fi

# 检查Service Worker可访问性
echo "🔧 Checking Service Worker accessibility..."
info "The Service Worker should be accessible at: https://yourdomain.com/service-worker.js"
info "You can test this manually after installation"

# 权限检查
echo "🔐 Checking file permissions..."
if [ -w "." ]; then
    check_status "Directory is writable"
else
    warn "Current directory is not writable"
fi

if [ -d "storage" ] && [ -w "storage" ]; then
    check_status "Storage directory is writable"
else
    warn "Storage directory is not writable or doesn't exist"
fi

# HTTPS检查提醒
echo "🔒 HTTPS Requirement Notice:"
info "Service Workers require HTTPS in production environments"
info "Make sure your site is served over HTTPS for the extension to work properly"

# 总结
echo ""
echo "🎉 Installation Verification Complete!"
echo "================================================"
echo ""
echo "Next steps:"
echo "1. If not already done: composer require steperlin/flarum-service-worker-cache"
echo "2. Enable the extension: php flarum extension:enable steperlin-service-worker-cache"
echo "3. Clear cache: php flarum cache:clear"
echo "4. Visit your forum and check browser console for Service Worker logs"
echo ""
echo "For debugging, open browser Developer Tools and check:"
echo "- Console tab for Service Worker registration logs"
echo "- Application/Storage tab for cache status"
echo "- Network tab to verify caching behavior"
echo ""
echo "Need help? Visit: https://github.com/linkerlin/flarum-service-worker-cache/issues"