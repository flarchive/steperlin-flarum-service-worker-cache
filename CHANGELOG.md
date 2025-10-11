# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Additional Flarum 2.0 optimizations
- Enhanced PWA features
- Extended browser compatibility

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