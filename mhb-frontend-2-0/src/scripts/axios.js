// src/scripts/axios.js
import axios from 'axios'
import { useAuthStore } from '@/stores/authentification/auth'

/**
 * Konfigurierte Axios-Instanz für alle Backend-Requests.
 *
 * baseURL:
 *   In Production leer (same-origin, kein Hostname nötig) → Fallback auf relativen Pfad.
 *   In Entwicklung auf den Vite-Proxy gesetzt via VITE_MHB_BACKEND_URL=http://localhost:5173,
 *   der die Requests dann an das PHP-Backend auf Port 443 weiterleitet.
 *
 * withCredentials:
 *   Notwendig damit der Browser das PHP-Session-Cookie mitsender — ohne diese Option
 *   würde der Backend immer 401 zurückgeben weil keine Session gefunden wird.
 *
 * Bearer-Token Interceptor:
 *   Wird nur gesetzt wenn idToken im Auth-Store vorhanden ist.
 *   In der Standardkonfiguration ist idToken immer null — Authentifizierung
 *   läuft ausschließlich über das Session-Cookie.
 *   Der Interceptor bleibt für zukünftige Token-basierte Flows (z.B. Mobile-App).
 */

const axiosInstance = axios.create({
  baseURL: import.meta.env.VITE_MHB_BACKEND_URL || '/mhb/mhb_be',
  withCredentials: true, // Session-Cookie bei jedem Request mitsenden
})

// Request-Interceptor: Bearer-Token hinzufügen wenn vorhanden
axiosInstance.interceptors.request.use(
  (config) => {
    const authStore = useAuthStore()
    const token = authStore.idToken
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// Response-Interceptor: 401 NUR behandeln wenn der Request nicht /api/me war.
// Sonst entsteht eine Race-Condition direkt nach dem OAuth-Callback:
// /api/me wird aufgerufen bevor der Auth-Store das Ergebnis verarbeiten kann,
// und der Interceptor wirft den User zurück zum Login während er gerade
// versucht hat einzuloggen.
axiosInstance.interceptors.response.use(
  (response) => response,
  (error) => {
    const isAuthCheck = error.config?.url?.includes('/api/me')

    if (error.response?.status === 401 && !isAuthCheck) {
      const authStore = useAuthStore()
      localStorage.clear()
      authStore.$reset()
      // BASE_URL respektieren — sonst landen wir auf der Domain-Root statt /mhb/
      window.location.href = import.meta.env.BASE_URL || '/'
    }
    return Promise.reject(error)
  }
)

export default axiosInstance