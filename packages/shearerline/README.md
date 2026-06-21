# Shearerline - 在线课程教务系统商品成本计算与结算分账扩展包

基于 Vue 3 + Laravel 的商品成本管理与结算分账系统扩展包。

## 功能特性

### 商品成本管理
- 商品基本信息管理（CRUD）
- 多维度成本项配置（采购、物流、包装、平台、营销、税费、其他）
- 成本项有效期管理（生效/失效日期）
- 成本类型化管理与自动汇总计算

### 成本计算
- 单商品成本实时计算（按指定日期取有效成本）
- 批量商品成本计算
- 成本构成明细与占比分析
- 毛利润与毛利率自动核算

### 结算分账（非预扣模式）
- 结算单创建 / 编辑 / 确认 / 结算 / 取消 流程
- 按订单 / 月度 / 手动 三种结算类型
- 结算明细快照（保存结算时的成本数据，用于事后审计）
- 销售总额、商品成本、平台费、其他成本、利润自动核算
- 供应商 / 分销商 / 平台 三方分成比例配置
- 按利润分成的金额自动计算

## 技术架构

### 后端（Laravel 扩展包）
- **框架**: Laravel 9/10/11
- **PHP**: >= 8.1
- **目录结构**: `packages/shearerline/`
- **关键模块**:
  - [Shearerline.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/004-在线课程教务系统/packages/shearerline/src/Shearerline.php) - 门面核心类
  - [CostCalculationService.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/004-在线课程教务系统/packages/shearerline/src/Services/CostCalculationService.php) - 成本计算服务
  - [SettlementService.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/004-在线课程教务系统/packages/shearerline/src/Services/SettlementService.php) - 结算分账服务
  - [Settlement.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/004-在线课程教务系统/packages/shearerline/src/Models/Settlement.php) - 结算模型（含状态机）

### 前端（Vue 3）
- **框架**: Vue 3 + Vite
- **UI**: Element Plus
- **状态管理**: Pinia
- **路由**: Vue Router 4
- **目录结构**: `frontend/`
- **关键页面**:
  - [Dashboard.vue](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/004-在线课程教务系统/frontend/src/views/Dashboard.vue) - 数据看板
  - [Products/List.vue](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/004-在线课程教务系统/frontend/src/views/Products/List.vue) - 商品列表
  - [Products/Detail.vue](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/004-在线课程教务系统/frontend/src/views/Products/Detail.vue) - 商品详情与成本配置
  - [CostCalculator/Index.vue](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/004-在线课程教务系统/frontend/src/views/CostCalculator/Index.vue) - 成本计算工具
  - [Settlements/Create.vue](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/004-在线课程教务系统/frontend/src/views/Settlements/Create.vue) - 新建结算单
  - [Settlements/Detail.vue](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/004-在线课程教务系统/frontend/src/views/Settlements/Detail.vue) - 结算单详情

## 数据库表结构

### `shearerline_products` 商品表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| name | varchar(255) | 商品名称 |
| sku | varchar(100) | SKU（唯一） |
| sale_price | decimal(12,2) | 销售单价 |
| status | tinyint | 状态: 1在售 0下架 |

### `shearerline_product_costs` 商品成本表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| product_id | bigint | 关联商品 |
| cost_type | varchar(50) | 成本类型（purchase/shipping/...） |
| cost_name | varchar(255) | 成本项名称 |
| unit_cost | decimal(12,2) | 单位成本 |
| quantity | int | 数量 |
| total_cost | decimal(12,2) | 小计（自动计算） |
| effective_date | date | 生效日期 |
| expiry_date | date | 失效日期 |
| is_active | tinyint | 是否启用 |

### `shearerline_settlements` 结算单表
| 字段 | 类型 | 说明 |
|------|------|------|
| settlement_no | varchar(50) | 结算单号 |
| type | varchar(30) | 结算类型 |
| settlement_date | date | 结算日期 |
| total_amount | decimal(14,2) | 销售总额 |
| product_cost | decimal(14,2) | 商品成本合计 |
| total_cost | decimal(14,2) | 总成本 |
| total_profit | decimal(14,2) | 利润总额 |
| profit_rate | decimal(8,4) | 利润率 |
| supplier_ratio | decimal(8,4) | 供应商分成比例 |
| supplier_share | decimal(14,2) | 供应商分成金额 |
| distributor_ratio / share | - | 分销商 |
| platform_ratio / share | - | 平台 |
| status | varchar(30) | pending/confirmed/settled/cancelled |

### `shearerline_settlement_items` 结算明细表
保存结算时的商品快照（名称/SKU/单价/成本），确保结算数据可追溯。

## 安装

### 后端
```bash
# 在主项目 composer.json 中添加
"repositories": [
    {
        "type": "path",
        "url": "packages/shearerline"
    }
]

composer require shearerline/shearerline
php artisan shearerline:install
php artisan migrate
```

### 前端
```bash
cd frontend
npm install
npm run dev
```

## 成本计算逻辑

**核心公式**：
```
单位总成本 = Σ(有效成本项的 total_cost)
毛利润 = 销售单价 - 单位总成本
毛利率 = 毛利润 ÷ 销售单价 × 100%
```

**有效成本项判定条件**：
1. `is_active = 1`（已启用）
2. `effective_date <= 计算日期`
3. `expiry_date >= 计算日期` 或 `expiry_date IS NULL`

## 结算分账逻辑

**非预扣模式**：不预先扣除成本，而是在结算时基于**实际销售利润**按比例分成。

```
销售总额 = Σ(商品售价 × 销售数量)
商品成本 = Σ(结算时商品单位成本快照 × 数量)
总成本  = 商品成本 + 平台费 + 其他成本
利润总额 = 销售总额 - 总成本

供应商分成 = 利润总额 × 供应商比例
分销商分成 = 利润总额 × 分销商比例
平台分成   = 利润总额 × 平台比例
```

**状态流转**：
```
待确认 (pending) → 已确认 (confirmed) → 已结算 (settled)
     ↓                  ↓
 已取消 (cancelled) ←──┘
```

## API 接口一览

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/shearerline/products | 商品列表 |
| POST | /api/shearerline/products | 创建商品 |
| GET | /api/shearerline/products/{id}/calculate-cost | 单商品成本计算 |
| POST | /api/shearerline/products/batch-calculate-cost | 批量成本计算 |
| GET | /api/shearerline/product-costs | 成本项列表 |
| POST | /api/shearerline/product-costs | 创建成本项 |
| POST | /api/shearerline/settlements/calculate | 结算预览计算 |
| POST | /api/shearerline/settlements | 创建结算单 |
| POST | /api/shearerline/settlements/{id}/confirm | 确认结算单 |
| POST | /api/shearerline/settlements/{id}/settle | 执行结算 |
| POST | /api/shearerline/settlements/{id}/cancel | 取消结算单 |

## License

MIT
