#!/bin/bash

# Flarum Service Worker Cache 自动诊断和修复脚本
# Version: 1.1.1
# Author: Steper Lin

echo "🔧 Flarum Service Worker Cache - 自动诊断和修复脚本"
echo "=================================================="
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日志函数
log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

# 检查是否在 Flarum 根目录
check_flarum_directory() {
    log_info "检查 Flarum 环境..."
    
    if [ ! -f "flarum" ]; then
        log_error "错误：当前目录不是 Flarum 根目录"
        log_info "请在 Flarum 根目录运行此脚本"
        exit 1
    fi
    
    if [ ! -f "composer.json" ]; then
        log_error "错误：找不到 composer.json 文件"
        exit 1
    fi
    
    log_success "Flarum 环境检查通过"
}

# 检查扩展安装状态
check_extension_installation() {
    log_info "检查扩展安装状态..."
    
    # 检查 composer.json 中是否有扩展
    if grep -q "steperlin/flarum-service-worker-cache" composer.json; then
        log_success "扩展已在 composer.json 中注册"
    else
        log_error "扩展未在 composer.json 中找到"
        return 1
    fi
    
    # 检查 vendor 目录中是否存在
    if [ -d "vendor/steperlin/flarum-service-worker-cache" ]; then
        log_success "扩展文件存在于 vendor 目录"
        EXTENSION_PATH="vendor/steperlin/flarum-service-worker-cache"
    elif [ -d "extensions/flarum-service-worker-cache" ]; then
        log_success "扩展文件存在于 extensions 目录"
        EXTENSION_PATH="extensions/flarum-service-worker-cache"
    else
        log_error "扩展文件未找到"
        return 1
    fi
    
    # 检查关键文件
    if [ -f "$EXTENSION_PATH/service-worker.js" ]; then
        log_success "Service Worker 文件存在: $EXTENSION_PATH/service-worker.js"
        SW_SOURCE_PATH="$EXTENSION_PATH/service-worker.js"
    else
        log_error "Service Worker 文件不存在"
        return 1
    fi
    
    if [ -f "$EXTENSION_PATH/extend.php" ]; then
        log_success "扩展配置文件存在"
    else
        log_error "扩展配置文件不存在"
        return 1
    fi
    
    return 0
}

# 检查扩展启用状态
check_extension_enabled() {
    log_info "检查扩展启用状态..."
    
    if php flarum extension:list | grep -q "steperlin-service-worker-cache"; then
        if php flarum extension:list | grep "steperlin-service-worker-cache" | grep -q "enabled"; then
            log_success "扩展已启用"
            return 0
        else
            log_warning "扩展已安装但未启用"
            return 1
        fi
    else
        log_error "扩展未找到或未安装"
        return 1
    fi
}

# 启用扩展
enable_extension() {
    log_info "启用扩展..."
    
    if php flarum extension:enable steperlin-service-worker-cache; then
        log_success "扩展启用成功"
        return 0
    else
        log_error "扩展启用失败"
        return 1
    fi
}

