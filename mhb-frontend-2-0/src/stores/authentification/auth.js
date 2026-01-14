// stores/auth.js
import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    isLoggedIn: false,
    user: null,
  }),
  actions: {
    login(userData) {
      this.isLoggedIn = true
      this.user = userData
      localStorage.setItem('token', 'dein-token') // Beispiel
      // Hier könntest du z. B. ein JWT-Token im localStorage speichern
    },
    logout() {
      this.isLoggedIn = false
      this.user = null
      // Token aus localStorage entfernen, falls vorhanden
      localStorage.removeItem('token')
    },
    // Optional: Initialen Login-Status aus localStorage laden
    async initialize() {
      const token = localStorage.getItem('token')
      this.isLoggedIn = !!token
    },
  },
  //Login Status persistieren
  persist: {
    enable: true,
    strategies: [
      {
        key: 'auth',
        storage: localStorage
      },
    ],
  },    
})

