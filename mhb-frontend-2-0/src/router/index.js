// src/router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/authentification/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: () => import('@/pages/home/home.vue')
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/loginpage/loginpage.vue')
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('@/pages/dashboardpage/dashboardpage.vue'),
      meta: {requiresAuth: true}
    },
        {
      path: '/mhb',
      name: 'mhb',
      component: () => import('@/pages/mhbpage/mhbpage.vue'),
      meta: {requiresAuth: true}
    },
        {
      path: '/rooms',
      name: 'rooms',
      component: () => import('@/pages/roomavaiabilitypage/roomavaiabilitypage.vue'),
      meta: {requiresAuth: true}
    },
        {
      path: '/tickets',
      name: 'tickets',
      component: () => import('@/pages/ticketpage/ticketpage.vue'),
      meta: {requiresAuth: true}
    },
  ]
})

router.beforeEach((to, from, next) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isLoggedIn) {
    next('/login') // Weiterleitung zur Login-Seite
  } else {
    next() // Route freigeben
  }
})

export default router
