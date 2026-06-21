<template>
  <div>
    <div class="filter-bar">
      <el-input
        v-model="filters.keyword"
        placeholder="搜索结算单号/订单号"
        style="width: 240px;"
        clearable
        @keyup.enter="loadList"
      >
        <template #prefix><el-icon><Search /></el-icon></template>
      </el-input>
      <el-select v-model="filters.type" placeholder="结算类型" clearable style="width: 160px;" @change="loadList">
        <el-option
          v-for="(info, key) in SETTLEMENT_TYPES"
          :key="key"
          :label="info.label"
          :value="key"
        />
      </el-select>
      <el-select v-model="filters.status" placeholder="状态" clearable style="width: 140px;" @change="loadList">
        <el-option
          v-for="(info, key) in SETTLEMENT_STATUSES"
          :key="key"
          :label="info.label"
          :value="key"
        />
      </el-select>
      <el-date-picker
        v-model="dateRange"
        type="daterange"
        range-separator="至"
        start-placeholder="起始日期"
        end-placeholder="结束日期"
        value-format="YYYY-MM-DD"
        style="width: 280px;"
      />
      <el-button type="primary" @click="loadList">
        <el-icon><Search /></el-icon>查询
      </el-button>
      <el-button @click="resetFilters">
        <el-icon><Refresh /></el-icon>重置
      </el-button>
      <div style="flex: 1;"></div>
      <el-button type="success" @click="goCreate">
        <el-icon><Plus /></el-icon>新建结算单
      </el-button>
    </div>

    <div class="page-card">
      <el-table :data="list" v-loading="loading" stripe>
        <el-table-column label="结算单号" width="200">
          <template #default="{ row }">
            <el-link type="primary" @click="goDetail(row)">{{ row.settlement_no }}</el-link>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="getSettlementTypeInfo(row.type).type" size="small">
              {{ getSettlementTypeInfo(row.type).label }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="settlement_date" label="结算日期" width="120" align="center" />
        <el-table-column label="明细数" width="100" align="center">
          <template #default="{ row }">{{ row.items_count || row.order_count || 0 }}</template>
        </el-table-column>
        <el-table-column label="销售总额" width="130" align="right">
          <template #default="{ row }">
            <span style="font-weight: 500;">¥{{ formatMoney(row.total_amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="总成本" width="120" align="right">
          <template #default="{ row }">¥{{ formatMoney(row.total_cost) }}</template>
        </el-table-column>
        <el-table-column label="利润总额" width="120" align="right">
          <template #default="{ row }">
            <span class="money-positive" style="font-weight: 500;">¥{{ formatMoney(row.total_profit) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="利润率" width="100" align="center">
          <template #default="{ row }">
            <el-tag size="small" :type="row.profit_rate >= 0.2 ? 'success' : row.profit_rate >= 0.1 ? 'warning' : 'danger'">
              {{ formatPercent(row.profit_rate) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="分成 (供/分/平)" width="220" align="center">
          <template #default="{ row }">
            <span style="font-size: 12px;">
              ¥{{ formatMoney(row.supplier_share) }} /
              ¥{{ formatMoney(row.distributor_share) }} /
              ¥{{ formatMoney(row.platform_share) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getSettlementStatusInfo(row.status).type" size="small">
              {{ getSettlementStatusInfo(row.status).label }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="240" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="goDetail(row)">
              <el-icon><View /></el-icon>详情
            </el-button>
            <el-button
              v-if="row.status === 'pending'"
              type="success" link
              @click="handleConfirm(row)"
            >
              <el-icon><Check /></el-icon>确认
            </el-button>
            <el-button
              v-if="row.status === 'confirmed'"
              type="primary" link
              @click="handleSettle(row)"
            >
              <el-icon><Wallet /></el-icon>结算
            </el-button>
            <el-button
              v-if="row.status === 'pending' || row.status === 'confirmed'"
              type="warning" link
              @click="handleCancel(row)"
            >
              <el-icon><Close /></el-icon>取消
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <div style="margin-top: 20px; text-align: right;">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 15, 20, 50]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadList"
          @current-change="loadList"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getSettlements, confirmSettlement, settleSettlement, cancelSettlement
} from '@/api/settlement'
import {
  formatMoney, formatPercent, SETTLEMENT_TYPES, SETTLEMENT_STATUSES,
  getSettlementTypeInfo, getSettlementStatusInfo
} from '@/utils/format'

const router = useRouter()

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(15)
const dateRange = ref([])

const filters = reactive({
  keyword: '',
  type: '',
  status: ''
})

const loadList = async () => {
  loading.value = true
  try {
    const params = {
      ...filters,
      start_date: dateRange.value?.[0],
      end_date: dateRange.value?.[1],
      per_page: pageSize.value,
      page: page.value
    }
    Object.keys(params).forEach(k => {
      if (params[k] === '' || params[k] === null || params[k] === undefined) delete params[k]
    })
    const res = await getSettlements(params)
    list.value = res.list
    total.value = res.pagination.total
  } finally {
    loading.value = false
  }
}

const resetFilters = () => {
  filters.keyword = ''
  filters.type = ''
  filters.status = ''
  dateRange.value = []
  page.value = 1
  loadList()
}

const goCreate = () => router.push('/settlements/create')
const goDetail = (row) => router.push(`/settlements/${row.id}`)

const handleConfirm = async (row) => {
  try {
    await ElMessageBox.confirm('确认此结算单？确认后将无法编辑明细。', '确认结算单', {
      type: 'warning'
    })
    await confirmSettlement(row.id)
    ElMessage.success('确认成功')
    loadList()
  } catch (e) {}
}

const handleSettle = async (row) => {
  try {
    await ElMessageBox.confirm('执行结算操作？结算后将标记为已完成。', '执行结算', {
      type: 'warning'
    })
    await settleSettlement(row.id)
    ElMessage.success('结算完成')
    loadList()
  } catch (e) {}
}

const handleCancel = async (row) => {
  try {
    await ElMessageBox.confirm('取消此结算单？', '取消结算单', {
      type: 'warning'
    })
    await cancelSettlement(row.id)
    ElMessage.success('已取消')
    loadList()
  } catch (e) {}
}

onMounted(() => {
  loadList()
})
</script>
