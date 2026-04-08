// src/router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/authentification/auth'

/**
 * Vue Router Konfiguration
 *
 * Routen-Typen:
 *   - Öffentlich:           Kein meta.requiresAuth — für alle zugänglich (/, /login)
 *   - Geschützt:            meta: { requiresAuth: true } — Redirect zu / wenn nicht eingeloggt
 *
 * Auth-Flow:
 *   Der OAuth-Callback wird vollständig vom PHP-Backend verarbeitet.
 *   Nach erfolgreichem Login setzt das Backend die Session und leitet
 *   direkt zu /dashboard weiter — Vue verarbeitet den Callback nicht selbst.
 *   Der globale Guard prüft dann per /api/me ob die Session gültig ist.
 */
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [

    // ── Öffentliche Routen ──────────────────────────────────────────────────
    {
      path: '/',
      name: 'home',
      component: () => import('@/pages/home/home.vue'),
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/loginpage/loginpage.vue'),
    },

    // ── Geschützte Routen ───────────────────────────────────────────────────
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('@/pages/dashboardpage/dashboardpage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/mhb',
      name: 'mhb',
      component: () => import('@/pages/mhbpage/mhbpage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/rooms',
      name: 'rooms',
      component: () => import('@/pages/roomavaiabilitypage/roomavaiabilitypage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/tickets',
      name: 'tickets',
      component: () => import('@/pages/ticketpage/ticketpage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/material',
      name: 'material',
      component: () => import('@/pages/materialsearchpage/materialsearchpage.vue'),
      meta: { requiresAuth: true },
    },

    // ── Fallback ────────────────────────────────────────────────────────────
    {
      // Alle nicht definierten Pfade → Startseite
      // Verhindert leere weiße Seite bei falscher URL
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
})

// =============================================================================
// Globaler Navigations-Guard
// =============================================================================

/**
 * Prüft vor jeder Navigation ob der User eingeloggt ist.
 *
 * Ablauf:
 *   1. Wenn isLoggedIn bereits true (aus localStorage persist) → kein Backend-Call nötig
 *   2. Wenn isLoggedIn false und Route ist geschützt → Session beim Backend prüfen
 *   3. Nach der Prüfung: Weiterleitung zu / wenn nicht authentifiziert
 *
 * Öffentliche Routen (ohne meta.requiresAuth) werden nie blockiert —
 * auch kein unnötiger Backend-Call wenn der User nicht eingeloggt ist.
 */
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // Öffentliche Route → immer durchlassen, kein Auth-Check nötig
  if (!to.meta.requiresAuth) {
    return next()
  }

  // Session noch nicht geprüft → Backend fragen
  if (!authStore.isLoggedIn) {
    await authStore.checkAuthOnLoad()
  }

  // Geschützte Route, nicht eingeloggt → zur Startseite
  if (!authStore.isLoggedIn) {
    return next('/')
  }

  next()
})

export default router