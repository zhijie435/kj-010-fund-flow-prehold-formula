<template>
  <div>
    <div class="filter-bar">
      <el-input
        v-model="filters.keyword"
        placeholder="搜索商品名称/SKU/条码"
        style="width: 260px;"
        clearable
        @keyup.enter="loadList"
      >
        <template #prefix>
          <el-icon><Search /></el-icon>
        </template>
      </el-input>
      <el-input
        v-model="filters.category"
        placeholder="商品分类"
        style="width: 180px;"
        clearable
      />
      <el-select v-model="filters.status" placeholder="状态" clearable style="width: 140px;">
        <el-option label="在售" :value="1" />
        <el-option label="下架" :value="0" />
      </el-select>
      <el-button type="primary" @click="loadList">
        <el-icon><Search /></el-icon>查询
      </el-button>
      <el-button @click="resetFilters">
        <el-icon><Refresh /></el-icon>重置
      </el-button>
      <div style="flex: 1;"></div>
      <el-button type="success" @click="openCreateDialog">
        <el-icon><Plus /></el-icon>新建商品
      </el-button>
    </div>

    <div class="page-card">
      <el-table :data="list" v-loading="loading" stripe>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="商品信息" min-width="220">
          <template #default="{ row }">
            <div style="display: flex; align-items: center; gap: 12px;">
              <el-image
                v-if="row.image_url"
                :src="row.image_url"
                :preview-src-list="[row.image_url]"
                fit="cover"
                style="width: 48px; height: 48px; border-radius: 6px;"
              />
              <div>
                <div style="font-weight: 500; color: #303133;">{{ row.name }}</div>
                <div style="font-size: 12px; color: #909399; margin-top: 4px;">
                  SKU: {{ row.sku }}
                  <span v-if="row.barcode" style="margin-left: 12px;">条码: {{ row.barcode }}</span>
                </div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="category" label="分类" width="120" />
        <el-table-column label="售价" width="120" align="right">
          <template #default="{ row }">
            <span style="font-weight: 500; color: #F56C6C;">¥{{ formatMoney(row.sale_price) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="成本" width="120" align="right">
          <template #default="{ row }">
            <span style="color: #909399;">¥{{ formatMoney(getTotalCost(row)) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="毛利率" width="100" align="right">
          <template #default="{ row }">
            <el-tag :type="getMarginType(row)" size="small">
              {{ formatPercent(getMargin(row)) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="stock" label="库存" width="100" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
              {{ row.status === 1 ? '在售' : '下架' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="260" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="goToDetail(row)">
              <el-icon><View /></el-icon>详情
            </el-button>
            <el-button type="warning" link @click="openEditDialog(row)">
              <el-icon><Edit /></el-icon>编辑
            </el-button>
            <el-button type="primary" link @click="calculateCost(row)">
              <el-icon><Calculator /></el-icon>计算
            </el-button>
            <el-popconfirm title="确认删除该商品？" @confirm="handleDelete(row)">
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

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑商品' : '新建商品'" width="600px">
      <el-form :model="formData" :rules="formRules" ref="formRef" label-width="100px">
        <el-form-item label="商品名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入商品名称" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="SKU" prop="sku">
              <el-input v-model="formData.sku" placeholder="商品SKU" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="条码">
              <el-input v-model="formData.barcode" placeholder="商品条码" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="分类">
              <el-input v-model="formData.category" placeholder="商品分类" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="单位">
              <el-input v-model="formData.unit" placeholder="件/套/个等" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="售价" prop="sale_price">
              <el-input-number v-model="formData.sale_price" :min="0" :precision="2" :step="1" style="width: 100%;" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="库存">
              <el-input-number v-model="formData.stock" :min="0" :step="1" style="width: 100%;" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="状态">
          <el-radio-group v-model="formData.status">
            <el-radio :value="1">在售</el-radio>
            <el-radio :value="0">下架</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="商品描述">
          <el-input v-model="formData.description" type="textarea" :rows="3" placeholder="商品描述" />
        </el-form-item>
        <el-form-item label="图片链接">
          <el-input v-model="formData.image_url" placeholder="图片URL地址" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="costDialogVisible" title="成本计算结果" width="700px">
      <div v-if="costResult">
        <el-descriptions :column="2" border size="small" style="margin-bottom: 20px;">
          <el-descriptions-item label="商品">{{ costResult.product_name }}</el-descriptions-item>
          <el-descriptions-item label="SKU">{{ costResult.product_sku }}</el-descriptions-item>
          <el-descriptions-item label="计算日期">{{ costResult.calculation_date }}</el-descriptions-item>
          <el-descriptions-item label="成本项数">{{ costResult.cost_item_count }}</el-descriptions-item>
          <el-descriptions-item label="销售单价">
            <span style="color: #F56C6C; font-weight: 500;">¥{{ formatMoney(costResult.sale_price) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="单位成本">
            <span style="font-weight: 500;">¥{{ formatMoney(costResult.total_cost) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="毛利润">
            <span class="money-positive" style="font-weight: 500;">¥{{ formatMoney(costResult.gross_profit) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="毛利率">
            <el-tag :type="costResult.gross_margin >= 0.2 ? 'success' : costResult.gross_margin >= 0.1 ? 'warning' : 'danger'" size="small">
              {{ formatPercent(costResult.gross_margin) }}
            </el-tag>
          </el-descriptions-item>
        </el-descriptions>

        <h4 style="margin: 0 0 12px 0;">成本明细</h4>
        <el-table :data="costResult.breakdown" size="small" border>
          <el-table-column prop="cost_type_name" label="成本类型" width="140" />
          <el-table-column label="明细">
            <template #default="{ row }">
              <div class="cost-breakdown">
                <span v-for="item in row.items" :key="item.id" class="cost-item">
                  {{ item.cost_name }}: ¥{{ formatMoney(item.total_cost) }}
                </span>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="小计" width="140" align="right">
            <template #default="{ row }">
              <span style="font-weight: 500;">¥{{ formatMoney(row.total) }}</span>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getProducts, createProduct, updateProduct, deleteProduct, calculateProductCost
} from '@/api/product'
import { formatMoney, formatPercent } from '@/utils/format'

const router = useRouter()

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(15)

const filters = reactive({
  keyword: '',
  category: '',
  status: ''
})

const dialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)
const formData = reactive({
  id: null,
  name: '',
  sku: '',
  barcode: '',
  category: '',
  unit: '',
  sale_price: 0,
  stock: 0,
  status: 1,
  description: '',
  image_url: ''
})

const formRules = {
  name: [{ required: true, message: '请输入商品名称', trigger: 'blur' }],
  sku: [{ required: true, message: '请输入SKU', trigger: 'blur' }],
  sale_price: [{ required: true, message: '请输入售价', trigger: 'blur' }]
}

const costDialogVisible = ref(false)
const costResult = ref(null)

const loadList = async () => {
  loading.value = true
  try {
    const params = {
      ...filters,
      per_page: pageSize.value,
      page: page.value
    }
    Object.keys(params).forEach(k => {
      if (params[k] === '' || params[k] === null || params[k] === undefined) delete params[k]
    })
    const res = await getProducts(params)
    list.value = res.list
    total.value = res.pagination.total
  } finally {
    loading.value = false
  }
}

const resetFilters = () => {
  filters.keyword = ''
  filters.category = ''
  filters.status = ''
  page.value = 1
  loadList()
}

const getTotalCost = (row) => {
  if (!row.active_costs || !row.active_costs?.length) return 0
  return row.active_costs.reduce((sum, c) => sum + parseFloat(c.total_cost || 0), 0)
}

const getMargin = (row) => {
  const price = parseFloat(row.sale_price || 0)
  const cost = getTotalCost(row)
  if (price <= 0) return 0
  return (price - cost) / price
}

const getMarginType = (row) => {
  const margin = getMargin(row)
  if (margin >= 0.3) return 'success'
  if (margin >= 0.15) return 'warning'
  if (margin > 0) return ''
  return 'danger'
}

const openCreateDialog = () => {
  isEdit.value = false
  Object.assign(formData, {
    id: null,
    name: '',
    sku: '',
    barcode: '',
    category: '',
    unit: '',
    sale_price: 0,
    stock: 0,
    status: 1,
    description: '',
    image_url: ''
  })
  dialogVisible.value = true
}

const openEditDialog = (row) => {
  isEdit.value = true
  Object.assign(formData, {
    id: row.id,
    name: row.name,
    sku: row.sku,
    barcode: row.barcode || '',
    category: row.category || '',
    unit: row.unit || '',
    sale_price: row.sale_price,
    stock: row.stock,
    status: row.status,
    description: row.description || '',
    image_url: row.image_url || ''
  })
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (valid) {
      try {
        if (isEdit.value) {
          await updateProduct(formData.id, { ...formData })
          ElMessage.success('更新成功')
        } else {
          await createProduct({ ...formData })
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
    await deleteProduct(row.id)
    ElMessage.success('删除成功')
    loadList()
  } catch (e) {}
}

const goToDetail = (row) => {
  router.push(`/products/${row.id}`)
}

const calculateCost = async (row) => {
  try {
    costResult.value = await calculateProductCost(row.id)
    costDialogVisible.value = true
  } catch (e) {}
}

onMounted(() => {
  loadList()
})
</script>
