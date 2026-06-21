<template>
  <div>
    <h2 class="page-title">成本计算工具</h2>

    <el-tabs v-model="activeTab" type="card" style="margin-bottom: 20px;">
      <el-tab-pane label="成本项计算" name="cost-item">
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
      </el-tab-pane>

      <el-tab-pane label="等级折扣成本" name="grade-discount">
        <el-row :gutter="20">
          <el-col :span="10">
            <div class="page-card">
              <h3 style="margin: 0 0 16px 0;">等级折扣成本计算</h3>

              <el-form label-width="100px">
                <el-form-item label="选择等级">
                  <el-select v-model="grade" style="width: 100%;" @change="onGradeChange">
                    <el-option
                      v-for="(info, key) in gradeOptions"
                      :key="key"
                      :label="`${info.name} (${formatDiscountMultiplier(info.discount_rate)})`"
                      :value="key"
                    />
                  </el-select>
                </el-form-item>
                <el-form-item label="折扣率">
                  <el-tag type="warning" size="large">{{ gradeDiscountDisplay }}</el-tag>
                </el-form-item>
              </el-form>

              <el-divider>单商品计算</el-divider>

              <el-form label-width="100px">
                <el-form-item label="选择商品">
                  <el-select
                    v-model="gradeSelectedProductId"
                    placeholder="搜索选择商品"
                    filterable
                    remote
                    :remote-method="searchProducts"
                    :loading="productLoading"
                    style="width: 100%;"
                    @change="onGradeProductChange"
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

              <el-divider>批量增加商品成本</el-divider>

              <el-form label-width="100px">
                <el-form-item label="添加商品">
                  <el-select
                    v-model="newIncreasedItem.product_id"
                    placeholder="选择商品添加"
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
                <el-form-item label="数量">
                  <el-input-number v-model="newIncreasedItem.quantity" :min="1" :step="1" style="width: 100%;" />
                </el-form-item>
                <el-form-item>
                  <el-button type="primary" @click="addIncreasedItem" :disabled="!newIncreasedItem.product_id">
                    <el-icon><Plus /></el-icon>添加
                  </el-button>
                  <el-button @click="clearIncreasedItems">
                    <el-icon><Delete /></el-icon>清空
                  </el-button>
                </el-form-item>
              </el-form>

              <div style="padding: 16px; background: #ecf5ff; border-radius: 6px; border: 1px solid #d9ecff;">
                <div style="font-weight: 500; color: #409EFF; margin-bottom: 8px;">
                  <el-icon><InfoFilled /></el-icon> 计算公式
                </div>
                <div style="font-size: 13px; color: #606266; line-height: 1.8;">
                  <b>增加商品成本 = Σ(供货价 × (1 - 等级折扣率) × 数量)</b>
                  <ul style="margin: 8px 0 0; padding-left: 20px;">
                    <li>单位成本 = 供货价 × (1 - 等级折扣率)</li>
                    <li>总成本 = 单位成本 × 数量</li>
                    <li>Σ 表示所有商品成本求和</li>
                  </ul>
                </div>
              </div>
            </div>
          </el-col>

          <el-col :span="14">
            <div class="page-card" v-if="gradeSingleResult">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0;">单商品等级折扣成本</h3>
                <el-tag type="warning">{{ gradeSingleResult.grade_name }}</el-tag>
              </div>

              <el-descriptions :column="2" border size="small" style="margin-bottom: 20px;">
                <el-descriptions-item label="商品">{{ gradeSingleResult.product_name }}</el-descriptions-item>
                <el-descriptions-item label="SKU">{{ gradeSingleResult.product_sku }}</el-descriptions-item>
                <el-descriptions-item label="销售单价">
                  <span style="color: #F56C6C; font-weight: 600;">¥{{ formatMoney(gradeSingleResult.sale_price) }}</span>
                </el-descriptions-item>
                <el-descriptions-item label="供货价">
                  <span style="font-weight: 500;">¥{{ formatMoney(gradeSingleResult.supplier_price) }}</span>
                </el-descriptions-item>
                <el-descriptions-item label="等级折扣">
                  <el-tag type="warning" size="small">{{ formatDiscountMultiplier(gradeSingleResult.discount_rate) }}{{ gradeSingleResult.discount_rate === 0 ? '（无折扣）' : '' }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="单位成本">
                  <span style="font-weight: 600; color: #E6A23C;">¥{{ formatMoney(gradeSingleResult.unit_cost) }}</span>
                </el-descriptions-item>
                <el-descriptions-item label="毛利润">
                  <span class="money-positive" style="font-weight: 600;">¥{{ formatMoney(gradeSingleResult.gross_profit) }}</span>
                </el-descriptions-item>
                <el-descriptions-item label="毛利率">
                  <el-tag
                    :type="gradeSingleResult.gross_margin >= 0.3 ? 'success' : gradeSingleResult.gross_margin >= 0.15 ? 'warning' : 'danger'"
                    size="small"
                  >
                    {{ formatPercent(gradeSingleResult.gross_margin) }}
                  </el-tag>
                </el-descriptions-item>
              </el-descriptions>
            </div>

            <div class="page-card" v-if="increasedItems.length > 0">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0;">增加商品成本明细</h3>
                <el-tag type="warning">{{ gradeName }} - {{ gradeDiscountDisplay }}</el-tag>
              </div>

              <el-table :data="increasedItems" size="small" border stripe>
                <el-table-column label="商品信息" min-width="180">
                  <template #default="{ row }">
                    <div>
                      <div style="font-weight: 500;">{{ row.product_name || '未选择' }}</div>
                      <div style="font-size: 12px; color: #909399;">SKU: {{ row.product_sku || '-' }}</div>
                    </div>
                  </template>
                </el-table-column>
                <el-table-column label="供货价" width="120" align="right">
                  <template #default="{ row, $index }">
                    <el-input-number
                      v-model="row.supplier_price"
                      :min="0"
                      :precision="2"
                      :step="1"
                      size="small"
                      style="width: 100%;"
                      @change="calculateIncreasedCost"
                    />
                  </template>
                </el-table-column>
                <el-table-column label="数量" width="100" align="center">
                  <template #default="{ row, $index }">
                    <el-input-number
                      v-model="row.quantity"
                      :min="1"
                      :step="1"
                      size="small"
                      style="width: 100%;"
                      @change="calculateIncreasedCost"
                    />
                  </template>
                </el-table-column>
                <el-table-column label="单位成本" width="120" align="right">
                  <template #default="{ row }">
                    <span style="color: #E6A23C;">¥{{ formatMoney(row.unit_cost) }}</span>
                  </template>
                </el-table-column>
                <el-table-column label="小计成本" width="130" align="right">
                  <template #default="{ row }">
                    <span style="font-weight: 500; color: #E6A23C;">¥{{ formatMoney(row.total_cost) }}</span>
                  </template>
                </el-table-column>
                <el-table-column label="操作" width="80" align="center" fixed="right">
                  <template #default="{ $index }">
                    <el-button type="danger" link @click="removeIncreasedItem($index)">
                      <el-icon><Delete /></el-icon>
                    </el-button>
                  </template>
                </el-table-column>
              </el-table>

              <div style="margin-top: 20px; padding: 16px; background: #fdf6ec; border-radius: 6px; border: 1px solid #faecd8;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <div style="font-weight: 500; color: #E6A23C;">
                    <el-icon><Money /></el-icon> 增加商品总成本
                  </div>
                  <div style="font-size: 24px; font-weight: 700; color: #E6A23C;">
                    ¥{{ formatMoney(totalIncreasedCost) }}
                  </div>
                </div>
                <div style="margin-top: 8px; font-size: 13px; color: #909399;">
                  共 {{ increasedItems.length }} 种商品，{{ totalIncreasedQuantity }} 件
                </div>
              </div>
            </div>

            <el-empty v-if="!gradeSingleResult && increasedItems.length === 0" description="请选择商品进行等级折扣成本计算" />
          </el-col>
        </el-row>
      </el-tab-pane>

      <el-tab-pane label="预估运费" name="shipping">
        <el-row :gutter="20">
          <el-col :span="10">
            <div class="page-card">
              <h3 style="margin: 0 0 16px 0;">运费预估参数</h3>

              <el-form label-width="100px">
                <el-form-item label="选择商品">
                  <el-select
                    v-model="shipProductId"
                    placeholder="搜索选择商品"
                    filterable
                    remote
                    :remote-method="searchProducts"
                    :loading="productLoading"
                    style="width: 100%;"
                    @change="onShipProductChange"
                  >
                    <el-option
                      v-for="p in productOptions"
                      :key="p.id"
                      :label="`${p.name} (${p.sku})`"
                      :value="p.id"
                    />
                  </el-select>
                </el-form-item>
                <el-form-item label="物流商模板">
                  <el-select v-model="shipTemplate" style="width: 100%;" @change="onTemplateChange">
                    <el-option
                      v-for="(tpl, key) in shippingTemplates"
                      :key="key"
                      :label="tpl.name"
                      :value="key"
                    />
                  </el-select>
                </el-form-item>
                <el-form-item label="目的地">
                  <el-select v-model="shipDestination" style="width: 100%;" :disabled="!currentTemplateZones.length">
                    <el-option
                      v-for="zone in currentTemplateZones"
                      :key="zone.key"
                      :label="zone.name"
                      :value="zone.key"
                    />
                  </el-select>
                </el-form-item>
                <el-form-item label="数量">
                  <el-input-number v-model="shipQuantity" :min="1" :step="1" style="width: 100%;" />
                </el-form-item>

                <el-divider>重量与尺寸（可覆盖）</el-divider>

                <el-form-item label="重量(kg)">
                  <el-input-number v-model="shipWeight" :min="0" :precision="2" :step="0.1" style="width: 100%;" />
                </el-form-item>
                <el-row :gutter="16">
                  <el-col :span="8">
                    <el-form-item label="长(cm)" label-width="70px">
                      <el-input-number v-model="shipLength" :min="0" :precision="2" :step="1" style="width: 100%;" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="宽(cm)" label-width="70px">
                      <el-input-number v-model="shipWidth" :min="0" :precision="2" :step="1" style="width: 100%;" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="高(cm)" label-width="70px">
                      <el-input-number v-model="shipHeight" :min="0" :precision="2" :step="1" style="width: 100%;" />
                    </el-form-item>
                  </el-col>
                </el-row>

                <el-form-item>
                  <el-button type="primary" @click="calculateShipping" :loading="shipLoading" :disabled="!shipProductId || !shipDestination">
                    <el-icon><Calculator /></el-icon>预估运费
                  </el-button>
                  <el-button @click="compareShippingFee" :loading="shipCompareLoading" :disabled="!shipProductId || !shipDestination">
                    <el-icon><Switch /></el-icon>对比物流商
                  </el-button>
                  <el-button @click="resetShipping">
                    <el-icon><Refresh /></el-icon>重置
                  </el-button>
                </el-form-item>
              </el-form>

              <div style="padding: 16px; background: #f0f9eb; border-radius: 6px; border: 1px solid #e1f3d8;">
                <div style="font-weight: 500; color: #67c23a; margin-bottom: 8px;">
                  <el-icon><InfoFilled /></el-icon> 计算规则
                </div>
                <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #606266; line-height: 1.8;">
                  <li>计费重量 = max(实际重量, 体积重量)</li>
                  <li>体积重量 = 长×宽×高×数量 ÷ 体积系数</li>
                  <li>运费 = 首重运费 + ceil(计费重量 - 首重) × 续重单价</li>
                  <li>未超首重时按首重运费收取</li>
                </ul>
              </div>
            </div>
          </el-col>

          <el-col :span="14">
            <div class="page-card" v-if="shipResult">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0;">预估运费结果</h3>
                <el-tag type="success">{{ shipResult.template_name }} · {{ shipResult.destination_name }}</el-tag>
              </div>

              <el-descriptions :column="2" border size="small" style="margin-bottom: 20px;">
                <el-descriptions-item label="商品">{{ shipResult.product_name }}</el-descriptions-item>
                <el-descriptions-item label="SKU">{{ shipResult.product_sku }}</el-descriptions-item>
                <el-descriptions-item label="数量">{{ shipResult.quantity }}</el-descriptions-item>
                <el-descriptions-item label="计费依据">
                  <el-tag size="small" :type="shipResult.weight_basis === 'volumetric' ? 'warning' : shipResult.weight_basis === 'actual' ? '' : 'info'">
                    {{ weightBasisLabel(shipResult.weight_basis) }}
                  </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="实际重量">{{ formatMoney(shipResult.actual_weight) }} kg</el-descriptions-item>
                <el-descriptions-item label="体积重量">{{ formatMoney(shipResult.volumetric_weight) }} kg</el-descriptions-item>
                <el-descriptions-item label="体积系数">{{ shipResult.volumetric_divisor }}</el-descriptions-item>
                <el-descriptions-item label="尺寸">
                  {{ formatMoney(shipResult.length) }} × {{ formatMoney(shipResult.width) }} × {{ formatMoney(shipResult.height) }} cm
                </el-descriptions-item>
                <el-descriptions-item label="首重">{{ formatMoney(shipResult.first_weight) }} kg</el-descriptions-item>
                <el-descriptions-item label="续重">{{ shipResult.additional_units }} kg</el-descriptions-item>
                <el-descriptions-item label="首重运费">¥{{ formatMoney(shipResult.first_weight_fee) }}</el-descriptions-item>
                <el-descriptions-item label="续重单价">¥{{ formatMoney(shipResult.additional_weight_fee) }}/kg</el-descriptions-item>
                <el-descriptions-item label="计费重量">
                  <span style="font-weight: 600; color: #409EFF;">{{ formatMoney(shipResult.chargeable_weight) }} kg</span>
                </el-descriptions-item>
                <el-descriptions-item label="预估运费">
                  <span style="font-size: 20px; font-weight: 700; color: #F56C6C;">¥{{ formatMoney(shipResult.shipping_fee) }}</span>
                </el-descriptions-item>
              </el-descriptions>

              <el-alert :title="shipResult.calculation_detail" type="info" :closable="false" show-icon style="margin-bottom: 16px;" />

              <div style="padding: 12px 16px; background: #f5f7fa; border-radius: 6px; font-size: 13px; color: #909399;">
                <el-icon><InfoFilled /></el-icon> {{ shipResult.formula }}
              </div>
            </div>

            <div class="page-card" v-if="shipCompareResult">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0;">物流商运费对比</h3>
                <el-tag type="warning">目的地：{{ shipCompareResult.destination }}</el-tag>
              </div>

              <el-table :data="shipCompareResult.estimates" size="small" border stripe>
                <el-table-column label="物流商" min-width="140">
                  <template #default="{ row }">
                    <span style="font-weight: 500;">{{ row.template_name }}</span>
                    <el-tag v-if="shipCompareResult.cheapest && row.template === shipCompareResult.cheapest.template" type="success" size="small" style="margin-left: 8px;">最低</el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="体积系数" width="100" align="center">
                  <template #default="{ row }">{{ row.volumetric_divisor }}</template>
                </el-table-column>
                <el-table-column label="计费重量" width="110" align="right">
                  <template #default="{ row }">{{ formatMoney(row.chargeable_weight) }} kg</template>
                </el-table-column>
                <el-table-column label="首重运费" width="100" align="right">
                  <template #default="{ row }">¥{{ formatMoney(row.first_weight_fee) }}</template>
                </el-table-column>
                <el-table-column label="续重单价" width="100" align="right">
                  <template #default="{ row }">¥{{ formatMoney(row.additional_weight_fee) }}</template>
                </el-table-column>
                <el-table-column label="预估运费" width="120" align="right">
                  <template #default="{ row }">
                    <span style="font-weight: 600; color: #F56C6C;">¥{{ formatMoney(row.shipping_fee) }}</span>
                  </template>
                </el-table-column>
              </el-table>

              <div style="margin-top: 16px; padding: 12px 16px; background: #fdf6ec; border-radius: 6px; border: 1px solid #faecd8;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <span style="color: #E6A23C; font-weight: 500;">最优物流商</span>
                  <span style="font-weight: 700; color: #E6A23C;">
                    {{ shipCompareResult.cheapest?.template_name }} · ¥{{ formatMoney(shipCompareResult.cheapest?.shipping_fee || 0) }}
                  </span>
                </div>
              </div>
            </div>

            <el-empty v-if="!shipResult && !shipCompareResult" description="请选择商品、物流商与目的地进行运费预估" />
          </el-col>
        </el-row>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import {
  getAllProducts,
  calculateProductCost,
  batchCalculateCost,
  getGradeDiscounts,
  calculateProductCostByGrade,
  getShippingTemplates,
  estimateShipping,
  compareShipping
} from '@/api/product'
import { formatMoney, formatPercent, formatDiscountMultiplier } from '@/utils/format'

const activeTab = ref('cost-item')
const calcDate = ref(new Date().toISOString().slice(0, 10))
const productLoading = ref(false)
const productOptions = ref([])
const productMap = ref({})
const selectedProductId = ref(null)
const selectedProductIds = ref([])
const calcLoading = ref(false)

const singleResult = ref(null)
const batchResult = ref(null)

const grade = ref('normal')
const gradeOptions = ref({})
const gradeSelectedProductId = ref(null)
const gradeSingleResult = ref(null)

const increasedItems = ref([])
const newIncreasedItem = reactive({
  product_id: null,
  quantity: 1
})

const shippingTemplates = ref({})
const shipProductId = ref(null)
const shipTemplate = ref('')
const shipDestination = ref('')
const shipQuantity = ref(1)
const shipWeight = ref(0)
const shipLength = ref(0)
const shipWidth = ref(0)
const shipHeight = ref(0)
const shipLoading = ref(false)
const shipCompareLoading = ref(false)
const shipResult = ref(null)
const shipCompareResult = ref(null)

const gradeDiscountRate = computed(() => {
  return gradeOptions.value[grade.value]?.discount_rate || 0
})

const gradeDiscountDisplay = computed(() => {
  const rate = gradeDiscountRate.value
  const multiplier = formatDiscountMultiplier(rate)
  if (rate === 0) {
    return multiplier + '（无折扣）'
  }
  return multiplier
})

const gradeName = computed(() => {
  return gradeOptions.value[grade.value]?.name || '普通批发商'
})

const currentTemplateZones = computed(() => {
  const tpl = shippingTemplates.value[shipTemplate.value]
  if (!tpl || !tpl.zones) return []
  return Object.entries(tpl.zones).map(([key, zone]) => ({
    key,
    name: zone.name
  }))
})

const totalIncreasedCost = computed(() => {
  let total = 0
  increasedItems.value.forEach(item => {
    total += (item.unit_cost || 0) * (item.quantity || 1)
  })
  return Math.round(total * 100) / 100
})

const totalIncreasedQuantity = computed(() => {
  let total = 0
  increasedItems.value.forEach(item => {
    total += item.quantity || 0
  })
  return total
})

const searchProducts = async (query) => {
  productLoading.value = true
  try {
    const all = await getAllProducts()
    if (!query) {
      productOptions.value = all
      all.forEach(p => { productMap.value[p.id] = p })
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

const loadGradeDiscounts = async () => {
  try {
    gradeOptions.value = await getGradeDiscounts()
  } catch (e) {}
}

const onGradeChange = async () => {
  if (gradeSelectedProductId.value) {
    await calculateGradeCost()
  }
  calculateIncreasedItemsCost()
}

const onGradeProductChange = async () => {
  await calculateGradeCost()
}

const calculateGradeCost = async () => {
  if (!gradeSelectedProductId.value) {
    gradeSingleResult.value = null
    return
  }
  try {
    gradeSingleResult.value = await calculateProductCostByGrade(
      gradeSelectedProductId.value,
      { grade: grade.value }
    )
  } catch (e) {}
}

const addIncreasedItem = () => {
  const product = productMap.value[newIncreasedItem.product_id]
  if (!product) return

  const supplierPrice = product.supplier_price || 0
  const unitCost = Math.round(supplierPrice * (1 - gradeDiscountRate.value) * 100) / 100

  increasedItems.value.push({
    product_id: product.id,
    product_name: product.name,
    product_sku: product.sku,
    supplier_price: supplierPrice,
    quantity: newIncreasedItem.quantity,
    unit_cost: unitCost,
    total_cost: Math.round(unitCost * newIncreasedItem.quantity * 100) / 100
  })

  newIncreasedItem.product_id = null
  newIncreasedItem.quantity = 1
}

const removeIncreasedItem = (index) => {
  increasedItems.value.splice(index, 1)
}

const clearIncreasedItems = () => {
  increasedItems.value = []
}

const calculateIncreasedCost = () => {
  calculateIncreasedItemsCost()
}

const calculateIncreasedItemsCost = () => {
  increasedItems.value.forEach(item => {
    const unitCost = Math.round(item.supplier_price * (1 - gradeDiscountRate.value) * 100) / 100
    item.unit_cost = unitCost
    item.total_cost = Math.round(unitCost * item.quantity * 100) / 100
  })
}

const loadShippingTemplates = async () => {
  try {
    const data = await getShippingTemplates()
    shippingTemplates.value = data || {}
    const keys = Object.keys(shippingTemplates.value)
    if (keys.length && !shipTemplate.value) {
      shipTemplate.value = keys[0]
    }
  } catch (e) {}
}

const onShipProductChange = () => {
  const product = productMap.value[shipProductId.value]
  if (product) {
    shipWeight.value = parseFloat(product.weight) || 0
    shipLength.value = parseFloat(product.length) || 0
    shipWidth.value = parseFloat(product.width) || 0
    shipHeight.value = parseFloat(product.height) || 0
  }
  shipResult.value = null
  shipCompareResult.value = null
}

const onTemplateChange = () => {
  shipDestination.value = ''
  shipResult.value = null
  shipCompareResult.value = null
}

const buildShippingParams = () => {
  const params = {
    template: shipTemplate.value,
    destination: shipDestination.value,
    quantity: shipQuantity.value
  }
  if (shipWeight.value) params.weight = shipWeight.value
  if (shipLength.value) params.length = shipLength.value
  if (shipWidth.value) params.width = shipWidth.value
  if (shipHeight.value) params.height = shipHeight.value
  return params
}

const calculateShipping = async () => {
  if (!shipProductId.value || !shipDestination.value) return
  shipLoading.value = true
  shipCompareResult.value = null
  try {
    shipResult.value = await estimateShipping(shipProductId.value, buildShippingParams())
    ElMessage.success('预估运费计算成功')
  } catch (e) {
    shipResult.value = null
  } finally {
    shipLoading.value = false
  }
}

const compareShippingFee = async () => {
  if (!shipProductId.value || !shipDestination.value) return
  shipCompareLoading.value = true
  shipResult.value = null
  try {
    const params = { destination: shipDestination.value, quantity: shipQuantity.value }
    if (shipWeight.value) params.weight = shipWeight.value
    if (shipLength.value) params.length = shipLength.value
    if (shipWidth.value) params.width = shipWidth.value
    if (shipHeight.value) params.height = shipHeight.value
    shipCompareResult.value = await compareShipping(shipProductId.value, params)
    ElMessage.success('物流商对比完成')
  } catch (e) {
    shipCompareResult.value = null
  } finally {
    shipCompareLoading.value = false
  }
}

const resetShipping = () => {
  shipProductId.value = null
  shipDestination.value = ''
  shipQuantity.value = 1
  shipWeight.value = 0
  shipLength.value = 0
  shipWidth.value = 0
  shipHeight.value = 0
  shipResult.value = null
  shipCompareResult.value = null
}

const weightBasisLabel = (basis) => {
  const map = { actual: '按实际重量', volumetric: '按体积重量', none: '无重量信息' }
  return map[basis] || basis
}

onMounted(async () => {
  await searchProducts('')
  await loadGradeDiscounts()
  await loadShippingTemplates()
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
