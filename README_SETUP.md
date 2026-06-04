# Shopify 内部管理工作台 - 项目配置指南

## 项目概述

这是一个 Laravel 8 构建的 Shopify 店铺管理系统，专为内部管理人员设计。支持多店铺接入、订单数据同步、数据字段转换和 Excel 导出功能。

### 核心功能

1. **多店铺管理** - 支持连接多个 Shopify 店铺
2. **细粒度权限** - 不同管理员对应不同店铺访问权限
3. **订单同步** - 从 Shopify 获取订单数据并本地缓存
4. **数据转换** - 应用 6 个 Ruby 规则转译为的 PHP 转换器
5. **Excel 导出** - 按指定格式导出订单数据

## 快速开始

### 1. 环境要求

- PHP 7.4+
- MySQL 5.7+
- Composer

### 2. 安装步骤

```bash
# 复制 .env 文件
cp .env.example .env

# 安装依赖
composer install

# 生成应用密钥
php artisan key:generate

# 配置数据库
# 编辑 .env 文件，设置 DB_DATABASE、DB_USERNAME、DB_PASSWORD

# 运行迁移
php artisan migrate

# 创建测试管理员用户（可选）
php artisan tinker
# 在 tinker 中执行：
# App\Models\Admin::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password'), 'role' => 'super'])
```

### 3. 配置 Shopify API

编辑 `.env` 文件，添加以下配置（暂不需要，稍后通过 UI 添加店铺时配置）：

```
SHOPIFY_API_KEY=your_api_key
SHOPIFY_API_SECRET=your_api_secret
```

### 4. 启动开发服务器

```bash
php artisan serve
```

访问 `http://localhost:8001/login`

## 项目结构

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/AdminLoginController.php       # 登录控制器
│   │   ├── DashboardController.php              # 仪表板
│   │   ├── OrderController.php                  # 订单管理
│   │   └── ExportController.php                 # 导出下载
│   └── Middleware/
│       └── AdminAuth.php                        # 管理员认证中间件
│
├── Models/
│   ├── Admin.php                                # 管理员模型
│   ├── ShopifyStore.php                         # 店铺模型
│   ├── AdminStoreAccess.php                     # 权限映射
│   ├── Order.php                                # 订单模型
│   └── OrderLineItem.php                        # 订单行项目
│
├── Services/
│   ├── ShopifyService.php                       # Shopify API 调用
│   ├── OrderCacheService.php                    # 缓存管理
│   ├── OrderFieldTransformer.php                # 字段转换主类
│   ├── ExcelExportService.php                   # Excel 导出
│   └── Transformers/
│       ├── NameTransformer.php                  # NAME 规则
│       ├── ValTransformer.php                   # VAL 规则
│       ├── UrlTransformer.php                   # URL 规则
│       ├── SubpicTransformer.php                # SUBPIC 规则
│       ├── ExtraTransformer.php                 # EXTRA 规则（最复杂）
│       └── GetnotesTransformer.php              # GETNOTES 规则
│
└── database/
    └── migrations/
        ├── create_admins_table.php
        ├── create_shopify_stores_table.php
        ├── create_admin_store_access_table.php
        ├── create_orders_table.php
        └── create_order_line_items_table.php
```

## 关键功能说明

### 1. 字段转换规则

这个项目实现了 6 个从 Ruby 转译的字段转换规则：

| 规则 | 功能 | 复杂度 |
|------|------|--------|
| NAME | 提取所有产品标题 | ⭐ |
| VAL | 检查特殊价格标记 | ⭐ |
| URL | 从属性中提取图片 URL | ⭐⭐ |
| SUBPIC | 从 URL 提取文件名 | ⭐ |
| EXTRA | 生成格式化文件名 | ⭐⭐⭐⭐⭐ |
| GETNOTES | 提取用户笔记 | ⭐⭐ |

**ExtraTransformer** 包含 40+ 个条件判断，涵盖多种产品类型的命名规则。

### 2. 缓存策略

- **缓存位置**: MySQL 数据库
- **缓存有效期**: 1 小时（可配置）
- **自动清理**: 过期数据自动删除
- **同步触发**: 手动刷新或定时任务

### 3. 权限模型

- **Super 管理员**: 访问所有店铺
- **普通管理员**: 仅访问授权店铺
- **权限存储**: admin_store_access 表

### 4. Excel 导出

- **格式**: XLSX（Excel 2007+）
- **列结构**: 与 de_order_with_image_0203230600.xlsx 一致
- **文件存储**: storage/exports/ 目录
- **自动下载**: 生成后立即下载

## API 端点

### 认证

```
POST   /login              显示登录表单
POST   /login              处理登录
POST   /logout             登出
```

### 仪表板

```
GET    /dashboard          显示店铺列表
```

### 订单管理

```
GET    /orders?store_id=X          显示订单列表
POST   /orders/refresh             刷新订单
POST   /orders/export              导出为 Excel
GET    /export/download/{filename} 下载文件
```

## 开发注意事项

### 添加新的 Transformer

1. 创建新文件 `app/Services/Transformers/YourTransformer.php`
2. 继承转换器模式，实现 `transform()` 方法
3. 在 `OrderFieldTransformer` 中注册

### 扩展店铺功能

1. 在 `admin_store_access` 表中添加权限
2. 管理员自动可访问授权店铺

### 调试

启用 Laravel 调试模式：

```bash
# .env
APP_DEBUG=true
APP_LOG_LEVEL=debug
```

## 常见问题

### Q: 如何添加新的 Shopify 店铺？

A: 目前需要直接数据库操作或通过 artisan tinker 添加。计划后续添加 UI 管理界面。

```php
php artisan tinker

App\Models\ShopifyStore::create([
    'shop_name' => 'myshop',
    'shop_url' => 'myshop.myshopify.com',
    'access_token' => 'your_access_token',
    'is_active' => true,
]);

App\Models\AdminStoreAccess::create([
    'admin_id' => 1,
    'store_id' => 1,
    'access_level' => 'edit',
]);
```

### Q: 如何修改缓存时长？

A: 在 `OrderCacheService` 中修改 `$cacheTtlHours` 属性。

### Q: 导出文件在哪里？

A: 文件存储在 `storage/exports/` 目录，有下载链接。

## 下一步

- [ ] 实现 Shopify OAuth 授权流程
- [ ] 添加店铺管理 UI
- [ ] 实现定时同步任务
- [ ] 添加数据搜索和筛选
- [ ] 支持批量操作
- [ ] 添加日志和审计追踪
- [ ] 性能优化和分页

## 许可证

私有项目

## 联系

如有问题，请联系项目维护者。
