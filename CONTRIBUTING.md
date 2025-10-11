# Contributing to Flarum Service Worker Cache

感谢您对本项目的贡献兴趣！我们欢迎各种形式的贡献。

## 🤝 贡献方式

### 报告Bug
1. 搜索现有的 [Issues](https://github.com/linkerlin/flarum-service-worker-cache/issues) 确保问题未被报告
2. 创建新的Issue，提供详细信息：
   - Flarum版本
   - 浏览器类型和版本
   - 复现步骤
   - 预期行为和实际行为
   - 错误日志（如有）

### 提交功能请求
1. 确保功能符合项目目标
2. 详细描述功能的使用场景和价值
3. 如果可能，提供设计草图或实现思路

### 代码贡献

#### 开发环境配置
```bash
# 克隆项目
git clone https://github.com/linkerlin/flarum-service-worker-cache.git
cd flarum-service-worker-cache

# 安装依赖
composer install

# 链接到Flarum测试环境
cd /path/to/your/flarum
composer config repositories.sw-cache path "../flarum-service-worker-cache"
composer require steperlin/flarum-service-worker-cache:*@dev
```

#### 代码规范
- 遵循 PSR-4 自动加载标准
- 使用 PSR-2 代码风格
- 所有新功能需要包含测试
- 提交信息使用英文，格式：`type(scope): description`
  - feat: 新功能
  - fix: Bug修复
  - docs: 文档更新
  - style: 代码格式
  - refactor: 重构
  - test: 测试
  - chore: 构建工具

#### Pull Request流程
1. Fork项目到您的GitHub账户
2. 创建功能分支：`git checkout -b feature/your-feature-name`
3. 提交您的更改：`git commit -am 'feat: add some feature'`
4. 推送到分支：`git push origin feature/your-feature-name`
5. 创建Pull Request

### 测试

#### 手动测试
1. 在浏览器开发者工具中验证Service Worker注册
2. 检查缓存策略是否正确应用
3. 测试离线功能
4. 验证缓存清理功能

#### 浏览器兼容性测试
请在以下浏览器中测试：
- Chrome (最新版本 + 前两个版本)
- Firefox (最新版本 + 前两个版本)
- Safari (最新版本)
- Edge (最新版本)

## 📖 开发指南

### 项目结构
```
flarum-service-worker-cache/
├── src/                    # PHP源代码
│   ├── Listeners/         # 事件监听器
│   └── Middleware/        # 中间件
├── js/                    # JavaScript源代码
│   └── forum/            # 前端组件
├── service-worker.js      # Service Worker核心文件
├── extend.php            # Flarum扩展配置
└── composer.json         # Composer配置
```

### 缓存策略设计原则
1. **性能优先** - 立即返回缓存，后台刷新
2. **安全考虑** - 保护敏感信息，正确处理CSRF
3. **用户体验** - 提供清晰的状态反馈
4. **资源管理** - 智能清理过期缓存

### Service Worker开发注意事项
1. 总是包含详细的日志记录
2. 处理所有可能的异常情况
3. 考虑向后兼容性
4. 提供优雅的降级方案

## 🚀 发布流程

### 版本号规则
使用[语义化版本](https://semver.org/lang/zh-CN/)：
- MAJOR: 不兼容的API修改
- MINOR: 向后兼容的功能性新增
- PATCH: 向后兼容的问题修正

### 发布检查清单
- [ ] 更新CHANGELOG.md
- [ ] 更新composer.json中的版本号
- [ ] 运行所有测试
- [ ] 在多个浏览器中验证
- [ ] 创建Git标签
- [ ] 提交到Packagist（如果是Packagist发布）

## 📋 代码审查标准

### PHP代码
- 遵循Flarum编码标准
- 正确的命名空间和类结构
- 适当的错误处理
- 清晰的文档注释

### JavaScript代码
- 现代ES6+语法
- 清晰的变量命名
- 适当的错误处理
- 兼容性考虑

### Service Worker代码
- 充分的日志记录
- 完整的错误处理
- 性能优化
- 缓存策略清晰

## 🙋‍♂️ 获得帮助

如果您在贡献过程中遇到问题：

1. 查看现有的Issues和Discussions
2. 创建新的Issue询问
3. 联系维护者：steper.lin@icloud.com

## 📄 许可证

通过贡献代码，您同意您的贡献将基于[Apache-2.0许可证](LICENSE)进行许可。

---

再次感谢您的贡献！🎉