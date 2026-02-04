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
    {
      path: '/oauth/callback',
      beforeEnter: async (to, from, next) => {
        const auth = useAuthStore();
        await auth.initializeFromCallback();
        next('/dashboard'); // Weiterleitung nach dem Login
      },
    },
  ]
})

// GLOBALER GUARD
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();

  // Nur prüfen, wenn wir noch nicht wissen, dass wir eingeloggt sind
  if (!authStore.isLoggedIn) {
    await authStore.checkAuthOnLoad();
  }

  if (to.meta.requiresAuth && !authStore.isLoggedIn) {
    next('/'); 
  } else {
    next();
  }
});

export default router
