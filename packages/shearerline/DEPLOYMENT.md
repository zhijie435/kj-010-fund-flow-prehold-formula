# Shearerline 部署文档

> 在线课程教务系统 - 资金流向与预扣公式模块

## 目录

- [系统要求](#系统要求)
- [安装部署](#安装部署)
- [环境变量配置](#环境变量配置)
- [数据库迁移](#数据库迁移)
- [队列任务配置](#队列任务配置)
- [种子数据](#种子数据)
- [验收命令](#验收命令)
- [资金流向说明](#资金流向说明)
- [预扣公式说明](#预扣公式说明)

---

## 系统要求

| 依赖 | 版本要求 |
|------|----------|
| PHP | >= 8.1 |
| Laravel | 9.x / 10.x / 11.x |
| 数据库 | MySQL 5.7+ / PostgreSQL 10+ |
| 队列驱动 | database / redis / beanstalkd |
| PHP 扩展 | bcmath, json, mbstring, pdo |

---

## 安装部署

### 1. 添加 Composer 仓库

在主项目 `composer.json` 中添加本地仓库：

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/shearerline"
        }
    ]
}
```

### 2. 安装扩展包

```bash
composer require shearerline/shearerline
```

### 3. 发布资源

```bash
# 发布配置文件
php artisan vendor:publish --tag=shearerline-config

# 发布迁移文件
php artisan vendor:publish --tag=shearerline-migrations

# 发布种子文件
php artisan vendor:publish --tag=shearerline-seeds

# 发布视图文件（可选）
php artisan vendor:publish --tag=shearerline-views

# 发布语言文件（可选）
php artisan vendor:publish --tag=shearerline-lang

# 一键安装（推荐）
php artisan shearerline:install
```

### 4. 运行迁移

```bash
php artisan migrate
```

### 5. 配置权限（可选）

如果使用 Laravel 的 Gate 权限系统，扩展包已自动注册策略：

- `Shearerline\Models\Product` → `Shearerline\Policies\ProductPolicy`
- `Shearerline\Models\ProductCost` → `Shearerline\Policies\ProductCostPolicy`
- `Shearerline\Models\Settlement` → `Shearerline\Policies\SettlementPolicy`

---

## 环境变量配置

在 `.env` 文件中添加以下配置：

```bash
# ==========================================
# Shearerline 基础配置
# ==========================================

# API 路由前缀
SHEARERLINE_API_PREFIX=api/shearerline

# 分页每页数量
SHEARERLINE_PER_PAGE=15

# 是否启用前端视图
SHEARERLINE_VIEWS_ENABLED=true

# ==========================================
# 结算分账默认比例
# ==========================================

# 供应商默认分成比例 (0.50 = 50%)
SHEARERLINE_DEFAULT_SUPPLIER_RATIO=0.50

# 分销商默认分成比例 (0.20 = 20%)
SHEARERLINE_DEFAULT_DISTRIBUTOR_RATIO=0.20

# 平台默认分成比例 (0.30 = 30%)
SHEARERLINE_DEFAULT_PLATFORM_RATIO=0.30

# ==========================================
# 批发商等级折扣
# ==========================================

# 默认等级: normal/silver/gold/platinum/diamond
SHEARERLINE_DEFAULT_GRADE=normal

# ==========================================
# 物流配置
# ==========================================

# 默认物流模板: sf_standard / sf_economy / zt_express / yt_express
SHEARERLINE_SHIPPING_TEMPLATE=sf_standard

# 体积重量除数
SHEARERLINE_VOLUMETRIC_DIVISOR=6000

# ==========================================
# 队列配置
# ==========================================

# 结算单处理队列名称
SHEARERLINE_QUEUE_CONNECTION=database
SHEARERLINE_SETTLEMENT_QUEUE=shearerline-settlements

# 是否启用异步结算处理
SHEARERLINE_SETTLEMENT_ASYNC=false

# ==========================================
# 资金流向与预扣公式
# ==========================================

# 是否在结算单详情中自动追加资金流向数据
SHEARERLINE_APPEND_FUND_FLOW=true

# 是否在结算单详情中自动追加预扣公式数据
SHEARERLINE_APPEND_WITHHOLD_FORMULA=true
```

### 配置文件说明

发布后配置文件位于 `config/shearerline.php`，可根据实际需求修改。

---

## 数据库迁移

### 迁移文件列表

| 文件名 | 说明 |
|--------|------|
| `2024_01_01_000001_create_products_table.php` | 商品表 |
| `2024_01_01_000002_create_product_costs_table.php` | 商品成本表 |
| `2024_01_01_000003_create_settlements_table.php` | 结算单表 |
| `2024_01_01_000004_create_settlement_items_table.php` | 结算明细表 |
| `2024_01_01_000005_add_supplier_price_to_products_table.php` | 商品供货价字段 |
| `2024_01_01_000006_add_dimensions_to_products_table.php` | 商品尺寸重量字段 |

### 核心表结构

#### shearerline_settlements 结算单表

| 字段 | 类型 | 说明 |
|------|------|------|
| `settlement_no` | varchar(50) | 结算单号（唯一） |
| `type` | varchar(30) | 结算类型: order/monthly/manual |
| `settlement_date` | date | 结算日期 |
| `total_amount` | decimal(14,2) | 销售总额 |
| `product_cost` | decimal(14,2) | 商品成本合计 |
| `platform_fee` | decimal(14,2) | 平台费用 |
| `other_cost` | decimal(14,2) | 其他成本 |
| `total_cost` | decimal(14,2) | 总成本合计 |
| `total_profit` | decimal(14,2) | 利润总额 |
| `profit_rate` | decimal(8,4) | 利润率 |
| `supplier_ratio` | decimal(8,4) | 供应商分成比例 |
| `distributor_ratio` | decimal(8,4) | 分销商分成比例 |
| `platform_ratio` | decimal(8,4) | 平台分成比例 |
| `supplier_share` | decimal(14,2) | 供应商分成金额 |
| `distributor_share` | decimal(14,2) | 分销商分成金额 |
| `platform_share` | decimal(14,2) | 平台分成金额 |
| `status` | varchar(30) | 状态: pending/confirmed/settled/cancelled |

#### shearerline_settlement_items 结算明细表

| 字段 | 类型 | 说明 |
|------|------|------|
| `settlement_id` | bigint | 关联结算单 |
| `product_id` | bigint | 关联商品 |
| `product_name` | varchar(255) | 商品名称快照 |
| `product_sku` | varchar(100) | 商品SKU快照 |
| `quantity` | int | 数量 |
| `sale_price` | decimal(12,2) | 销售单价 |
| `total_sales` | decimal(14,2) | 销售金额 |
| `unit_cost` | decimal(12,2) | 单位成本（结算时快照） |
| `total_cost` | decimal(14,2) | 总成本 |
| `cost_breakdown` | json | 成本构成明细 |
| `profit` | decimal(14,2) | 利润 |

### 回滚迁移

```bash
# 回滚所有迁移
php artisan migrate:rollback --step=6

# 重置并重新运行
php artisan migrate:refresh
```

---

## 队列任务配置

### 队列驱动配置

在 `.env` 中设置队列驱动：

```bash
QUEUE_CONNECTION=database

# 或使用 Redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

如果使用 database 驱动，需要创建队列表：

```bash
php artisan queue:table
php artisan migrate
```

### 可用队列任务

| 任务类 | 说明 | 队列名称 |
|--------|------|----------|
| `ProcessSettlementJob` | 结算单异步处理 | `shearerline-settlements` |
| `RecalculateSettlementJob` | 重新计算结算单 | `shearerline-settlements` |
| `GenerateSettlementReportJob` | 生成结算报表 | `shearerline-reports` |

### 启动队列工作进程

```bash
# 启动单个工作进程
php artisan queue:work --queue=shearerline-settlements,shearerline-reports

# 启动多个工作进程（推荐生产环境使用 Supervisor）
php artisan queue:work --queue=shearerline-settlements --daemon --tries=3

# 监听队列（开发环境）
php artisan queue:listen --queue=shearerline-settlements
```

### Supervisor 配置示例

```ini
[program:shearerline-settlements]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=shearerline-settlements --daemon --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/queue-settlements.log
```

### 失败任务处理

```bash
# 查看失败任务
php artisan queue:failed

# 重试所有失败任务
php artisan queue:retry all

# 重试指定任务
php artisan queue:retry <job-id>

# 清除所有失败任务
php artisan queue:flush
```

---

## 种子数据

### 发布种子文件

```bash
php artisan vendor:publish --tag=shearerline-seeds
```

### 运行种子

```bash
# 运行所有种子
php artisan db:seed --class=ShearerlineSeeder

# 单独运行各模块种子
php artisan db:seed --class=ProductSeeder
php artisan db:seed --class=ProductCostSeeder
php artisan db:seed --class=SettlementSeeder
```

### 种子数据说明

| 种子类 | 说明 | 数据量 |
|--------|------|--------|
| `ProductSeeder` | 商品数据 | 10 个示例商品 |
| `ProductCostSeeder` | 商品成本数据 | 每个商品 5-8 个成本项 |
| `SettlementSeeder` | 结算单数据 | 5 个不同状态的结算单 |

### 开发环境快速填充

```bash
# 一键迁移并填充数据
php artisan migrate --seed --seeder=ShearerlineSeeder
```

---

## 验收命令

### 核心验收命令

```bash
# 运行完整验收套件
php artisan shearerline:verify

# 仅验收资金流向功能
php artisan shearerline:verify --fund-flow

# 仅验收预扣公式功能
php artisan shearerline:verify --withhold-formula

# 仅验收结算流程
php artisan shearerline:verify --settlement

# 显示详细输出
php artisan shearerline:verify -v
```

### 单元测试

```bash
# 运行所有测试
cd packages/shearerline
./vendor/bin/phpunit

# 运行资金流向相关测试
./vendor/bin/phpunit --filter FundFlowServiceTest

# 运行结算相关测试
./vendor/bin/phpunit --filter Settlement
```

### 接口验收

#### 1. 创建结算单

```bash
curl -X POST http://your-domain/api/shearerline/settlements \
  -H "Content-Type: application/json" \
  -d '{
    "type": "manual",
    "settlement_date": "2024-01-15",
    "platform_fee": 50.00,
    "other_cost": 30.00,
    "supplier_ratio": 0.50,
    "distributor_ratio": 0.20,
    "platform_ratio": 0.30,
    "items": [
      {"product_id": 1, "quantity": 2, "sale_price": 299.00},
      {"product_id": 2, "quantity": 1, "sale_price": 199.00}
    ]
  }'
```

#### 2. 查看资金流向

```bash
curl -X GET http://your-domain/api/shearerline/settlements/{id}
```

响应中包含：
- `fund_flow.nodes` - 资金节点（客户、平台、成本项、利润、分成方）
- `fund_flow.edges` - 资金流向边（金额、标签）
- `fund_flow.description` - 资金流向文字描述

#### 3. 查看预扣公式

```bash
curl -X GET http://your-domain/api/shearerline/settlements/{id}
```

响应中包含：
- `withhold_formula.formulas` - 各项计算公式明细
- `withhold_formula.summary` - 公式汇总说明

#### 4. 结算计算预览

```bash
curl -X POST http://your-domain/api/shearerline/settlements/calculate \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      {"product_id": 1, "quantity": 3}
    ],
    "supplier_ratio": 0.5,
    "platform_fee": 20
  }'
```

### 验收检查清单

- [ ] 数据库迁移执行成功
- [ ] 商品数据可正常创建/查询
- [ ] 成本项可正常配置并参与计算
- [ ] 结算单创建成功，金额计算正确
- [ ] 资金流向数据完整（9个节点 + 8条边）
- [ ] 预扣公式数据完整（9项公式）
- [ ] 结算状态流转正常（待确认 → 已确认 → 已结算）
- [ ] 队列任务可正常投递和消费
- [ ] 权限策略正常生效
- [ ] API 接口响应符合预期

---

## 资金流向说明

### 资金流向模型

资金流向描述了从客户付款到最终各方分成的完整资金链路。

```
客户 (customer)
    │
    ▼ 销售总额
平台账户 (platform)
    ├───► 商品成本 (product_cost) ── 供应商货款
    ├───► 平台费用 (platform_fee)  ── 平台服务费预扣
    ├───► 其他成本 (other_cost)    ── 其他杂费
    │
    ▼ 剩余利润
可分配利润 (profit)
    ├───► 供应商 (supplier)         ── 利润分成
    ├───► 分销商 (distributor)      ── 利润分成
    └───► 平台收益 (platform_income) ── 利润分成
```

### 节点说明

| 节点ID | 名称 | 类型 | 说明 |
|--------|------|------|------|
| `customer` | 客户 | source | 资金来源，支付销售总额 |
| `platform` | 平台账户 | transfer | 资金归集中转站 |
| `product_cost` | 商品成本 | cost | 供应商货款 |
| `platform_fee` | 平台费用 | cost | 平台服务费 |
| `other_cost` | 其他成本 | cost | 其他杂费 |
| `profit` | 可分配利润 | profit | 利润总额 |
| `supplier` | 供应商 | recipient | 利润分成接收方 |
| `distributor` | 分销商 | recipient | 利润分成接收方 |
| `platform_income` | 平台收益 | recipient | 利润分成接收方 |

### 数据结构

```php
[
    'nodes' => [
        ['id' => 'customer', 'name' => '客户', 'amount' => 1000.00, 'type' => 'source'],
        // ... 更多节点
    ],
    'edges' => [
        ['from' => 'customer', 'to' => 'platform', 'amount' => 1000.00, 'label' => '销售总额'],
        // ... 更多边
    ],
    'description' => '资金流向：客户支付 ¥1000.00 → 平台归集后，扣除商品成本...',
    'total_amount' => 1000.00,
    'total_cost' => 580.00,
    'total_profit' => 420.00,
]
```

### 使用方式

```php
use Shearerline\Models\Settlement;
use Shearerline\Services\FundFlowService;

// 方式1：通过模型访问器自动获取
$settlement = Settlement::with('items')->find($id);
$fundFlow = $settlement->fund_flow;

// 方式2：通过服务手动构建
$service = app(FundFlowService::class);
$fundFlow = $service->buildFundFlow(
    totalAmount: 1000.00,
    productCost: 500.00,
    platformFee: 50.00,
    otherCost: 30.00,
    totalCost: 580.00,
    totalProfit: 420.00,
    supplierShare: 210.00,
    distributorShare: 84.00,
    platformShare: 126.00,
    supplierRatio: 0.50,
    distributorRatio: 0.20,
    platformRatio: 0.30
);
```

---

## 预扣公式说明

### 核心公式

非预扣模式下，各项成本与利润的计算公式：

```
1. 销售总额 = Σ(商品单价 × 数量)
2. 商品成本 = Σ(单位成本 × 数量)
3. 预扣费用 = 平台费用 + 其他成本
4. 总成本 = 商品成本 + 平台费用 + 其他成本
5. 利润总额 = 销售总额 - 总成本
6. 利润率 = 利润总额 ÷ 销售总额 × 100%
7. 供应商分成 = 利润总额 × 供应商分成比例
8. 分销商分成 = 利润总额 × 分销商分成比例
9. 平台分成 = 利润总额 × 平台分成比例
```

### 公式项说明

| 公式名称 | 计算方式 | 单位 |
|----------|----------|------|
| 销售总额 | 各商品销售金额累加 | 金额 |
| 商品成本 | 各商品成本累加 | 金额 |
| 预扣费用合计 | 平台费用 + 其他成本 | 金额 |
| 总成本 | 商品成本 + 平台费用 + 其他成本 | 金额 |
| 利润总额 | 销售总额 - 总成本 | 金额 |
| 利润率 | 利润总额 ÷ 销售总额 × 100% | 百分比 |
| 供应商分成 | 利润总额 × 供应商比例 | 金额 |
| 分销商分成 | 利润总额 × 分销商比例 | 金额 |
| 平台分成 | 利润总额 × 平台比例 | 金额 |

### 数据结构

```php
[
    'formulas' => [
        [
            'name' => '销售总额',
            'formula' => '销售总额 = Σ(商品单价 × 数量)',
            'value' => 500.00,
            'calculation' => '¥100 × 2 + ¥300 × 1',
        ],
        [
            'name' => '利润率',
            'formula' => '利润率 = 利润总额 ÷ 销售总额 × 100%',
            'value' => 0.50,
            'calculation' => '¥250 ÷ ¥500 × 100%',
            'is_percent' => true,
        ],
        // ... 更多公式项
    ],
    'summary' => '本次结算共 2 件商品，销售总额 ¥500.00，扣除各项成本 ¥250.00 后，实现利润 ¥250.00，利润率 50%。',
]
```

### 使用方式

```php
use Shearerline\Models\Settlement;
use Shearerline\Services\FundFlowService;

// 方式1：通过模型访问器自动获取
$settlement = Settlement::with('items')->find($id);
$formula = $settlement->withhold_formula;

// 方式2：通过服务手动构建
$service = app(FundFlowService::class);
$formula = $service->buildWithholdFormula(
    items: $settlementItems,
    totalAmount: 500.00,
    productCost: 200.00,
    platformFee: 30.00,
    otherCost: 20.00,
    totalCost: 250.00,
    totalProfit: 250.00,
    profitRate: 0.50,
    supplierShare: 125.00,
    distributorShare: 50.00,
    platformShare: 75.00,
    supplierRatio: 0.50,
    distributorRatio: 0.20,
    platformRatio: 0.30
);
```

### 成本构成明细

结算明细中保存了 `cost_breakdown` 字段（JSON格式），记录了结算时的成本构成快照：

```json
[
    {"cost_type": "purchase", "cost_type_name": "采购成本", "total": 150.00},
    {"cost_type": "shipping", "cost_type_name": "物流成本", "total": 20.00},
    {"cost_type": "platform_fee", "cost_type_name": "平台费用", "total": 10.00}
]
```

可通过以下方式获取汇总：

```php
$settlement = Settlement::with('items')->find($id);
$breakdown = $settlement->product_cost_breakdown;
```

---

## 常见问题

### Q: 资金流向数据为空怎么办？

A: 确保已预加载 `items` 关联关系：
```php
$settlement = Settlement::with('items')->find($id);
```

### Q: 如何自定义分成比例？

A: 创建结算单时传入对应比例，或修改配置文件 `config/shearerline.php` 中的默认值。

### Q: 队列任务失败如何重试？

A: 使用 `php artisan queue:retry all` 重试所有失败任务，或指定任务 ID。

### Q: 结算单金额计算不准确？

A: 确保商品成本项的生效日期覆盖结算日期，且 `is_active = 1`。

---

## License

MIT
