<template>
  <div v-loading="loading">
    <div class="action-bar">
      <el-page-header :content="product?.name || '商品详情'" @back="router.back()">
        <template #content>
          <span style="font-size: 20px; font-weight: 600;">{{ product?.name }}</span>
          <el-tag size="small" style="margin-left: 12px;">SKU: {{ product?.sku }}</el-tag>
        </template>
      </el-page-header>
    </div>

    <el-row :gutter="20" v-if="product">
      <el-col :span="16">
        <div class="page-card">
          <h3 style="margin: 0 0 16px 0;">基本信息</h3>
          <el-descriptions :column="2" border>
            <el-descriptions-item label="商品名称">{{ product.name }}</el-descriptions-item>
            <el-descriptions-item label="SKU">{{ product.sku }}</el-descriptions-item>
            <el-descriptions-item label="条码">{{ product.barcode || '-' }}</el-descriptions-item>
            <el-descriptions-item label="分类">{{ product.category || '-' }}</el-descriptions-item>
            <el-descriptions-item label="单位">{{ product.unit || '-' }}</el-descriptions-item>
            <el-descriptions-item label="库存">{{ product.stock }}</el-descriptions-item>
            <el-descriptions-item label="售价">
              <span style="color: #F56C6C; font-weight: 600;">¥{{ formatMoney(product.sale_price) }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag :type="product.status === 1 ? 'success' : 'info'" size="small">
                {{ product.status === 1 ? '在售' : '下架' }}
              </el-tag>
            </el-descriptions-item>
          </el-descriptions>
        </div>

        <div class="page-card">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0;">成本明细</h3>
            <div>
              <el-date-picker
                v-model="calcDate"
                type="date"
                placeholder="选择计算日期"
                value-format="YYYY-MM-DD"
                style="width: 160px; margin-right: 8px;"
              />
              <el-button type="primary" @click="loadCostResult">
                <el-icon><Refresh /></el-icon>重新计算
              </el-button>
              <el-button type="success" style="margin-left: 8px;" @click="openCostDialog">
                <el-icon><Plus /></el-icon>添加成本项
              </el-button>
            </div>
          </div>

          <div v-if="costResult" style="margin-bottom: 20px;">
            <el-alert
              :title="'当前总成本: ¥' + formatMoney(costResult.total_cost) + '，毛利率: ' + formatPercent(costResult.gross_margin)"
              :type="costResult.gross_margin >= 0.2 ? 'success' : costResult.gross_margin >= 0.1 ? 'warning' : 'error'"
              show-icon
              :closable="false"
            />
          </div>

          <el-table :data="product.costs || []" stripe>
            <el-table-column prop="cost_name" label="成本项" width="180" />
            <el-table-column label="类型" width="120">
              <template #default="{ row }">
                <el-tag :type="getCostTypeInfo(row.cost_type).type" size="small" effect="light">
                  {{ getCostTypeInfo(row.cost_type).label }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="单位成本" width="120" align="right">
              <template #default="{ row }">¥{{ formatMoney(row.unit_cost) }}</template>
            </el-table-column>
            <el-table-column prop="quantity" label="数量" width="80" align="center" />
            <el-table-column label="小计" width="120" align="right">
              <template #default="{ row }">
                <span style="font-weight: 500;">¥{{ formatMoney(row.total_cost) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="有效期" width="240">
              <template #default="{ row }">
                {{ row.effective_date }} ~ {{ row.expiry_date || '永久' }}
              </template>
            </el-table-column>
            <el-table-column label="状态" width="80" align="center">
              <template #default="{ row }">
                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                  {{ row.is_active ? '启用' : '停用' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="160" fixed="right">
              <template #default="{ row }">
                <el-button type="primary" link @click="openEditCostDialog(row)">
                  <el-icon><Edit /></el-icon>编辑
                </el-button>
                <el-popconfirm title="确认删除？" @confirm="handleDeleteCost(row)">
                  <template #reference>
                    <el-button type="danger" link><el-icon><Delete /></el-icon>删除</el-button>
                  </template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </el-col>

      <el-col :span="8">
        <div class="page-card">
          <h3 style="margin: 0 0 16px 0;">成本构成</h3>
          <div v-if="costResult?.breakdown?.length">
            <el-table :data="costResult.breakdown" size="small" border>
              <el-table-column prop="cost_type_name" label="类型" />
              <el-table-column label="金额" align="right">
                <template #default="{ row }">¥{{ formatMoney(row.total) }}</template>
              </el-table-column>
              <el-table-column label="占比" width="100" align="right">
                <template #default="{ row }">
                  {{ costResult.total_cost > 0 ? formatPercent(row.total / costResult.total_cost) : '0%' }}
                </template>
              </el-table-column>
            </el-table>

            <div style="margin-top: 20px; padding: 16px; background: #f5f7fa; border-radius: 6px;">
              <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span>售价</span>
                <span style="color: #F56C6C;">¥{{ formatMoney(costResult.sale_price) }}</span>
              </div>
              <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span>成本</span>
                <span>¥{{ formatMoney(costResult.total_cost) }}</span>
              </div>
              <el-divider style="margin: 8px 0;" />
              <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span>毛利润</span>
                <span class="money-positive" style="font-weight: 600;">¥{{ formatMoney(costResult.gross_profit) }}</span>
              </div>
              <div style="display: flex; justify-content: space-between;">
                <span>毛利率</span>
                <el-tag :type="costResult.gross_margin >= 0.2 ? 'success' : costResult.gross_margin >= 0.1 ? 'warning' : 'danger'" size="small">
                  {{ formatPercent(costResult.gross_margin) }}
                </el-tag>
              </div>
            </div>
          </div>
          <el-empty v-else description="暂无成本数据" />
        </div>
      </el-col>
    </el-row>

    <el-dialog v-model="costDialogVisible" :title="isCostEdit ? '编辑成本项' : '添加成本项'" width="520px">
      <el-form :model="costForm" :rules="costRules" ref="costFormRef" label-width="100px">
        <el-form-item label="成本类型" prop="cost_type">
          <el-select v-model="costForm.cost_type" style="width: 100%;">
            <el-option
              v-for="(info, key) in COST_TYPES"
              :key="key"
              :label="info.label"
              :value="key"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="成本名称" prop="cost_name">
          <el-input v-model="costForm.cost_name" placeholder="如：商品采购价、快递费等" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="单位成本" prop="unit_cost">
              <el-input-number v-model="costForm.unit_cost" :min="0" :precision="2" :step="1" style="width: 100%;" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="数量" prop="quantity">
              <el-input-number v-model="costForm.quantity" :min="1" :step="1" style="width: 100%;" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="生效日期" prop="effective_date">
              <el-date-picker
                v-model="costForm.effective_date"
                type="date"
                value-format="YYYY-MM-DD"
                style="width: 100%;"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="失效日期">
              <el-date-picker
                v-model="costForm.expiry_date"
                type="date"
                value-format="YYYY-MM-DD"
                style="width: 100%;"
                :disabled-date="disableExpiryDate"
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="启用状态">
          <el-switch v-model="costForm.is_active" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="costForm.remark" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="costDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitCost">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import {
  getProduct, calculateProductCost
} from '@/api/product'
import {
  createProductCost, updateProductCost, deleteProductCost
} from '@/api/productCost'
import { formatMoney, formatPercent, COST_TYPES, getCostTypeInfo } from '@/utils/format'

const route = useRoute()
const router = useRouter()

const productId = computed(() => route.params.id)
const loading = ref(false)
const product = ref(null)
const costResult = ref(null)
const calcDate = ref(new Date().toISOString().slice(0, 10))

const costDialogVisible = ref(false)
const isCostEdit = ref(false)
const costFormRef = ref(null)
const costForm = reactive({
  id: null,
  product_id: null,
  cost_type: 'purchase',
  cost_name: '',
  unit_cost: 0,
  quantity: 1,
  effective_date: new Date().toISOString().slice(0, 10),
  expiry_date: '',
  is_active: 1,
  remark: ''
})

const costRules = {
  cost_type: [{ required: true, message: '请选择成本类型', trigger: 'change' }],
  cost_name: [{ required: true, message: '请输入成本名称', trigger: 'blur' }],
  unit_cost: [{ required: true, message: '请输入单位成本', trigger: 'blur' }],
  effective_date: [{ required: true, message: '请选择生效日期', trigger: 'change' }]
}

const disableExpiryDate = (date) => {
  if (!costForm.effective_date) return false
  return date.getTime() < new Date(costForm.effective_date).getTime()
}

const loadProduct = async () => {
  loading.value = true
  try {
    product.value = await getProduct(productId.value)
  } finally {
    loading.value = false
  }
}

const loadCostResult = async () => {
  try {
    costResult.value = await calculateProductCost(productId.value, { date: calcDate.value })
  } catch (e) {}
}

const openCostDialog = () => {
  isCostEdit.value = false
  Object.assign(costForm, {
    id: null,
    product_id: productId.value,
    cost_type: 'purchase',
    cost_name: '',
    unit_cost: 0,
    quantity: 1,
    effective_date: new Date().toISOString().slice(0, 10),
    expiry_date: '',
    is_active: 1,
    remark: ''
  })
  costDialogVisible.value = true
}

const openEditCostDialog = (row) => {
  isCostEdit.value = true
  Object.assign(costForm, {
    id: row.id,
    product_id: productId.value,
    cost_type: row.cost_type,
    cost_name: row.cost_name,
    unit_cost: row.unit_cost,
    quantity: row.quantity,
    effective_date: row.effective_date,
    expiry_date: row.expiry_date || '',
    is_active: row.is_active,
    remark: row.remark || ''
  })
  costDialogVisible.value = true
}

const handleSubmitCost = async () => {
  if (!costFormRef.value) return
  await costFormRef.value.validate(async (valid) => {
    if (valid) {
      try {
        if (isCostEdit.value) {
          await updateProductCost(costForm.id, { ...costForm })
          ElMessage.success('更新成功')
        } else {
          await createProductCost({ ...costForm })
          ElMessage.success('添加成功')
        }
        costDialogVisible.value = false
        loadProduct()
        loadCostResult()
      } catch (e) {}
    }
  })
}

const handleDeleteCost = async (row) => {
  try {
    await deleteProductCost(row.id)
    ElMessage.success('删除成功')
    loadProduct()
    loadCostResult()
  } catch (e) {}
}

onMounted(() => {
  loadProduct()
  loadCostResult()
})
</script>
