import { createRouter, createWebHistory } from 'vue-router'

// Routes — lazy-loaded views for smaller initial bundle
const routes = [
  {
    path: '/',
    name: 'home',
    component: () => import('../views/HomeView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/catalog',
    name: 'catalog',
    component: () => import('../views/CatalogView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/doc-item/:id',
    name: 'doc-item',
    component: () => import('../views/DocItemView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/apply/:itemId',
    name: 'apply',
    component: () => import('../views/ApplyView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/my-docs',
    name: 'my-docs',
    component: () => import('../views/MyDocsView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/docs/:id',
    name: 'doc-detail',
    component: () => import('../views/DocDetailView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/profile',
    name: 'profile',
    component: () => import('../views/ProfileView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/LoginView.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('../views/RegisterView.vue'),
    meta: { guestOnly: true },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Global auth guard
router.beforeEach((to) => {
  const token = localStorage.getItem('mdoc_token')
  if (to.meta.requiresAuth && !token) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.meta.guestOnly && token) {
    return { name: 'home' }
  }
  return true
})

export default router
