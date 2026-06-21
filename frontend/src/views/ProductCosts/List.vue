<template>
  <div>
    <div class="filter-bar">
      <el-select
        v-model="filters.product_id"
        placeholder="选择商品"
        filterable
        remote
        :remote-method="searchProducts"
        :loading="productLoading"
        style="width: 280px;"
        clearable
        @change="loadList"
      >
        <el-option
          v-for="p in productOptions"
          :key="p.id"
          :label="`${p.name} (${p.sku})`"
          :value="p.id"
        />
      </el-select>
      <el-select v-model="filters.cost_type" placeholder="成本类型" clearable style="width: 160px;" @change="loadList">
        <el-option
          v-for="(info, key) in COST_TYPES"
          :key="key"
          :label="info.label"
          :value="key"
        />
      </el-select>
      <el-select v-model="filters.is_active" placeholder="状态" clearable style="width: 140px;" @change="loadList">
        <el-option label="启用" :value="1" />
        <el-option label="停用" :value="0" />
      </el-select>
      <el-date-picker
        v-model="filters.date_range"
        type="daterange"
        range-separator="至"
        start-placeholder="生效起"
        end-placeholder="生效止"
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
      <el-button type="success" @click="openCreateDialog">
        <el-icon><Plus /></el-icon>新增成本项
      </el-button>
    </div>

    <div class="page-card">
      <el-table :data="list" v-loading="loading" stripe>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="商品" min-width="200">
          <template #default="{ row }">
            <div>
              <div style="font-weight: 500;">{{ row.product?.name || '-' }}</div>
              <div style="font-size: 12px; color: #909399;">SKU: {{ row.product?.sku || '-' }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="cost_name" label="成本项" width="160" />
        <el-table-column label="类型" width="120">
          <template #default="{ row }">
            <el-tag :type="getCostTypeInfo(row.cost_type).type" size="small">
              {{ getCostTypeInfo(row.cost_type).label }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="单位成本" width="120" align="right">
          <template #default="{ row }">¥{{ formatMoney(row.unit_cost) }}</template>
        </el-table-column>
        <el-table-column prop="quantity" label="数量" width="80" align="center" />
        <el-table-column label="小计" width="130" align="right">
          <template #default="{ row }">
            <span style="font-weight: 500;">¥{{ formatMoney(row.total_cost) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="有效期" width="240">
          <template #default="{ row }">
            {{ row.effective_date }} ~ {{ row.expiry_date || '永久' }}
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
              {{ row.is_active ? '启用' : '停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="openEditDialog(row)">
              <el-icon><Edit /></el-icon>编辑
            </el-button>
            <el-popconfirm title="确认删除该成本项？" @confirm="handleDelete(row)">
              <template #reference>
                <el-button type="danger" link>
                  <el-icon><Delete /></el-icon>删除
                </el-button>
              </template>
            </el-popconfirm>
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

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑成本项' : '新增成本项'" width="560px">
      <el-form :model="formData" :rules="formRules" ref="formRef" label-width="100px">
        <el-form-item label="选择商品" prop="product_id">
          <el-select
            v-model="formData.product_id"
            placeholder="搜索选择商品"
            filterable
            remote
            :remote-method="searchProducts"
            :loading="productLoading"
            style="width: 100%;"
          >
            <el-option
              v-for="p in productOptions"
              :key="p.id"
              :label="`${p.name} (${p.sku}) - ¥${formatMoney(p.sale_price)}`"
              :value="p.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="成本类型" prop="cost_type">
          <el-select v-model="formData.cost_type" style="width: 100%;">
            <el-option
              v-for="(info, key) in COST_TYPES"
              :key="key"
              :label="info.label"
              :value="key"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="成本名称" prop="cost_name">
          <el-input v-model="formData.cost_name" placeholder="如：商品采购价、快递费、包装费等" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="单位成本" prop="unit_cost">
              <el-input-number v-model="formData.unit_cost" :min="0" :precision="2" :step="1" style="width: 100%;" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="数量" prop="quantity">
              <el-input-number v-model="formData.quantity" :min="1" :step="1" style="width: 100%;" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="生效日期" prop="effective_date">
              <el-date-picker
                v-model="formData.effective_date"
                type="date"
                value-format="YYYY-MM-DD"
                style="width: 100%;"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="失效日期">
              <el-date-picker
                v-model="formData.expiry_date"
                type="date"
                value-format="YYYY-MM-DD"
                style="width: 100%;"
                :disabled-date="disableExpiry"
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="启用状态">
          <el-switch v-model="formData.is_active" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="formData.remark" type="textarea" :rows="2" />
        </el-form-item>
        <el-alert
          v-if="formData.unit_cost && formData.quantity"
          :title="'自动计算小计: ¥' + formatMoney(parseFloat(formData.unit_cost) * parseInt(formData.quantity))"
          type="info"
          show-icon
          :closable="false"
        />
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getAllProducts } from '@/api/product'
import {
  getProductCosts, createProductCost, updateProductCost, deleteProductCost
} from '@/api/productCost'
import { formatMoney, COST_TYPES, getCostTypeInfo } from '@/utils/format'

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(15)

const filters = reactive({
  product_id: '',
  cost_type: '',
  is_active: '',
  date_range: []
})

const productLoading = ref(false)
const productOptions = ref([])

const dialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)
const formData = reactive({
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

const formRules = {
  product_id: [{ required: true, message: '请选择商品', trigger: 'change' }],
  cost_type: [{ required: true, message: '请选择成本类型', trigger: 'change' }],
  cost_name: [{ required: true, message: '请输入成本名称', trigger: 'blur' }],
  unit_cost: [{ required: true, message: '请输入单位成本', trigger: 'blur' }],
  effective_date: [{ required: true, message: '请选择生效日期', trigger: 'change' }]
}

const disableExpiry = (date) => {
  if (!formData.effective_date) return false
  return date.getTime() < new Date(formData.effective_date).getTime()
}

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

const loadList = async () => {
  if (!filters.product_id) {
    list.value = []
    total.value = 0
    return
  }
  loading.value = true
  try {
    const params = {
      cost_type: filters.cost_type || undefined,
      is_active: filters.is_active !== '' ? filters.is_active : undefined,
      per_page: pageSize.value,
      page: page.value
    }
    const res = await getProductCosts(filters.product_id, params)
    list.value = res.list
    total.value = res.pagination.total
  } finally {
    loading.value = false
  }
}

const resetFilters = () => {
  filters.product_id = ''
  filters.cost_type = ''
  filters.is_active = ''
  filters.date_range = []
  page.value = 1
  loadList()
}

const openCreateDialog = async () => {
  isEdit.value = false
  Object.assign(formData, {
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
  if (!productOptions.value.length) await searchProducts('')
  dialogVisible.value = true
}

const openEditDialog = async (row) => {
  isEdit.value = true
  Object.assign(formData, {
    id: row.id,
    product_id: row.product_id,
    cost_type: row.cost_type,
    cost_name: row.cost_name,
    unit_cost: row.unit_cost,
    quantity: row.quantity,
    effective_date: row.effective_date,
    expiry_date: row.expiry_date || '',
    is_active: row.is_active,
    remark: row.remark || ''
  })
  if (!productOptions.value.length) await searchProducts('')
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (valid) {
      try {
        if (isEdit.value) {
          await updateProductCost(formData.id, { ...formData })
          ElMessage.success('更新成功')
        } else {
          await createProductCost({ ...formData })
          ElMessage.success('创建成功')
        }
        dialogVisible.value = false
        loadList()
      } catch (e) {}
    }
  })
}

const handleDelete = async (row) => {
  try {
    await deleteProductCost(row.id)
    ElMessage.success('删除成功')
    loadList()
  } catch (e) {}
}

onMounted(async () => {
  await searchProducts('')
})
</script>
