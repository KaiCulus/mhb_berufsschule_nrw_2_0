// stores/auth.js
import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    isLoggedIn: false, // Standardmäßig nicht eingeloggt
    user: null,        // Benutzerdaten (z. B. Name, E-Mail)
  }),

  actions: {
    /**
     * Simuliert den Login-Prozess.
     * TODO: Ersetze dies durch einen echten API-Call ans Backend!
     * @param {Object} userData - Benutzerdaten (z. B. { name: 'Kai', email: 'kai@example.com' })
     */
    login(userData) {
      this.isLoggedIn = true
      this.user = userData
      // ACHTUNG: Nur für Demo-Zwecke! Im echten Projekt:
      // 1. Token vom Backend abrufen (z. B. nach erfolgreicher Anmeldung)
      // 2. Token NICHT hartcodieren, sondern dynamisch vom Backend erhalten
      localStorage.setItem('token', 'dein-token') // Beispiel-Token (unsicher!)
      // Besser: localStorage.setItem('token', response.data.token) // Nach API-Login
    },

    /**
     * Simuliert den Logout-Prozess.
     * TODO: Backend-API aufrufen, um das Token serverseitig zu invalidieren!
     */
    logout() {
      this.isLoggedIn = false
      this.user = null
      // Token aus localStorage entfernen (Client-seitig)
      localStorage.removeItem('token')
      // WICHTIG: Im echten Projekt auch das Backend über den Logout informieren,
      // z. B. durch einen API-Call, um das Token serverseitig zu sperren.
    },

    /**
     * Initialisiert den Auth-Status beim Laden der App.
     * Problem: Aktuell wird nur geprüft, OB ein Token existiert, nicht OB es gültig ist.
     * Lösung: Token-Validierung mit dem Backend durchführen!
     */
    async initialize() {
      const token = localStorage.getItem('token')

      // 1. Prüfe, ob ein Token existiert
      if (!token) {
        this.isLoggedIn = false
        return
      }

      // 2. TODO: Token-Validierung mit dem Backend (z. B. /api/validate-token)
      // Beispiel für einen API-Call (auskommentiert, da Backend fehlt):
      /*
      try {
        const response = await fetch('/api/validate-token', {
          method: 'POST',
          headers: { 'Authorization': `Bearer ${token}` }
        })
        this.isLoggedIn = response.ok // Nur true, wenn das Backend das Token akzeptiert
      } catch (error) {
        console.error('Token-Validierung fehlgeschlagen:', error)
        this.isLoggedIn = false
        localStorage.removeItem('token') // Ungültiges Token entfernen
      }
      */

      // 3. FALLBACK (nur für Entwicklung ohne Backend):
      //    Dekodiert den JWT-Token (nur möglich, wenn es ein JWT ist!)
      //    ACHTUNG: Dies ist KEINE sichere Validierung, da der Client das Token manipulieren kann!
      try {
        const payload = JSON.parse(atob(token.split('.')[1])) // Dekodiert den Payload-Teil des JWT
        const isExpired = payload.exp * 1000 < Date.now() // Prüft, ob das Token abgelaufen ist
        this.isLoggedIn = !isExpired // Setzt Login-Status basierend auf dem Ablaufdatum
      } catch (e) {
        // Falls das Token kein JWT ist oder ungültig:
        this.isLoggedIn = false
        localStorage.removeItem('token')
      }
    },
  },

  // Persistenz-Konfiguration: Speichert den Auth-Status im localStorage
  persist: {
    enable: true,
    strategies: [
      {
        key: 'auth',          // Schlüssel im localStorage
        storage: localStorage, // Speicherort
        // WICHTIG: Nur 'user' persistieren, nicht 'isLoggedIn'!
        // Warum? 'isLoggedIn' sollte dynamisch basierend auf dem Token-Status gesetzt werden.
        paths: ['user'],
      },
    ],
  },
})
