<template>
  <div>
    <h2 class="page-title">成本计算工具</h2>

    <el-row :gutter="20">
      <el-col :span="10">
        <div class="page-card">
          <h3 style="margin: 0 0 16px 0;">选择商品与参数</h3>

          <el-form label-width="100px">
            <el-form-item label="计算日期">
              <el-date-picker
                v-model="calcDate"
                type="date"
                value-format="YYYY-MM-DD"
                style="width: 100%;"
              />
            </el-form-item>
            <el-form-item label="选择商品">
              <el-select
                v-model="selectedProductId"
                placeholder="搜索选择商品"
                filterable
                remote
                :remote-method="searchProducts"
                :loading="productLoading"
                style="width: 100%;"
                @change="onProductChange"
              >
                <el-option
                  v-for="p in productOptions"
                  :key="p.id"
                  :label="`${p.name} (${p.sku})`"
                  :value="p.id"
                />
              </el-select>
            </el-form-item>
          </el-form>

          <el-divider>批量计算</el-divider>

          <el-form label-width="100px">
            <el-form-item label="批量选择">
              <el-select
                v-model="selectedProductIds"
                multiple
                filterable
                remote
                :remote-method="searchProducts"
                :loading="productLoading"
                placeholder="选择多个商品进行批量计算"
                style="width: 100%;"
              >
                <el-option
                  v-for="p in productOptions"
                  :key="p.id"
                  :label="`${p.name} (${p.sku})`"
                  :value="p.id"
                />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="calculateBatch" :loading="calcLoading" :disabled="!selectedProductIds.length">
                <el-icon><Calculator /></el-icon>批量计算成本
              </el-button>
              <el-button @click="clearSelection">
                <el-icon><Delete /></el-icon>清空
              </el-button>
            </el-form-item>
          </el-form>

          <div style="padding: 16px; background: #f0f9eb; border-radius: 6px; border: 1px solid #e1f3d8;">
            <div style="font-weight: 500; color: #67c23a; margin-bottom: 8px;">
              <el-icon><InfoFilled /></el-icon> 计算说明
            </div>
            <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #606266; line-height: 1.8;">
              <li>仅计算在指定日期内<b>已启用且处于有效期</b>的成本项</li>
              <li>单位成本 = 各项有效成本的 total_cost 之和</li>
              <li>毛利润 = 销售单价 - 单位总成本</li>
              <li>毛利率 = 毛利润 ÷ 销售单价 × 100%</li>
            </ul>
          </div>
        </div>
      </el-col>

      <el-col :span="14">
        <div class="page-card" v-if="singleResult">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0;">单商品成本分析</h3>
          </div>

          <el-descriptions :column="2" border size="small" style="margin-bottom: 20px;">
            <el-descriptions-item label="商品">{{ singleResult.product_name }}</el-descriptions-item>
            <el-descriptions-item label="SKU">{{ singleResult.product_sku }}</el-descriptions-item>
            <el-descriptions-item label="销售单价">
              <span style="color: #F56C6C; font-weight: 600;">¥{{ formatMoney(singleResult.sale_price) }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="单位成本">
              <span style="font-weight: 600;">¥{{ formatMoney(singleResult.total_cost) }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="毛利润">
              <span class="money-positive" style="font-weight: 600;">¥{{ formatMoney(singleResult.gross_profit) }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="毛利率">
              <el-tag
                :type="singleResult.gross_margin >= 0.3 ? 'success' : singleResult.gross_margin >= 0.15 ? 'warning' : 'danger'"
                size="small"
              >
                {{ formatPercent(singleResult.gross_margin) }}
              </el-tag>
            </el-descriptions-item>
          </el-descriptions>

          <h4 style="margin: 0 0 12px 0;">成本类型构成</h4>
          <el-table :data="singleResult.breakdown" size="small" border>
            <el-table-column prop="cost_type_name" label="成本类型" width="140" />
            <el-table-column label="构成明细">
              <template #default="{ row }">
                <div class="cost-breakdown">
                  <span v-for="item in row.items" :key="item.id" class="cost-item">
                    {{ item.cost_name }}: ¥{{ formatMoney(item.total_cost) }}
                    <span style="color: #c0c4cc;">(¥{{ formatMoney(item.unit_cost) }} × {{ item.quantity }})</span>
                  </span>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="金额" width="120" align="right">
              <template #default="{ row }">
                <span style="font-weight: 500;">¥{{ formatMoney(row.total) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="占比" width="100" align="right">
              <template #default="{ row }">
                {{ singleResult.total_cost > 0 ? formatPercent(row.total / singleResult.total_cost) : '0%' }}
              </template>
            </el-table-column>
          </el-table>
        </div>

        <div class="page-card" v-if="batchResult">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0;">批量计算汇总</h3>
            <el-tag type="info">共 {{ batchResult.summary.product_count }} 个商品</el-tag>
          </div>

          <el-row :gutter="12" style="margin-bottom: 20px;">
            <el-col :span="6">
              <el-card shadow="never" class="mini-stat" style="border-color: #F56C6C;">
                <div class="mini-label">销售单价合计</div>
                <div class="mini-value">¥{{ formatMoney(batchResult.summary.total_sale_price) }}</div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="never" class="mini-stat" style="border-color: #909399;">
                <div class="mini-label">成本合计</div>
                <div class="mini-value">¥{{ formatMoney(batchResult.summary.total_cost) }}</div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="never" class="mini-stat" style="border-color: #67C23A;">
                <div class="mini-label">利润合计</div>
                <div class="mini-value money-positive">¥{{ formatMoney(batchResult.summary.total_gross_profit) }}</div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="never" class="mini-stat" style="border-color: #409EFF;">
                <div class="mini-label">加权毛利率</div>
                <div class="mini-value">{{ formatPercent(batchResult.summary.weighted_margin) }}</div>
              </el-card>
            </el-col>
          </el-row>

          <el-table :data="batchResult.products" size="small" border stripe>
            <el-table-column prop="product_name" label="商品名称" min-width="160" />
            <el-table-column prop="product_sku" label="SKU" width="120" />
            <el-table-column label="售价" width="100" align="right">
              <template #default="{ row }">¥{{ formatMoney(row.sale_price) }}</template>
            </el-table-column>
            <el-table-column label="成本" width="100" align="right">
              <template #default="{ row }">¥{{ formatMoney(row.total_cost) }}</template>
            </el-table-column>
            <el-table-column label="利润" width="110" align="right">
              <template #default="{ row }">
                <span class="money-positive">¥{{ formatMoney(row.gross_profit) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="毛利率" width="100" align="center">
              <template #default="{ row }">
                <el-tag
                  :type="row.gross_margin >= 0.3 ? 'success' : row.gross_margin >= 0.15 ? 'warning' : 'danger'"
                  size="small"
                >
                  {{ formatPercent(row.gross_margin) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="成本项数" width="90" align="center">
              <template #default="{ row }">{{ row.cost_item_count }}</template>
            </el-table-column>
          </el-table>
        </div>

        <el-empty v-if="!singleResult && !batchResult" description="请选择商品进行成本计算" />
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getAllProducts, calculateProductCost, batchCalculateCost } from '@/api/product'
import { formatMoney, formatPercent } from '@/utils/format'

const calcDate = ref(new Date().toISOString().slice(0, 10))
const productLoading = ref(false)
const productOptions = ref([])
const selectedProductId = ref(null)
const selectedProductIds = ref([])
const calcLoading = ref(false)

const singleResult = ref(null)
const batchResult = ref(null)

const searchProducts = async (query) => {
  productLoading.value = true
  try {
    const all = await getAllProducts()
    if (!query) {
      productOptions.value = all
      return
    }
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

const onProductChange = async () => {
  if (!selectedProductId.value) {
    singleResult.value = null
    return
  }
  try {
    singleResult.value = await calculateProductCost(selectedProductId.value, { date: calcDate.value })
  } catch (e) {}
}

const calculateBatch = async () => {
  if (!selectedProductIds.value.length) return
  calcLoading.value = true
  try {
    batchResult.value = await batchCalculateCost({
      product_ids: selectedProductIds.value,
      date: calcDate.value
    })
    singleResult.value = null
    ElMessage.success('批量计算完成')
  } catch (e) {
  } finally {
    calcLoading.value = false
  }
}

const clearSelection = () => {
  selectedProductId.value = null
  selectedProductIds.value = []
  singleResult.value = null
  batchResult.value = null
}

onMounted(async () => {
  await searchProducts('')
})
</script>

<style lang="scss" scoped>
.mini-stat {
  border-width: 2px;
  border-left-width: 4px;
  text-align: center;
  padding: 8px;

  :deep(.el-card__body) {
    padding: 12px;
  }

  .mini-label {
    font-size: 12px;
    color: #909399;
    margin-bottom: 4px;
  }

  .mini-value {
    font-size: 20px;
    font-weight: 600;
    color: #303133;
  }
}
</style>
