// src/stores/authentification/auth.js
import { defineStore } from 'pinia'
import axios from '@/scripts/axios.js'

/**
 * Auth Store
 *
 * Verwaltet den Authentifizierungszustand der Anwendung.
 *
 * Auth-Strategie:
 *   Die eigentliche Authentifizierung läuft vollständig server-seitig via PHP-Session.
 *   Der Store hält nur den UI-Zustand (ist der User eingeloggt, wer ist er, welche Rechte hat er).
 *   Beim App-Start wird /api/me aufgerufen um zu prüfen ob eine gültige Session existiert.
 *
 * Persistenz:
 *   Nur nicht-sensible UI-Daten werden in localStorage gespeichert (user, dbId, isLoggedIn, permissions).
 *   Keine Tokens — die Session-Cookie übernimmt die Authentifizierung.
 *
 * Login/Logout:
 *   Beide leiten zum PHP-Backend weiter — das Backend steuert den kompletten OAuth-Flow.
 */

// Backend-URL: leer in Production (same-origin), gesetzt in Entwicklung via .env
const BACKEND_URL = import.meta.env.VITE_MHB_BACKEND_URL || '/mhb/mhb_be'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,         // User-Objekt aus der Session (name, email, groups, ...)
    dbId: null,         // Interne Datenbank-ID des Users
    isLoggedIn: false,  // Ob eine gültige Session existiert
    permissions: {},    // Berechtigungen: { verwaltung: bool, is_processor: bool, ... }
  }),

  actions: {
    /**
     * Leitet zum Microsoft-Login weiter.
     * Das PHP-Backend startet den OAuth-Flow und leitet nach erfolgreichem
     * Login zu /dashboard zurück.
     */
    async login() {
      window.location.href = `${BACKEND_URL}/oauth/login`
    },

    /**
     * Löscht den lokalen Auth-Zustand und leitet zum Microsoft-Logout weiter.
     *
     * Reihenfolge ist wichtig:
     *   1. localStorage leeren (bevor $reset() persist neu schreiben kann)
     *   2. Store zurücksetzen
     *   3. Zum Backend-Logout weiterleiten (beendet auch die Microsoft-Session)
     */
    async logout() {
      localStorage.clear()  // Zuerst leeren — verhindert dass persist den Reset überschreibt
      this.$reset()
      window.location.href = `${BACKEND_URL}/oauth/logout`
    },

    /**
     * Prüft beim Backend ob eine gültige Session existiert.
     *
     * Wird aufgerufen wenn isLoggedIn false ist und eine geschützte Route
     * aufgerufen wird. Befüllt den Store mit den User-Daten wenn eine
     * Session gefunden wird.
     *
     * @returns {boolean} true wenn eingeloggt, false wenn nicht
     */
    async checkSession() {
      try {
        const response = await axios.get('/api/me')

        if (response.data?.id) {
          this.user        = response.data.user
          this.dbId        = response.data.id
          this.isLoggedIn  = true
          this.permissions = response.data.permissions ?? {}
          return true
        }

        // Antwort kam aber kein id → Session ungültig
        this.isLoggedIn = false
        return false

      } catch {
        // 401 oder Netzwerkfehler → nicht eingeloggt
        this.isLoggedIn = false
        return false
      }
    },

    /**
     * Kombinierter Check beim App-Start.
     *
     * Gibt sofort true zurück wenn der User laut localStorage bereits
     * eingeloggt ist (schneller Pfad, kein Backend-Call).
     * Fragt ansonsten das Backend via checkSession().
     *
     * @returns {boolean} true wenn eingeloggt
     */
    async checkAuthOnLoad() {
      if (this.isLoggedIn && this.user) return true
      return await this.checkSession()
    },

    /**
     * Lädt die aktuellen Berechtigungen vom Backend nach.
     *
     * Kann nach einer Gruppenänderung in Azure aufgerufen werden,
     * ohne dass der User sich neu einloggen muss.
     */
    async fetchPermissions() {
      if (!this.isLoggedIn) return

      try {
        const response = await axios.get('/api/sync/get-permissions')
        this.permissions = response.data.permissions ?? {}
      } catch {
        this.permissions = {}
      }
    },
  },

  persist: {
    enabled: true,
    strategies: [
      {
        storage: localStorage,
        // Nur UI-relevante Daten persistieren — keine Tokens
        paths: ['user', 'dbId', 'isLoggedIn', 'permissions'],
      },
    ],
  },
})