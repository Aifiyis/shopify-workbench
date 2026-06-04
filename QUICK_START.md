# 快速启动指南

## 🚀 系统已就绪！

Shopify 工作台已启动并运行在 **http://localhost:8001**

### 📋 登录凭证

```
邮箱: admin@example.com
密码: password123
角色: Super Admin
```

### ✅ 已完成的初始化

- ✅ SQLite 数据库已创建
- ✅ 所有表已迁移
- ✅ 管理员账户已创建
- ✅ 测试店铺已配置
- ✅ 权限已分配
- ✅ 开发服务器运行在端口 8001

### 🌐 访问链接

| 页面 | URL |
|------|-----|
| **登录** | http://localhost:8001/login |
| **仪表板** | http://localhost:8001/dashboard |
| **订单管理** | http://localhost:8001/orders |

### 🎯 首次使用步骤

1. **打开浏览器** → http://localhost:8001/login
2. **输入凭证**
   - 邮箱: `admin@example.com`
   - 密码: `password123`
3. **登录** → 进入仪表板
4. **选择店铺** → "Test Shop" 
5. **查看订单** → 显示订单列表（目前为空）
6. **测试功能**
   - 🔄 点击 "Refresh" 刷新订单（需要有效的 Shopify access token）
   - ⬇️ 点击 "Export Excel" 导出订单（目前无数据）
   - 📅 选择日期范围进行筛选

### 🔧 项目配置

**数据库**: SQLite (database/database.sqlite)
**缓存**: File based
**队列**: Sync
**会话**: File based

### 📊 数据库表

```
✓ admins                    - 管理员用户
✓ shopify_stores           - Shopify 店铺
✓ admin_store_access       - 权限映射
✓ orders                   - 订单（本地缓存）
✓ order_line_items         - 订单行项目
```

### 🧪 测试账户

| 账户 | 邮箱 | 密码 | 角色 | 店铺 |
|------|------|------|------|------|
| Admin | admin@example.com | password123 | Super | Test Shop |

### 📝 创建额外账户

如需创建更多管理员账户，使用 `php artisan tinker`:

```php
App\Models\Admin::create([
    'name' => 'User Name',
    'email' => 'user@example.com',
    'password' => bcrypt('password'),
    'role' => 'manager', // 或 'super'
    'is_active' => true
]);
```

### 🛠️ 常用命令

```bash
# 启动开发服务器
php artisan serve --port=8001

# 运行迁移
php artisan migrate

# 使用 Tinker 交互式 shell
php artisan tinker

# 查看日志
tail -f storage/logs/laravel.log

# 清除缓存
php artisan cache:clear

# 清空所有缓存
php artisan optimize:clear
```

### 🔑 重要说明

1. **Shopify Access Token**
   - 当前测试店铺使用虚拟 token
   - 若要实际同步订单，需更新为真实的 Shopify access token
   - 在数据库中更新: `shopify_stores.access_token`

2. **文件上传**
   - Excel 导出文件存储在 `storage/exports/` 目录
   - 确保目录可写权限

3. **日志**
   - 应用日志: `storage/logs/laravel.log`
   - 查看错误信息用于调试

### 🚨 故障排除

**Q: 无法登录?**
- 检查 .env 数据库配置
- 确保 SQLite 文件存在: `database/database.sqlite`
- 运行: `php artisan migrate:refresh`

**Q: 页面加载缓慢?**
- 运行: `php artisan optimize`
- 清除缓存: `php artisan cache:clear`

**Q: 样式未加载?**
- 这是预期行为（未配置 npm/webpack）
- 视图使用内联样式

### 📚 文档

- **CLAUDE.md** - 开发者指南
- **README_SETUP.md** - 详细安装指南
- **PROJECT_SUMMARY.md** - 项目完成总结

### ✨ 下一步

1. 配置真实的 Shopify access token
2. 测试订单同步功能
3. 验证 Excel 导出格式
4. 添加更多管理员和店铺

---

**服务器状态**: 🟢 运行中 (http://localhost:8001)  
**数据库**: 🟢 就绪  
**准备就绪**: ✅ 是

