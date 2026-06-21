<template>
  <div>
    <h2 class="page-title">数据看板</h2>

    <el-row :gutter="20" class="stats-row">
      <el-col :span="6">
        <div class="stat-card" style="border-left: 4px solid #409EFF;">
          <div class="stat-label">商品总数</div>
          <div class="stat-value">{{ statistics?.products?.total || 0 }}</div>
          <div class="stat-desc">
            在售 <span class="money-positive">{{ statistics?.products?.active || 0 }}</span> 件
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card" style="border-left: 4px solid #67C23A;">
          <div class="stat-label">累计销售额</div>
          <div class="stat-value">
            ¥{{ formatMoney(statistics?.finance?.total_amount) }}
          </div>
          <div class="stat-desc">
            本月 ¥{{ formatMoney(statistics?.finance?.this_month_amount) }}
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card" style="border-left: 4px solid #E6A23C;">
          <div class="stat-label">累计利润</div>
          <div class="stat-value money-positive">
            ¥{{ formatMoney(statistics?.finance?.total_profit) }}
          </div>
          <div class="stat-desc">
            本月 ¥{{ formatMoney(statistics?.finance?.this_month_profit) }}
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card" style="border-left: 4px solid #F56C6C;">
          <div class="stat-label">结算单统计</div>
          <div class="stat-value">{{ statistics?.settlements?.total || 0 }}</div>
          <div class="stat-desc">
            已完成 <span class="money-positive">{{ statistics?.settlements?.settled || 0 }}</span>
            待确认 <span style="color: #E6A23C;">{{ statistics?.settlements?.pending || 0 }}</span>
          </div>
        </div>
      </el-col>
    </el-row>

    <el-row :gutter="20">
      <el-col :span="16">
        <div class="page-card">
          <h3 style="margin: 0 0 20px 0;">利润分成概览</h3>
          <el-row :gutter="16">
            <el-col :span="8">
              <el-card shadow="never" class="share-card" style="border-color: #409EFF;">
                <div class="share-label" style="color: #409EFF;">供应商分成</div>
                <div class="share-value">¥{{ formatMoney(statistics?.finance?.total_supplier_share) }}</div>
                <div class="share-ratio">默认 50%</div>
              </el-card>
            </el-col>
            <el-col :span="8">
              <el-card shadow="never" class="share-card" style="border-color: #67C23A;">
                <div class="share-label" style="color: #67C23A;">分销商分成</div>
                <div class="share-value">¥{{ formatMoney(statistics?.finance?.total_distributor_share) }}</div>
                <div class="share-ratio">默认 20%</div>
              </el-card>
            </el-col>
            <el-col :span="8">
              <el-card shadow="never" class="share-card" style="border-color: #E6A23C;">
                <div class="share-label" style="color: #E6A23C;">平台分成</div>
                <div class="share-value">¥{{ formatMoney(statistics?.finance?.total_platform_share) }}</div>
                <div class="share-ratio">默认 30%</div>
              </el-card>
            </el-col>
          </el-row>
        </div>
      </el-col>
      <el-col :span="8">
        <div class="page-card">
          <h3 style="margin: 0 0 20px 0;">快捷操作</h3>
          <div class="shortcut-actions">
            <el-button type="primary" size="large" @click="goToProducts">
              <el-icon><Goods /></el-icon>
              <span>商品管理</span>
            </el-button>
            <el-button type="success" size="large" @click="goToProductCosts">
              <el-icon><Money /></el-icon>
              <span>成本配置</span>
            </el-button>
            <el-button type="warning" size="large" @click="goToCalculator">
              <el-icon><Calculator /></el-icon>
              <span>成本计算</span>
            </el-button>
            <el-button type="danger" size="large" @click="goToCreateSettlement">
              <el-icon><DocumentAdd /></el-icon>
              <span>新建结算</span>
            </el-button>
          </div>
        </div>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getStatistics } from '@/api/dashboard'
import { formatMoney } from '@/utils/format'

const router = useRouter()
const statistics = ref(null)

const loadStatistics = async () => {
  try {
    statistics.value = await getStatistics()
  } catch (e) {
    statistics.value = {
      products: { total: 0, active: 0 },
      settlements: { total: 0, settled: 0, pending: 0, this_month_count: 0 },
      finance: {
        total_amount: 0, total_profit: 0,
        total_supplier_share: 0, total_distributor_share: 0, total_platform_share: 0,
        this_month_amount: 0, this_month_profit: 0
      }
    }
  }
}

const goToProducts = () => router.push('/products')
const goToProductCosts = () => router.push('/product-costs')
const goToCalculator = () => router.push('/cost-calculator')
const goToCreateSettlement = () => router.push('/settlements/create')

onMounted(() => {
  loadStatistics()
})
</script>

<style lang="scss" scoped>
.stats-row {
  margin-bottom: 20px;
}

.share-card {
  text-align: center;
  border-width: 2px;

  .share-label {
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 8px;
  }

  .share-value {
    font-size: 24px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 4px;
  }

  .share-ratio {
    font-size: 12px;
    color: #909399;
  }
}

.shortcut-actions {
  display: flex;
  flex-direction: column;
  gap: 12px;

  .el-button {
    justify-content: flex-start;
    height: 52px;
    font-size: 15px;

    .el-icon {
      margin-right: 8px;
      font-size: 18px;
    }
  }
}
</style>
