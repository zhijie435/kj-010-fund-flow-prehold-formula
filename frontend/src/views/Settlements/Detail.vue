<template>
  <div v-loading="loading">
    <div class="action-bar">
      <el-page-header :content="settlement?.settlement_no || '结算单详情'" @back="router.back()">
        <template #extra>
          <div style="display: flex; gap: 8px;">
            <el-button
              v-if="settlement?.status === 'pending'"
              type="success"
              @click="handleConfirm"
            >
              <el-icon><Check /></el-icon>确认结算单
            </el-button>
            <el-button
              v-if="settlement?.status === 'confirmed'"
              type="primary"
              @click="handleSettle"
            >
              <el-icon><Wallet /></el-icon>执行结算
            </el-button>
            <el-button
              v-if="settlement?.status === 'pending' || settlement?.status === 'confirmed'"
              type="warning"
              @click="handleCancel"
            >
              <el-icon><Close /></el-icon>取消
            </el-button>
          </div>
        </template>
      </el-page-header>
    </div>

    <el-row :gutter="20" v-if="settlement">
      <el-col :span="16">
        <div class="page-card">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0;">基本信息</h3>
            <el-tag :type="getSettlementStatusInfo(settlement.status).type" size="large">
              {{ getSettlementStatusInfo(settlement.status).label }}
            </el-tag>
          </div>

          <el-descriptions :column="3" border>
            <el-descriptions-item label="结算单号">
              <span style="font-weight: 500;">{{ settlement.settlement_no }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="类型">
              <el-tag :type="getSettlementTypeInfo(settlement.type).type" size="small">
                {{ getSettlementTypeInfo(settlement.type).label }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="结算日期">{{ settlement.settlement_date }}</el-descriptions-item>
            <el-descriptions-item label="订单号" :span="2">{{ settlement.order_no || '-' }}</el-descriptions-item>
            <el-descriptions-item label="明细数">{{ settlement.order_count }} 件</el-descriptions-item>
            <el-descriptions-item label="创建人" :span="2">{{ settlement.created_by || '-' }}</el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ formatDate(settlement.created_at, 'YYYY-MM-DD HH:mm') }}</el-descriptions-item>
            <el-descriptions-item label="备注" :span="3">{{ settlement.remark || '-' }}</el-descriptions-item>
          </el-descriptions>
        </div>

        <div class="page-card">
          <h3 style="margin: 0 0 16px 0;">商品明细</h3>
          <el-table :data="settlement.items || []" border stripe row-key="id">
            <el-table-column type="expand" width="50">
              <template #default="{ row }">
                <div v-if="row.cost_breakdown && row.cost_breakdown.length" style="padding: 12px 20px; background: #fafafa;">
                  <div style="font-weight: 500; margin-bottom: 8px; color: #606266;">成本构成明细</div>
                  <el-row :gutter="20">
                    <el-col :span="8" v-for="bd in row.cost_breakdown" :key="bd.cost_type">
                      <div style="display: flex; justify-content: space-between; padding: 4px 0;">
                        <span style="color: #909399;">{{ bd.cost_type_name }}</span>
                        <span style="font-weight: 500;">¥{{ formatMoney(bd.total * row.quantity) }}</span>
                      </div>
                    </el-col>
                  </el-row>
                </div>
                <div v-else style="padding: 12px 20px; color: #909399;">
                  暂无成本构成明细
                </div>
              </template>
            </el-table-column>
            <el-table-column label="商品信息" min-width="200">
              <template #default="{ row }">
                <div>
                  <div style="font-weight: 500;">{{ row.product_name }}</div>
                  <div style="font-size: 12px; color: #909399;">SKU: {{ row.product_sku }}</div>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="quantity" label="数量" width="80" align="center" />
            <el-table-column label="单价" width="110" align="right">
              <template #default="{ row }">¥{{ formatMoney(row.sale_price) }}</template>
            </el-table-column>
            <el-table-column label="销售金额" width="120" align="right">
              <template #default="{ row }">
                <span style="color: #F56C6C; font-weight: 500;">¥{{ formatMoney(row.total_sales) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="单位成本" width="110" align="right">
              <template #default="{ row }">¥{{ formatMoney(row.unit_cost) }}</template>
            </el-table-column>
            <el-table-column label="总成本" width="110" align="right">
              <template #default="{ row }">¥{{ formatMoney(row.total_cost) }}</template>
            </el-table-column>
            <el-table-column label="利润" width="120" align="right">
              <template #default="{ row }">
                <span class="money-positive" style="font-weight: 500;">¥{{ formatMoney(row.profit) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="利润率" width="100" align="center">
              <template #default="{ row }">
                <el-tag
                  :type="row.total_sales > 0 && row.profit / row.total_sales >= 0.2 ? 'success' : row.total_sales > 0 && row.profit / row.total_sales >= 0.1 ? 'warning' : 'danger'"
                  size="small"
                >
                  {{ row.total_sales > 0 ? formatPercent(row.profit / row.total_sales) : '0%' }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>
          <div style="margin-top: 8px; font-size: 12px; color: #909399;">
            <el-icon style="vertical-align: -2px;"><InfoFilled /></el-icon> 点击左侧箭头可展开查看商品成本构成明细
          </div>
        </div>
      </el-col>

      <el-col :span="8">
        <div class="page-card">
          <h3 style="margin: 0 0 16px 0;">金额汇总</h3>

          <div class="finance-block">
            <div class="finance-title">销售数据</div>
            <div class="finance-row">
              <span>销售总额</span>
              <span class="text-red big">¥{{ formatMoney(settlement.total_amount) }}</span>
            </div>
          </div>

          <div class="finance-block">
            <div class="finance-title">成本数据</div>
            <div class="finance-row">
              <span>商品成本</span>
              <span>¥{{ formatMoney(settlement.product_cost) }}</span>
            </div>

            <div v-if="settlement.product_cost_breakdown && settlement.product_cost_breakdown.length" style="margin: 8px 0 8px 12px; padding-left: 12px; border-left: 2px solid #E4E7ED;">
              <div v-for="bd in settlement.product_cost_breakdown" :key="bd.cost_type" class="finance-row" style="font-size: 13px;">
                <span style="color: #909399;">{{ bd.cost_type_name }}</span>
                <span style="color: #606266;">¥{{ formatMoney(bd.total) }}</span>
              </div>
            </div>

            <div class="finance-row">
              <span>平台费用</span>
              <span>¥{{ formatMoney(settlement.platform_fee) }}</span>
            </div>
            <div class="finance-row">
              <span>其他成本</span>
              <span>¥{{ formatMoney(settlement.other_cost) }}</span>
            </div>
            <el-divider style="margin: 6px 0;" />
            <div class="finance-row">
              <span style="font-weight: 500;">总成本</span>
              <span style="font-weight: 500;">¥{{ formatMoney(settlement.total_cost) }}</span>
            </div>
          </div>

          <div class="finance-block" style="background: #f0f9eb; border-radius: 6px; padding: 12px;">
            <div class="finance-title" style="color: #67c23a;">利润数据</div>
            <div class="finance-row">
              <span>利润总额</span>
              <span class="money-positive" style="font-size: 22px; font-weight: 600;">¥{{ formatMoney(settlement.total_profit) }}</span>
            </div>
            <div class="finance-row">
              <span>利润率</span>
              <el-tag :type="settlement.profit_rate >= 0.2 ? 'success' : settlement.profit_rate >= 0.1 ? 'warning' : 'danger'">
                {{ formatPercent(settlement.profit_rate) }}
              </el-tag>
            </div>
          </div>
        </div>

        <div class="page-card">
          <h3 style="margin: 0 0 16px 0;">分账明细 (按利润分成)</h3>

          <el-card shadow="never" class="share-item" style="border-left: 4px solid #409EFF; margin-bottom: 10px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div>
                <div style="font-weight: 500; color: #409EFF;">供应商</div>
                <div style="font-size: 12px; color: #909399; margin-top: 4px;">
                  分成比例 {{ formatPercent(settlement.supplier_ratio) }}
                </div>
              </div>
              <div style="font-size: 20px; font-weight: 600; color: #409EFF;">
                ¥{{ formatMoney(settlement.supplier_share) }}
              </div>
            </div>
          </el-card>

          <el-card shadow="never" class="share-item" style="border-left: 4px solid #67C23A; margin-bottom: 10px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div>
                <div style="font-weight: 500; color: #67C23A;">分销商</div>
                <div style="font-size: 12px; color: #909399; margin-top: 4px;">
                  分成比例 {{ formatPercent(settlement.distributor_ratio) }}
                </div>
              </div>
              <div style="font-size: 20px; font-weight: 600; color: #67C23A;">
                ¥{{ formatMoney(settlement.distributor_share) }}
              </div>
            </div>
          </el-card>

          <el-card shadow="never" class="share-item" style="border-left: 4px solid #E6A23C;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div>
                <div style="font-weight: 500; color: #E6A23C;">平台</div>
                <div style="font-size: 12px; color: #909399; margin-top: 4px;">
                  分成比例 {{ formatPercent(settlement.platform_ratio) }}
                </div>
              </div>
              <div style="font-size: 20px; font-weight: 600; color: #E6A23C;">
                ¥{{ formatMoney(settlement.platform_share) }}
              </div>
            </div>
          </el-card>

          <el-divider />
          <div class="finance-row" style="padding-top: 4px;">
            <span style="font-weight: 600;">分成合计</span>
            <span style="font-weight: 600; font-size: 16px;">
              ¥{{ formatMoney(parseFloat(settlement.supplier_share) + parseFloat(settlement.distributor_share) + parseFloat(settlement.platform_share)) }}
            </span>
          </div>
        </div>

        <div class="page-card" v-if="settlement.status === 'settled'">
          <h3 style="margin: 0 0 16px 0;">结算信息</h3>
          <el-descriptions :column="1" border size="small">
            <el-descriptions-item label="结算操作人">{{ settlement.settled_by || '-' }}</el-descriptions-item>
            <el-descriptions-item label="结算时间">
              {{ formatDate(settlement.settled_at, 'YYYY-MM-DD HH:mm') }}
            </el-descriptions-item>
          </el-descriptions>
        </div>
      </el-col>
    </el-row>

    <el-row :gutter="20" v-if="settlement">
      <el-col :span="24">
        <div class="page-card">
          <h3 style="margin: 0 0 16px 0;">资金流向图</h3>
          <div class="fund-flow-container">
            <div class="fund-flow-chart">
              <div class="fund-flow-node source">
                <div class="node-icon"><el-icon><User /></el-icon></div>
                <div class="node-name">客户</div>
                <div class="node-amount">¥{{ formatMoney(settlement.fund_flow?.total_amount || settlement.total_amount) }}</div>
                <div class="node-desc">销售收款</div>
              </div>
              <div class="fund-flow-arrow">
                <el-icon><ArrowRight /></el-icon>
                <span class="arrow-label">销售总额</span>
              </div>
              <div class="fund-flow-node transfer">
                <div class="node-icon"><el-icon><Wallet /></el-icon></div>
                <div class="node-name">平台账户</div>
                <div class="node-amount">¥{{ formatMoney(settlement.fund_flow?.total_amount || settlement.total_amount) }}</div>
                <div class="node-desc">资金归集</div>
              </div>
            </div>
            <div class="fund-flow-split">
              <div class="split-row">
                <div class="split-item cost">
                  <div class="split-icon"><el-icon><Goods /></el-icon></div>
                  <div class="split-info">
                    <div class="split-name">商品成本</div>
                    <div class="split-amount">¥{{ formatMoney(settlement.product_cost) }}</div>
                  </div>
                  <div class="split-arrow"><el-icon><Bottom /></el-icon></div>
                </div>
                <div class="split-item cost">
                  <div class="split-icon"><el-icon><Service /></el-icon></div>
                  <div class="split-info">
                    <div class="split-name">平台费用</div>
                    <div class="split-amount">¥{{ formatMoney(settlement.platform_fee) }}</div>
                  </div>
                  <div class="split-arrow"><el-icon><Bottom /></el-icon></div>
                </div>
                <div class="split-item cost">
                  <div class="split-icon"><el-icon><Menu /></el-icon></div>
                  <div class="split-info">
                    <div class="split-name">其他成本</div>
                    <div class="split-amount">¥{{ formatMoney(settlement.other_cost) }}</div>
                  </div>
                  <div class="split-arrow"><el-icon><Bottom /></el-icon></div>
                </div>
              </div>
            </div>
            <div class="fund-flow-profit">
              <div class="profit-node">
                <div class="profit-icon"><el-icon><GoldMedal /></el-icon></div>
                <div class="profit-name">可分配利润</div>
                <div class="profit-amount">¥{{ formatMoney(settlement.fund_flow?.total_profit || settlement.total_profit) }}</div>
                <div class="profit-desc">利润总额</div>
              </div>
            </div>
            <div class="fund-flow-share">
              <div class="share-item supplier">
                <div class="share-icon"><el-icon><OfficeBuilding /></el-icon></div>
                <div class="share-info">
                  <div class="share-name">供应商</div>
                  <div class="share-ratio">{{ formatPercent(settlement.supplier_ratio) }}</div>
                </div>
                <div class="share-amount">¥{{ formatMoney(settlement.supplier_share) }}</div>
              </div>
              <div class="share-item distributor">
                <div class="share-icon"><el-icon><UserFilled /></el-icon></div>
                <div class="share-info">
                  <div class="share-name">分销商</div>
                  <div class="share-ratio">{{ formatPercent(settlement.distributor_ratio) }}</div>
                </div>
                <div class="share-amount">¥{{ formatMoney(settlement.distributor_share) }}</div>
              </div>
              <div class="share-item platform">
                <div class="share-icon"><el-icon><Platform /></el-icon></div>
                <div class="share-info">
                  <div class="share-name">平台</div>
                  <div class="share-ratio">{{ formatPercent(settlement.platform_ratio) }}</div>
                </div>
                <div class="share-amount">¥{{ formatMoney(settlement.platform_share) }}</div>
              </div>
            </div>
            <div class="fund-flow-desc">
              <el-icon style="vertical-align: -2px; margin-right: 4px;"><InfoFilled /></el-icon>
              {{ settlement.fund_flow?.description || '资金流向说明加载中...' }}
            </div>
          </div>
        </div>

        <div class="page-card">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0;">预扣公式与计算明细</h3>
          </div>
          <el-alert
            :title="settlement.withhold_formula?.summary || '计算摘要加载中...'"
            type="info"
            :closable="false"
            style="margin-bottom: 16px;"
          />
          <el-table :data="settlement.withhold_formula?.formulas || []" border stripe>
            <el-table-column prop="name" label="项目" width="140" />
            <el-table-column prop="formula" label="计算公式" min-width="280">
              <template #default="{ row }">
                <code style="background: #f5f7fa; padding: 2px 6px; border-radius: 4px; font-size: 13px;">{{ row.formula }}</code>
              </template>
            </el-table-column>
            <el-table-column label="计算过程" min-width="300">
              <template #default="{ row }">
                <span style="color: #606266; font-size: 13px;">{{ row.calculation }}</span>
              </template>
            </el-table-column>
            <el-table-column label="结果" width="140" align="right">
              <template #default="{ row }">
                <span style="font-weight: 600;">
                  {{ row.is_percent ? formatPercent(row.value) : '¥' + formatMoney(row.value) }}
                </span>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getSettlement, confirmSettlement, settleSettlement, cancelSettlement
} from '@/api/settlement'
import {
  Check, Wallet, Close, InfoFilled,
  User, ArrowRight, Goods, Service, Menu,
  Bottom, GoldMedal, OfficeBuilding, UserFilled, Platform
} from '@element-plus/icons-vue'
import {
  formatMoney, formatPercent, formatDate,
  getSettlementTypeInfo, getSettlementStatusInfo
} from '@/utils/format'

const route = useRoute()
const router = useRouter()

const id = computed(() => route.params.id)
const loading = ref(false)
const settlement = ref(null)

const loadData = async () => {
  loading.value = true
  try {
    settlement.value = await getSettlement(id.value)
  } finally {
    loading.value = false
  }
}

const handleConfirm = async () => {
  try {
    await ElMessageBox.confirm('确认此结算单？确认后将无法编辑明细。', '确认结算单', { type: 'warning' })
    await confirmSettlement(id.value)
    ElMessage.success('确认成功')
    loadData()
  } catch (e) {}
}

const handleSettle = async () => {
  try {
    await ElMessageBox.confirm('执行结算操作？结算后将标记为已完成，不可恢复。', '执行结算', { type: 'warning' })
    await settleSettlement(id.value)
    ElMessage.success('结算完成')
    loadData()
  } catch (e) {}
}

const handleCancel = async () => {
  try {
    await ElMessageBox.confirm('取消此结算单？', '取消结算单', { type: 'warning' })
    await cancelSettlement(id.value)
    ElMessage.success('已取消')
    loadData()
  } catch (e) {}
}

onMounted(() => {
  loadData()
})
</script>

<style lang="scss" scoped>
.finance-block {
  margin-bottom: 16px;

  .finance-title {
    font-size: 13px;
    color: #909399;
    margin-bottom: 8px;
    font-weight: 500;
  }

  .finance-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    font-size: 14px;
    color: #606266;
  }
}

.text-red {
  color: #F56C6C;

  &.big {
    font-size: 18px;
    font-weight: 600;
  }
}

.share-item {
  :deep(.el-card__body) {
    padding: 12px 16px;
  }
}

.fund-flow-container {
  background: linear-gradient(135deg, #f5f7fa 0%, #e8f4fd 100%);
  border-radius: 12px;
  padding: 24px;

  .fund-flow-chart {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;

    .fund-flow-node {
      background: #fff;
      border-radius: 12px;
      padding: 16px 24px;
      text-align: center;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      min-width: 140px;
      transition: transform 0.3s, box-shadow 0.3s;

      &:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
      }

      &.source {
        border: 2px solid #67C23A;

        .node-icon {
          color: #67C23A;
        }
      }

      &.transfer {
        border: 2px solid #409EFF;

        .node-icon {
          color: #409EFF;
        }
      }

      .node-icon {
        font-size: 32px;
        margin-bottom: 8px;
      }

      .node-name {
        font-size: 14px;
        font-weight: 600;
        color: #303133;
        margin-bottom: 4px;
      }

      .node-amount {
        font-size: 20px;
        font-weight: 700;
        color: #303133;
        margin-bottom: 4px;
      }

      .node-desc {
        font-size: 12px;
        color: #909399;
      }
    }

    .fund-flow-arrow {
      display: flex;
      flex-direction: column;
      align-items: center;
      color: #409EFF;
      font-size: 24px;

      .arrow-label {
        font-size: 12px;
        color: #606266;
        margin-top: 4px;
      }
    }
  }

  .fund-flow-split {
    margin-bottom: 20px;

    .split-row {
      display: flex;
      justify-content: center;
      gap: 24px;
    }

    .split-item {
      background: #fff;
      border-radius: 8px;
      padding: 12px 20px;
      display: flex;
      align-items: center;
      gap: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      min-width: 180px;

      &.cost {
        border-left: 4px solid #F56C6C;

        .split-icon {
          color: #F56C6C;
        }
      }

      .split-icon {
        font-size: 24px;
      }

      .split-info {
        flex: 1;

        .split-name {
          font-size: 13px;
          color: #606266;
        }

        .split-amount {
          font-size: 16px;
          font-weight: 600;
          color: #303133;
        }
      }

      .split-arrow {
        color: #F56C6C;
        font-size: 16px;
      }
    }
  }

  .fund-flow-profit {
    display: flex;
    justify-content: center;
    margin-bottom: 20px;

    .profit-node {
      background: linear-gradient(135deg, #67C23A 0%, #85CE61 100%);
      border-radius: 12px;
      padding: 16px 40px;
      text-align: center;
      box-shadow: 0 4px 12px rgba(103, 194, 58, 0.3);
      color: #fff;

      .profit-icon {
        font-size: 32px;
        margin-bottom: 8px;
      }

      .profit-name {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 4px;
      }

      .profit-amount {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 4px;
      }

      .profit-desc {
        font-size: 12px;
        opacity: 0.9;
      }
    }
  }

  .fund-flow-share {
    display: flex;
    justify-content: center;
    gap: 24px;
    margin-bottom: 20px;

    .share-item {
      background: #fff;
      border-radius: 12px;
      padding: 16px 24px;
      display: flex;
      align-items: center;
      gap: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      min-width: 200px;

      &.supplier {
        border-top: 4px solid #409EFF;

        .share-icon {
          color: #409EFF;
        }

        .share-amount {
          color: #409EFF;
        }
      }

      &.distributor {
        border-top: 4px solid #67C23A;

        .share-icon {
          color: #67C23A;
        }

        .share-amount {
          color: #67C23A;
        }
      }

      &.platform {
        border-top: 4px solid #E6A23C;

        .share-icon {
          color: #E6A23C;
        }

        .share-amount {
          color: #E6A23C;
        }
      }

      .share-icon {
        font-size: 28px;
      }

      .share-info {
        flex: 1;

        .share-name {
          font-size: 14px;
          font-weight: 600;
          color: #303133;
        }

        .share-ratio {
          font-size: 12px;
          color: #909399;
        }
      }

      .share-amount {
        font-size: 18px;
        font-weight: 700;
      }
    }
  }

  .fund-flow-desc {
    background: #fff;
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 13px;
    color: #606266;
    line-height: 1.8;
    border-left: 4px solid #409EFF;
  }
}
</style>
