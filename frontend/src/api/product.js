import request from '@/utils/request'

export function getProducts(params) {
  return request.get('/products', { params })
}

export function getAllProducts() {
  return request.get('/products/all')
}

export function getProduct(id) {
  return request.get(`/products/${id}`)
}

export function createProduct(data) {
  return request.post('/products', data)
}

export function updateProduct(id, data) {
  return request.put(`/products/${id}`, data)
}

export function deleteProduct(id) {
  return request.delete(`/products/${id}`)
}

export function calculateProductCost(id, params) {
  return request.get(`/products/${id}/calculate-cost`, { params })
}

export function batchCalculateCost(data) {
  return request.post('/products/batch-calculate-cost', data)
}

export function getCostTypes() {
  return request.get('/products/cost-types')
}

export function getGradeDiscounts() {
  return request.get('/products/grade-discounts')
}

export function calculateProductCostByGrade(id, params) {
  return request.get(`/products/${id}/calculate-cost-by-grade`, { params })
}

export function batchCalculateCostByGrade(data) {
  return request.post('/products/batch-calculate-cost-by-grade', data)
}

export function calculateIncreasedCost(data) {
  return request.post('/products/calculate-increased-cost', data)
}

export function getShippingTemplates() {
  return request.get('/products/shipping-templates')
}

export function estimateShipping(id, params) {
  return request.get(`/products/${id}/estimate-shipping`, { params })
}

export function compareShipping(id, params) {
  return request.get(`/products/${id}/compare-shipping`, { params })
}
