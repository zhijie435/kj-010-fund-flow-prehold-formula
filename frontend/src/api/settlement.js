import request from '@/utils/request'

export function getSettlements(params) {
  return request.get('/settlements', { params })
}

export function getSettlement(id) {
  return request.get(`/settlements/${id}`)
}

export function createSettlement(data) {
  return request.post('/settlements', data)
}

export function updateSettlement(id, data) {
  return request.put(`/settlements/${id}`, data)
}

export function deleteSettlement(id) {
  return request.delete(`/settlements/${id}`)
}

export function calculateSettlement(data) {
  return request.post('/settlements/calculate', data)
}

export function confirmSettlement(id) {
  return request.post(`/settlements/${id}/confirm`)
}

export function settleSettlement(id) {
  return request.post(`/settlements/${id}/settle`)
}

export function cancelSettlement(id) {
  return request.post(`/settlements/${id}/cancel`)
}

export function getSettlementTypes() {
  return request.get('/settlements/types')
}
