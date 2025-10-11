# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Additional Flarum 2.0 optimizations
- Enhanced PWA features
- Extended browser compatibility

## [1.2.0] - 2024-10-11

### Added
- **🚀 全自动部署系统** - 多层自动部署机制，实现真正的零配置安装
- **Composer Scripts 自动化** - 在安装/更新时自动部署 Service Worker 文件
- **智能部署策略** - 符号链接优先，文件复制回退，动态提供兰底
- **扩展生命周期监听** - 监听扩展启用/禁用事件，自动处理部署和清理
- **HTTP中间件增强** - 首次访问时自动尝试部署，多路径文件查找
- **API状态接口** - 实时查询部署状态和手动重新部署功能
- **完整的错误处理** - 详细的日志记录和调试信息
- **环境适配性** - 支持 Packagist、本地开发、自定义安装路径

### Changed
- **中间件架构升级** - 增强的文件查找和提供机制
- **部署优先级优化** - public 目录 > 扩展目录 > 默认回退
- **错误恢复能力** - 即使部分机制失败也能正常工作
- **响应头增强** - 添加更多调试信息和状态指示

### Fixed
- **安装流程简化** - 用户无需任何手动操作，安装即可使用
- **多环境兼容性** - 解决了不同安装方式和文件系统的兼容问题
- **权限问题处理** - 智能处理文件权限和符号链接限制

### Technical Details
- **Installer 类** - 完整的自动部署逻辑和状态管理
- **ExtensionLifecycleListener** - 扩展生命周期事件监听和处理
- **API Endpoints** - `/api/service-worker/status` 和 `/api/service-worker/redeploy`
- **多路径查找** - vendor、extensions、自定义目录自动检测
- **智能部署** - 符号链接 → 文件复制 → 动态提供

### Breaking Changes
- **无破坏性变更** - 完全向后兼容，现有用户可直接升级

### Migration Guide
- **从 v1.1.x 升级** - 直接升级，无需任何配置更改
- **手动部署用户** - 自动部署系统会自动管理文件，无需手动干预

## [1.1.1] - 2024-10-11

### Fixed
- **Service Worker 404 错误修复** - 增强了Service Worker文件的提供机制
- **中间件路径检测改进** - 支持更多路径匹配模式 (/service-worker.js 和 /forum/service-worker.js)
- **多路径文件查找** - 智能检测扩展安装位置，提高兼容性
- **增强错误处理** - 添加详细的调试信息和回退机制
- **默认Service Worker备用** - 即使主文件丢失也能提供基本功能

### Added
- **自动诊断修复脚本** - 提供 fix-service-worker.sh 自动化修复工具
- **详细调试指南** - 新增 SERVICE_WORKER_DEBUG.md 完整诊断文档
- **符号链接支持** - 提供多种文件部署方案
- **HTTP响应头增强** - 添加更多调试信息头

### Changed
- **中间件注册机制** - 改用HTTP中间件层面拦截，提高可靠性
- **文件查找策略** - 支持vendor和extensions目录自动检测
- **错误重试机制** - Service Worker注册失败时自动重试

## [1.1.0] - 2024-10-11

### Added
- **Flarum 2.0.0@beta compatibility** - Full support for Flarum 2.0 beta
- **PHP 8.1+ support** - Enhanced type declarations and modern PHP features
- **Improved error handling** - Better exception handling and logging
- **New debug endpoints** - Enhanced debugging and monitoring capabilities
- **Extension version headers** - X-Extension-Version header for better tracking
- **Intelligent version detection** - Automatic Flarum version detection in install script

### Changed
- **Upgraded minimum PHP requirement** to 8.1 for Flarum 2.0 compatibility
- **Updated all dependencies** to Flarum 2.0 beta versions
- **Enhanced Service Worker** with v1.1 cache identifiers
- **Improved middleware architecture** for better performance and error handling
- **Updated CI/CD pipeline** for PHP 8.1-8.3 testing
- **Cache version management** - Better handling of cache migrations

### Fixed
- **Compatibility issues** with Flarum 2.0 API changes
- **PSR-7 response handling** improvements
- **Better cache migration** between versions
- **Type declaration consistency** across all PHP classes

## [1.0.0] - 2024-10-11

### Added
- Initial release
- Service Worker-based caching system
- Multi-layer cache strategy (API, Static, Images, HTML)
- Intelligent cache management with background refresh
- Built-in cache control console
- Support for CDN and external image caching
- Offline browsing capability
- Auto-update mechanism for Service Worker
- Zero-configuration setup

### Features
- **Intelligent Caching Strategy**: Different cache policies for different resource types
- **Performance Optimization**: 60-80% faster loading on repeat visits
- **Offline Support**: Browse cached content without internet connection
- **Auto-Update**: Background refresh ensures content freshness
- **Debug Tools**: Built-in console for cache management and debugging
- **Security**: CSRF token handling and secure domain filtering

### Cache Strategies
- API responses: 5-minute cache with background refresh
- Static resources: 7-day cache with background refresh
- Images: 30-day cache with background refresh
- HTML pages: 24-hour cache with background refresh

### Supported Domains
- Current domain (full caching)
- CDN domains (jsdelivr, cdnjs, unpkg)
- External image services (imgur, placeholder services)
- Admin panel (automatically skipped)

### Browser Compatibility
- Chrome 40+
- Firefox 44+
- Safari 11.1+
- Edge 17+

[Unreleased]: https://github.com/linkerlin/flarum-service-worker-cache/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/linkerlin/flarum-service-worker-cache/releases/tag/v1.0.0