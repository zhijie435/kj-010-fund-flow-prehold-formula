<template>
  <el-container class="layout-container">
    <el-aside width="240px" class="layout-aside">
      <div class="logo">
        <h2>Shearerline</h2>
        <p>成本与结算系统</p>
      </div>
      <el-menu
        :default-active="activeMenu"
        router
        class="layout-menu"
        background-color="transparent"
        text-color="#c0c4cc"
        active-text-color="#409EFF"
      >
        <el-menu-item index="/dashboard">
          <el-icon><DataLine /></el-icon>
          <span>数据看板</span>
        </el-menu-item>
        <el-menu-item index="/products">
          <el-icon><Goods /></el-icon>
          <span>商品管理</span>
        </el-menu-item>
        <el-menu-item index="/product-costs">
          <el-icon><Money /></el-icon>
          <span>成本管理</span>
        </el-menu-item>
        <el-menu-item index="/cost-calculator">
          <el-icon><Calculator /></el-icon>
          <span>成本计算</span>
        </el-menu-item>
        <el-menu-item index="/settlements">
          <el-icon><Document /></el-icon>
          <span>结算分账</span>
        </el-menu-item>
      </el-menu>
    </el-aside>
    <el-container>
      <el-header class="layout-header">
        <div class="header-left">
          <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/dashboard' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item v-if="breadcrumbTitle">{{ breadcrumbTitle }}</el-breadcrumb-item>
          </el-breadcrumb>
        </div>
        <div class="header-right">
          <el-icon class="header-icon"><Bell /></el-icon>
          <el-avatar :size="32" class="header-avatar">A</el-avatar>
        </div>
      </el-header>
      <el-main class="layout-main">
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const activeMenu = computed(() => route.path)
const breadcrumbTitle = computed(() => route.meta?.title || '')
</script>

<style lang="scss" scoped>
.layout-container {
  height: 100vh;
}

.layout-aside {
  background: linear-gradient(180deg, #1f2d3d 0%, #192435 100%);
  transition: width 0.3s;

  .logo {
    padding: 24px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);

    h2 {
      margin: 0;
      color: #fff;
      font-size: 20px;
      font-weight: 600;
      letter-spacing: 1px;
    }

    p {
      margin: 4px 0 0;
      color: #8492a6;
      font-size: 12px;
    }
  }

  .layout-menu {
    border-right: none;
    padding-top: 12px;
  }
}

.layout-header {
  background: #fff;
  border-bottom: 1px solid #ebeef5;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  height: 60px;

  .header-left {
    flex: 1;
  }

  .header-right {
    display: flex;
    align-items: center;
    gap: 16px;

    .header-icon {
      font-size: 18px;
      color: #606266;
      cursor: pointer;
    }

    .header-avatar {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
  }
}

.layout-main {
  background: #f5f7fa;
  padding: 24px;
  overflow-y: auto;
}
</style>
