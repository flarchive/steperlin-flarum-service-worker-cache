#!/bin/bash
# 一键修复 Service Worker 404 问题

echo "🔧 修复 Service Worker 404 问题..."

# 检查环境
if [ ! -f "flarum" ]; then
    echo "❌ 请在 Flarum 根目录运行"
    exit 1
fi

# 查找源文件
if [ -f "vendor/steperlin/flarum-service-worker-cache/service-worker.js" ]; then
    SW_SOURCE="vendor/steperlin/flarum-service-worker-cache/service-worker.js"
elif [ -f "extensions/flarum-service-worker-cache/service-worker.js" ]; then
    SW_SOURCE="extensions/flarum-service-worker-cache/service-worker.js"
else
    echo "❌ 未找到 Service Worker 文件"
    exit 1
fi

echo "📁 源文件: $SW_SOURCE"

# 删除旧文件
[ -f "public/service-worker.js" ] && rm -f "public/service-worker.js"

# 创建符号链接或复制文件
if ln -s "../$SW_SOURCE" "public/service-worker.js" 2>/dev/null; then
    echo "✅ 符号链接创建成功"
elif cp "$SW_SOURCE" "public/service-worker.js"; then
    echo "✅ 文件复制成功"
    chmod 644 "public/service-worker.js"
else
    echo "❌ 部署失败"
    exit 1
fi

# 验证
if [ -f "public/service-worker.js" ]; then
    SIZE=$(ls -lh "public/service-worker.js" | awk '{print $5}')
    echo "✅ 验证通过，文件大小: $SIZE"
else
    echo "❌ 验证失败"
    exit 1
fi

# 重启扩展
echo "🔄 重启扩展..."
php flarum extension:disable steperlin-service-worker-cache >/dev/null 2>&1
php flarum cache:clear >/dev/null 2>&1
php flarum extension:enable steperlin-service-worker-cache >/dev/null 2>&1
php flarum cache:clear >/dev/null 2>&1

echo "🎉 修复完成！请刷新浏览器测试。"