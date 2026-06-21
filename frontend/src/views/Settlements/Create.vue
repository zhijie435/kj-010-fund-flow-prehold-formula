<template>
  <div>
    <div class="action-bar">
      <el-page-header content="新建结算单" @back="router.back()" />
      <div>
        <el-button @click="goBack">取消</el-button>
        <el-button type="warning" @click="previewCalculate" :loading="previewLoading">
          <el-icon><View /></el-icon>预览计算
        </el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitLoading">
          <el-icon><Check /></el-icon>创建结算单
        </el-button>
      </div>
    </div>

    <el-row :gutter="20">
      <el-col :span="16">
        <div class="page-card">
          <h3 style="margin: 0 0 16px 0;">基本信息</h3>
          <el-form :model="formData" label-width="100px">
            <el-row :gutter="16">
              <el-col :span="12">
                <el-form-item label="结算类型">
                  <el-select v-model="formData.type" style="width: 100%;">
                    <el-option
                      v-for="(info, key) in SETTLEMENT_TYPES"
                      :key="key"
                      :label="info.label"
                      :value="key"
                    />
                  </el-select>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="结算日期">
                  <el-date-picker
                    v-model="formData.settlement_date"
                    type="date"
                    value-format="YYYY-MM-DD"
                    style="width: 100%;"
                  />
                </el-form-item>
              </el-col>
            </el-row>
            <el-row :gutter="16">
              <el-col :span="12">
                <el-form-item label="关联订单号">
                  <el-input v-model="formData.order_no" placeholder="按订单结算时填写" />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="备注">
                  <el-input v-model="formData.remark" placeholder="结算备注说明" />
                </el-form-item>
              </el-col>
            </el-row>
          </el-form>
        </div>

        <div class="page-card">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0;">商品明细</h3>
            <div>
              <el-select
                v-model="newItem.product_id"
                placeholder="选择商品添加"
                filterable
                remote
                :remote-method="searchProducts"
                :loading="productLoading"
                style="width: 280px; margin-right: 8px;"
              >
                <el-option
                  v-for="p in productOptions"
                  :key="p.id"
                  :label="`${p.name} (${p.sku}) - ¥${formatMoney(p.sale_price)}`"
                  :value="p.id"
                />
              </el-select>
              <el-button type="primary" @click="addItem" :disabled="!newItem.product_id">
                <el-icon><Plus /></el-icon>添加
              </el-button>
            </div>
          </div>

          <el-table :data="formData.items" border stripe>
            <el-table-column label="商品信息" min-width="200">
              <template #default="{ row }">
                <div>
                  <div style="font-weight: 500;">{{ row.product_name || '未选择' }}</div>
                  <div style="font-size: 12px; color: #909399;">SKU: {{ row.product_sku || '-' }}</div>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="数量" width="140">
              <template #default="{ row, $index }">
                <el-input-number
                  v-model="row.quantity"
                  :min="1"
                  :step="1"
                  size="small"
                  style="width: 100%;"
                  @change="updateItem($index)"
                />
              </template>
            </el-table-column>
            <el-table-column label="销售单价" width="160">
              <template #default="{ row, $index }">
                <el-input-number
                  v-model="row.sale_price"
                  :min="0"
                  :precision="2"
                  :step="1"
                  size="small"
                  style="width: 100%;"
                  @change="updateItem($index)"
                />
              </template>
            </el-table-column>
            <el-table-column label="销售金额" width="130" align="right">
              <template #default="{ row }">
                <span style="font-weight: 500; color: #F56C6C;">¥{{ formatMoney(row.sale_price * row.quantity) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="单位成本" width="160">
              <template #default="{ row, $index }">
                <el-input-number
                  v-model="row.unit_cost"
                  :min="0"
                  :precision="2"
                  :step="1"
                  size="small"
                  style="width: 100%;"
                  @change="updateItem($index)"
                />
                <el-button
                  v-if="row.product_id"
                  type="primary"
                  link
                  size="small"
                  style="margin-top: 4px;"
                  @click="recalcItemCost($index)"
                >
                  按日期获取
                </el-button>
              </template>
            </el-table-column>
            <el-table-column label="总成本" width="120" align="right">
              <template #default="{ row }">
                ¥{{ formatMoney(row.unit_cost * row.quantity) }}
              </template>
            </el-table-column>
            <el-table-column label="利润" width="120" align="right">
              <template #default="{ row }">
                <span class="money-positive" style="font-weight: 500;">
                  ¥{{ formatMoney((row.sale_price - row.unit_cost) * row.quantity) }}
                </span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="80" align="center" fixed="right">
              <template #default="{ $index }">
                <el-button type="danger" link @click="removeItem($index)">
                  <el-icon><Delete /></el-icon>
                </el-button>
              </template>
            </el-table-column>
          </el-table>

          <el-empty v-if="!formData.items.length" description="请添加商品明细" :image-size="80" />
        </div>
      </el-col>

      <el-col :span="8">
        <div class="page-card">
          <h3 style="margin: 0 0 16px 0;">额外成本 & 分成比例</h3>
          <el-form label-width="100px">
            <el-form-item label="平台费用">
              <el-input-number v-model="formData.platform_fee" :min="0" :precision="2" :step="1" style="width: 100%;" />
            </el-form-item>
            <el-form-item label="其他成本">
              <el-input-number v-model="formData.other_cost" :min="0" :precision="2" :step="1" style="width: 100%;" />
            </el-form-item>
            <el-divider />
            <el-form-item label="供应商比例">
              <el-input-number
                v-model="formData.supplier_ratio"
                :min="0"
                :max="1"
                :precision="4"
                :step="0.05"
                style="width: 100%;"
              />
            </el-form-item>
            <el-form-item label="分销商比例">
              <el-input-number
                v-model="formData.distributor_ratio"
                :min="0"
                :max="1"
                :precision="4"
                :step="0.05"
                style="width: 100%;"
              />
            </el-form-item>
            <el-form-item label="平台比例">
              <el-input-number
                v-model="formData.platform_ratio"
                :min="0"
                :max="1"
                :precision="4"
                :step="0.05"
                style="width: 100%;"
              />
            </el-form-item>
            <el-alert
              :title="'比例合计: ' + (formData.supplier_ratio + formData.distributor_ratio + formData.platform_ratio).toFixed(4) + (Math.abs(formData.supplier_ratio + formData.distributor_ratio + formData.platform_ratio - 1) > 0.0001 ? ' (建议等于1)' : ' ✓')"
              :type="Math.abs(formData.supplier_ratio + formData.distributor_ratio + formData.platform_ratio - 1) > 0.0001 ? 'warning' : 'success'"
              show-icon
              :closable="false"
              size="small"
            />
          </el-form>
        </div>

        <div class="page-card">
          <h3 style="margin: 0 0 16px 0;">计算预览</h3>
          <div v-if="previewResult || liveSummary">
            <div class="summary-row">
              <span>商品数量</span>
              <span>{{ (previewResult?.summary?.order_count || liveSummary?.order_count || 0) }} 件</span>
            </div>
            <div class="summary-row highlight">
              <span>销售总额</span>
              <span class="text-red">¥{{ formatMoney(previewResult?.summary?.total_amount || liveSummary?.total_amount) }}</span>
            </div>
            <div class="summary-row">
              <span>商品成本</span>
              <span>¥{{ formatMoney(previewResult?.summary?.product_cost || liveSummary?.product_cost) }}</span>
            </div>

            <div v-if="previewResult?.summary?.product_cost_breakdown && previewResult.summary.product_cost_breakdown.length" style="margin: 4px 0 4px 12px; padding-left: 12px; border-left: 2px solid #E4E7ED;">
              <div v-for="bd in previewResult.summary.product_cost_breakdown" :key="bd.cost_type" class="summary-row" style="font-size: 13px;">
                <span style="color: #909399;">{{ bd.cost_type_name }}</span>
                <span style="color: #606266;">¥{{ formatMoney(bd.total) }}</span>
              </div>
            </div>
            <div class="summary-row">
              <span>平台费用</span>
              <span>¥{{ formatMoney(previewResult?.summary?.platform_fee || formData.platform_fee) }}</span>
            </div>
            <div class="summary-row">
              <span>其他成本</span>
              <span>¥{{ formatMoney(previewResult?.summary?.other_cost || formData.other_cost) }}</span>
            </div>
            <el-divider style="margin: 8px 0;" />
            <div class="summary-row">
              <span>总成本</span>
              <span>¥{{ formatMoney(previewResult?.summary?.total_cost || liveSummary?.total_cost) }}</span>
            </div>
            <div class="summary-row highlight big">
              <span>利润总额</span>
              <span class="money-positive">¥{{ formatMoney(previewResult?.summary?.total_profit || liveSummary?.total_profit) }}</span>
            </div>
            <div class="summary-row">
              <span>利润率</span>
              <el-tag :type="profitRateTagType" size="small">
                {{ formatPercent(previewResult?.summary?.profit_rate || liveSummary?.profit_rate) }}
              </el-tag>
            </div>
            <el-divider />
            <div style="font-weight: 500; margin-bottom: 8px; color: #606266;">利润分成</div>
            <div class="summary-row">
              <span style="color: #409EFF;">供应商 ({{ formatPercent(previewResult?.shares?.supplier_ratio || formData.supplier_ratio) }})</span>
              <span style="color: #409EFF; font-weight: 500;">¥{{ formatMoney(previewResult?.shares?.supplier_share || liveShares?.supplier) }}</span>
            </div>
            <div class="summary-row">
              <span style="color: #67C23A;">分销商 ({{ formatPercent(previewResult?.shares?.distributor_ratio || formData.distributor_ratio) }})</span>
              <span style="color: #67C23A; font-weight: 500;">¥{{ formatMoney(previewResult?.shares?.distributor_share || liveShares?.distributor) }}</span>
            </div>
            <div class="summary-row">
              <span style="color: #E6A23C;">平台 ({{ formatPercent(previewResult?.shares?.platform_ratio || formData.platform_ratio) }})</span>
              <span style="color: #E6A23C; font-weight: 500;">¥{{ formatMoney(previewResult?.shares?.platform_share || liveShares?.platform) }}</span>
            </div>
          </div>
          <el-empty v-else description="添加商品后自动计算预览" :image-size="60" />
        </div>
      </el-col>
    </el-row>

    <el-row :gutter="20" v-if="previewResult?.fund_flow">
      <el-col :span="24">
        <div class="page-card">
          <h3 style="margin: 0 0 16px 0;">资金流向图</h3>
          <div class="fund-flow-container">
            <div class="fund-flow-chart">
              <div class="fund-flow-node source">
                <div class="node-icon"><el-icon><User /></el-icon></div>
                <div class="node-name">客户</div>
                <div class="node-amount">¥{{ formatMoney(previewResult.fund_flow.total_amount) }}</div>
                <div class="node-desc">销售收款</div>
              </div>
              <div class="fund-flow-arrow">
                <el-icon><ArrowRight /></el-icon>
                <span class="arrow-label">销售总额</span>
              </div>
              <div class="fund-flow-node transfer">
                <div class="node-icon"><el-icon><Wallet /></el-icon></div>
                <div class="node-name">平台账户</div>
                <div class="node-amount">¥{{ formatMoney(previewResult.fund_flow.total_amount) }}</div>
                <div class="node-desc">资金归集</div>
              </div>
            </div>
            <div class="fund-flow-split">
              <div class="split-row">
                <div class="split-item cost">
                  <div class="split-icon"><el-icon><Goods /></el-icon></div>
                  <div class="split-info">
                    <div class="split-name">商品成本</div>
                    <div class="split-amount">¥{{ formatMoney(previewResult.summary.product_cost) }}</div>
                  </div>
                  <div class="split-arrow"><el-icon><Bottom /></el-icon></div>
                </div>
                <div class="split-item cost">
                  <div class="split-icon"><el-icon><Service /></el-icon></div>
                  <div class="split-info">
                    <div class="split-name">平台费用</div>
                    <div class="split-amount">¥{{ formatMoney(previewResult.summary.platform_fee) }}</div>
                  </div>
                  <div class="split-arrow"><el-icon><Bottom /></el-icon></div>
                </div>
                <div class="split-item cost">
                  <div class="split-icon"><el-icon><Menu /></el-icon></div>
                  <div class="split-info">
                    <div class="split-name">其他成本</div>
                    <div class="split-amount">¥{{ formatMoney(previewResult.summary.other_cost) }}</div>
                  </div>
                  <div class="split-arrow"><el-icon><Bottom /></el-icon></div>
                </div>
              </div>
            </div>
            <div class="fund-flow-profit">
              <div class="profit-node">
                <div class="profit-icon"><el-icon><GoldMedal /></el-icon></div>
                <div class="profit-name">可分配利润</div>
                <div class="profit-amount">¥{{ formatMoney(previewResult.fund_flow.total_profit) }}</div>
                <div class="profit-desc">利润总额</div>
              </div>
            </div>
            <div class="fund-flow-share">
              <div class="share-item supplier">
                <div class="share-icon"><el-icon><OfficeBuilding /></el-icon></div>
                <div class="share-info">
                  <div class="share-name">供应商</div>
                  <div class="share-ratio">{{ formatPercent(previewResult.shares.supplier_ratio) }}</div>
                </div>
                <div class="share-amount">¥{{ formatMoney(previewResult.shares.supplier_share) }}</div>
              </div>
              <div class="share-item distributor">
                <div class="share-icon"><el-icon><UserFilled /></el-icon></div>
                <div class="share-info">
                  <div class="share-name">分销商</div>
                  <div class="share-ratio">{{ formatPercent(previewResult.shares.distributor_ratio) }}</div>
                </div>
                <div class="share-amount">¥{{ formatMoney(previewResult.shares.distributor_share) }}</div>
              </div>
              <div class="share-item platform">
                <div class="share-icon"><el-icon><Platform /></el-icon></div>
                <div class="share-info">
                  <div class="share-name">平台</div>
                  <div class="share-ratio">{{ formatPercent(previewResult.shares.platform_ratio) }}</div>
                </div>
                <div class="share-amount">¥{{ formatMoney(previewResult.shares.platform_share) }}</div>
              </div>
            </div>
            <div class="fund-flow-desc">
              <el-icon style="vertical-align: -2px; margin-right: 4px;"><InfoFilled /></el-icon>
              {{ previewResult.fund_flow.description }}
            </div>
          </div>
        </div>

        <div class="page-card" v-if="previewResult?.withhold_formula">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0;">预扣公式与计算明细</h3>
          </div>
          <el-alert
            :title="previewResult.withhold_formula.summary"
            type="info"
            :closable="false"
            style="margin-bottom: 16px;"
          />
          <el-table :data="previewResult.withhold_formula.formulas" border stripe>
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
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { getAllProducts, calculateProductCost } from '@/api/product'
import { calculateSettlement, createSettlement } from '@/api/settlement'
import { formatMoney, formatPercent, SETTLEMENT_TYPES } from '@/utils/format'
import {
  View, Check, Plus, Delete, Calculator,
  User, ArrowRight, Goods, Service, Menu,
  Bottom, GoldMedal, OfficeBuilding, UserFilled, Platform, InfoFilled
} from '@element-plus/icons-vue'

const router = useRouter()

const productLoading = ref(false)
const productOptions = ref([])
const productMap = ref({})

const previewLoading = ref(false)
const submitLoading = ref(false)
const previewResult = ref(null)

const formData = reactive({
  type: 'manual',
  settlement_date: new Date().toISOString().slice(0, 10),
  order_no: '',
  remark: '',
  platform_fee: 0,
  other_cost: 0,
  supplier_ratio: 0.5,
  distributor_ratio: 0.2,
  platform_ratio: 0.3,
  items: []
})

const newItem = reactive({
  product_id: null
})

const searchProducts = async (query) => {
  productLoading.value = true
  try {
    const all = await getAllProducts()
    productOptions.value = all
    all.forEach(p => { productMap.value[p.id] = p })
    if (!query) return
    const q = query.toLowerCase()
    productOptions.value = all.filter(p =>
      p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q)
    )
  } catch (e) {
    productOptions.value = []
  } finally {
    productLoading.value = false
  }
}

if (!productOptions.value.length) searchProducts('')

const addItem = () => {
  const product = productMap.value[newItem.product_id]
  if (!product) return

  formData.items.push({
    product_id: product.id,
    product_name: product.name,
    product_sku: product.sku,
    quantity: 1,
    sale_price: product.sale_price,
    unit_cost: 0
  })
  newItem.product_id = null
  previewResult.value = null
}

const updateItem = () => {
  previewResult.value = null
}

const removeItem = (index) => {
  formData.items.splice(index, 1)
  previewResult.value = null
}

const recalcItemCost = async (index) => {
  const item = formData.items[index]
  if (!item?.product_id) return
  try {
    const result = await calculateProductCost(item.product_id, { date: formData.settlement_date })
    item.unit_cost = result.total_cost
    previewResult.value = null
    ElMessage.success('成本已更新')
  } catch (e) {}
}

const liveSummary = computed(() => {
  let orderCount = 0
  let totalAmount = 0
  let productCost = 0
  formData.items.forEach(item => {
    orderCount++
    const sales = parseFloat(item.sale_price || 0) * parseInt(item.quantity || 1)
    const cost = parseFloat(item.unit_cost || 0) * parseInt(item.quantity || 1)
    totalAmount += sales
    productCost += cost
  })
  const pf = parseFloat(formData.platform_fee || 0)
  const oc = parseFloat(formData.other_cost || 0)
  const totalCost = productCost + pf + oc
  const totalProfit = totalAmount - totalCost
  const profitRate = totalAmount > 0 ? totalProfit / totalAmount : 0
  return { order_count: orderCount, total_amount: totalAmount, product_cost: productCost, total_cost: totalCost, total_profit: totalProfit, profit_rate: profitRate }
})

const liveShares = computed(() => {
  const profit = liveSummary.value.total_profit
  return {
    supplier: profit * parseFloat(formData.supplier_ratio || 0),
    distributor: profit * parseFloat(formData.distributor_ratio || 0),
    platform: profit * parseFloat(formData.platform_ratio || 0)
  }
})

const profitRateTagType = computed(() => {
  const rate = previewResult?.summary?.profit_rate ?? liveSummary.value.profit_rate
  if (rate >= 0.2) return 'success'
  if (rate >= 0.1) return 'warning'
  return 'danger'
})

const previewCalculate = async () => {
  if (!formData.items.length) {
    ElMessage.warning('请先添加商品明细')
    return
  }
  previewLoading.value = true
  try {
    const itemsForCalc = formData.items.map(i => ({
      product_id: i.product_id,
      quantity: i.quantity,
      sale_price: i.sale_price,
      unit_cost: i.unit_cost
    }))
    previewResult.value = await calculateSettlement({
      settlement_date: formData.settlement_date,
      platform_fee: formData.platform_fee,
      other_cost: formData.other_cost,
      supplier_ratio: formData.supplier_ratio,
      distributor_ratio: formData.distributor_ratio,
      platform_ratio: formData.platform_ratio,
      items: itemsForCalc
    })
    ElMessage.success('计算完成')
  } catch (e) {
  } finally {
    previewLoading.value = false
  }
}

const handleSubmit = async () => {
  if (!formData.items.length) {
    ElMessage.warning('请先添加商品明细')
    return
  }
  submitLoading.value = true
  try {
    const payload = { ...formData, items: formData.items }
    const res = await createSettlement(payload)
    ElMessage.success('结算单创建成功')
    router.push(`/settlements/${res.id}`)
  } catch (e) {
  } finally {
    submitLoading.value = false
  }
}

const goBack = () => router.back()
</script>

<style lang="scss" scoped>
.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
  font-size: 14px;
  color: #606266;

  &.highlight {
    padding: 10px 0;
    font-size: 15px;

    &.big {
      padding: 14px 0;
      font-size: 17px;
      font-weight: 600;
    }
  }
}

.text-red {
  color: #F56C6C;
  font-weight: 500;
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