# 检查 Service Worker 文件访问性
check_service_worker_access() {
    log_info "检查 Service Worker 文件访问性..."
    
    # 获取网站 URL
    if [ -f "config.php" ]; then
        URL=$(php -r "
        \$config = include 'config.php';
        echo isset(\$config['url']) ? \$config['url'] : 'http://localhost';
        ")
    else
        log_warning "无法读取 config.php，使用默认 URL"
        URL="http://localhost"
    fi
    
    log_info "测试 URL: $URL/service-worker.js"
    
    # 测试访问
    if command -v curl >/dev/null 2>&1; then
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$URL/service-worker.js")
        if [ "$HTTP_CODE" = "200" ]; then
            log_success "Service Worker 文件可通过 HTTP 访问 (状态码: $HTTP_CODE)"
            return 0
        else
            log_error "Service Worker 文件无法访问 (状态码: $HTTP_CODE)"
            return 1
        fi
    else
        log_warning "curl 命令不可用，跳过 HTTP 测试"
        return 1
    fi
}

# 修复方案 1：重新启用扩展
fix_re_enable_extension() {
    log_info "执行修复方案 1：重新启用扩展..."
    
    log_info "禁用扩展..."
    php flarum extension:disable steperlin-service-worker-cache
    
    log_info "清除缓存..."
    php flarum cache:clear
    
    log_info "启用扩展..."
    if php flarum extension:enable steperlin-service-worker-cache; then
        log_success "扩展重新启用成功"
        
        log_info "再次清除缓存..."
        php flarum cache:clear
        
        return 0
    else
        log_error "扩展重新启用失败"
        return 1
    fi
}

# 修复方案 2：创建符号链接
fix_create_symlink() {
    log_info "执行修复方案 2：创建符号链接..."
    
    if [ -z "$SW_SOURCE_PATH" ]; then
        log_error "Service Worker 源文件路径未知"
        return 1
    fi
    
    # 删除可能存在的旧文件
    if [ -f "public/service-worker.js" ]; then
        log_info "删除现有的 service-worker.js 文件..."
        rm -f "public/service-worker.js"
    fi
    
    # 创建符号链接
    log_info "创建符号链接: public/service-worker.js -> $SW_SOURCE_PATH"
    if ln -s "../$SW_SOURCE_PATH" "public/service-worker.js"; then
        log_success "符号链接创建成功"
        
        # 验证链接
        if [ -L "public/service-worker.js" ] && [ -f "public/service-worker.js" ]; then
            log_success "符号链接验证通过"
            return 0
        else
            log_error "符号链接验证失败"
            return 1
        fi
    else
        log_error "符号链接创建失败"
        return 1
    fi
}

# 修复方案 3：直接复制文件
fix_copy_file() {
    log_info "执行修复方案 3：直接复制文件..."
    
    if [ -z "$SW_SOURCE_PATH" ]; then
        log_error "Service Worker 源文件路径未知"
        return 1
    fi
    
    log_info "复制文件: $SW_SOURCE_PATH -> public/service-worker.js"
    if cp "$SW_SOURCE_PATH" "public/service-worker.js"; then
        log_success "文件复制成功"
        
        # 设置权限
        chmod 644 "public/service-worker.js"
        log_success "文件权限设置完成"
        
        return 0
    else
        log_error "文件复制失败"
        return 1
    fi
}

# 验证修复结果
verify_fix() {
    log_info "验证修复结果..."
    
    # 检查文件是否存在
    if [ -f "public/service-worker.js" ]; then
        log_success "public/service-worker.js 文件存在"
        
        # 检查文件大小
        FILE_SIZE=$(stat -c%s "public/service-worker.js" 2>/dev/null || stat -f%z "public/service-worker.js" 2>/dev/null)
        if [ "$FILE_SIZE" -gt 1000 ]; then
            log_success "文件大小正常: $FILE_SIZE 字节"
        else
            log_warning "文件大小可能异常: $FILE_SIZE 字节"
        fi
        
        # 检查文件内容
        if grep -q "Flarum Service Worker" "public/service-worker.js"; then
            log_success "文件内容验证通过"
        else
            log_warning "文件内容可能异常"
        fi
        
        return 0
    else
        log_error "public/service-worker.js 文件不存在"
        return 1
    fi
}

# 显示后续步骤
show_next_steps() {
    echo ""
    echo "🎉 修复完成！请执行以下步骤验证："
    echo "=================================="
    echo ""
    echo "1. 打开浏览器，访问您的论坛网站"
    echo "2. 按 F12 打开开发者工具"
    echo "3. 查看 Console 选项卡，应该看到："
    echo "   [SW Cache] 🚀 Initializing Service Worker registration..."
    echo "   [SW Cache] ✅ Service Worker registered successfully"
    echo ""
    echo "4. 检查 Network 选项卡，确认 service-worker.js 返回 200 状态码"
    echo ""
    echo "5. 在 Application > Service Workers 中应该看到已注册的 Service Worker"
    echo ""
    echo "如果仍有问题，请查看详细调试指南："
    echo "cat SERVICE_WORKER_DEBUG.md"
    echo ""
}

# 主函数
main() {
    check_flarum_directory
    
    if ! check_extension_installation; then
        log_error "扩展安装检查失败，请确保扩展已正确安装"
        exit 1
    fi
    
    if ! check_extension_enabled; then
        log_warning "扩展未启用，尝试启用..."
        if ! enable_extension; then
            log_error "扩展启用失败"
            exit 1
        fi
    fi
    
    # 检查 Service Worker 访问性
    if check_service_worker_access; then
        log_success "Service Worker 已可正常访问，无需修复"
        show_next_steps
        exit 0
    fi
    
    log_warning "Service Worker 访问失败，开始修复..."
    
    # 尝试修复方案 1：重新启用扩展
    if fix_re_enable_extension; then
        if check_service_worker_access; then
            log_success "修复方案 1 成功！"
            show_next_steps
            exit 0
        fi
    fi
    
    # 尝试修复方案 2：创建符号链接
    if fix_create_symlink; then
        if verify_fix; then
            log_success "修复方案 2 成功！"
            show_next_steps
            exit 0
        fi
    fi
    
    # 尝试修复方案 3：直接复制文件
    if fix_copy_file; then
        if verify_fix; then
            log_success "修复方案 3 成功！"
            show_next_steps
            exit 0
        fi
    fi
    
    log_error "所有自动修复方案均失败"
    echo ""
    echo "请手动检查以下内容："
    echo "1. Web 服务器配置是否正确"
    echo "2. 文件权限是否正确"
    echo "3. Flarum 路由是否正常工作"
    echo ""
    echo "详细调试指南请查看："
    echo "cat SERVICE_WORKER_DEBUG.md"
    
    exit 1
}

# 运行主函数
main "$@"