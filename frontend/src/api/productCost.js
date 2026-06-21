import request from '@/utils/request'

export function getProductCosts(productId, params) {
  return request.get(`/products/${productId}/costs`, { params })
}

export function getProductCost(id) {
  return request.get(`/product-costs/${id}`)
}

export function createProductCost(data) {
  return request.post('/product-costs', data)
}

export function updateProductCost(id, data) {
  return request.put(`/product-costs/${id}`, data)
}

export function deleteProductCost(id) {
  return request.delete(`/product-costs/${id}`)
}
