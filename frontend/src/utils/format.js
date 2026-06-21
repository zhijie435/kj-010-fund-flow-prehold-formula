export function formatMoney(value, decimals = 2) {
  const num = parseFloat(value || 0)
  return num.toFixed(decimals)
}

export function formatPercent(value, decimals = 2) {
  const num = parseFloat(value || 0)
  return (num * 100).toFixed(decimals) + '%'
}

export function formatDiscountMultiplier(discountRate, decimals = 2) {
  const num = parseFloat(discountRate || 0)
  const multiplier = 1 - num
  return '× ' + multiplier.toFixed(decimals)
}

export function formatDate(value, format = 'YYYY-MM-DD') {
  if (!value) return '-'
  const date = new Date(value)
  if (isNaN(date.getTime())) return '-'
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return format
    .replace('YYYY', year)
    .replace('MM', month)
    .replace('DD', day)
    .replace('HH', hours)
    .replace('mm', minutes)
}

export const COST_TYPES = {
  purchase: { label: '采购成本', color: '#409EFF', type: '' },
  shipping: { label: '物流成本', color: '#67C23A', type: 'success' },
  packaging: { label: '包装成本', color: '#E6A23C', type: 'warning' },
  platform_fee: { label: '平台费用', color: '#F56C6C', type: 'danger' },
  marketing: { label: '营销成本', color: '#909399', type: 'info' },
  tax: { label: '税费', color: '#722ed1', type: '' },
  other: { label: '其他成本', color: '#8c8c8c', type: '' }
}

export const SETTLEMENT_TYPES = {
  order: { label: '按订单结算', color: '#409EFF', type: '' },
  monthly: { label: '月度结算', color: '#67C23A', type: 'success' },
  manual: { label: '手动结算', color: '#E6A23C', type: 'warning' }
}

export const SETTLEMENT_STATUSES = {
  pending: { label: '待确认', color: '#E6A23C', type: 'warning' },
  confirmed: { label: '已确认', color: '#409EFF', type: '' },
  settled: { label: '已结算', color: '#67C23A', type: 'success' },
  cancelled: { label: '已取消', color: '#909399', type: 'info' }
}

export function getCostTypeInfo(type) {
  return COST_TYPES[type] || { label: type, color: '#8c8c8c', type: '' }
}

export function getSettlementTypeInfo(type) {
  return SETTLEMENT_TYPES[type] || { label: type, color: '#8c8c8c', type: '' }
}

export function getSettlementStatusInfo(status) {
  return SETTLEMENT_STATUSES[status] || { label: status, color: '#8c8c8c', type: '' }
}
