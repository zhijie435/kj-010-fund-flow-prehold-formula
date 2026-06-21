import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    redirect: '/dashboard'
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('@/views/Dashboard.vue'),
    meta: { title: '数据看板' }
  },
  {
    path: '/products',
    name: 'Products',
    component: () => import('@/views/Products/List.vue'),
    meta: { title: '商品管理' }
  },
  {
    path: '/products/:id',
    name: 'ProductDetail',
    component: () => import('@/views/Products/Detail.vue'),
    meta: { title: '商品详情' }
  },
  {
    path: '/product-costs',
    name: 'ProductCosts',
    component: () => import('@/views/ProductCosts/List.vue'),
    meta: { title: '成本管理' }
  },
  {
    path: '/cost-calculator',
    name: 'CostCalculator',
    component: () => import('@/views/CostCalculator/Index.vue'),
    meta: { title: '成本计算' }
  },
  {
    path: '/settlements',
    name: 'Settlements',
    component: () => import('@/views/Settlements/List.vue'),
    meta: { title: '结算分账' }
  },
  {
    path: '/settlements/create',
    name: 'SettlementCreate',
    component: () => import('@/views/Settlements/Create.vue'),
    meta: { title: '创建结算单' }
  },
  {
    path: '/settlements/:id',
    name: 'SettlementDetail',
    component: () => import('@/views/Settlements/Detail.vue'),
    meta: { title: '结算单详情' }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

export default router
