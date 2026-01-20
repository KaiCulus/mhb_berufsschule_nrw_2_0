// src/stores/auth.js
import { defineStore } from 'pinia';
import router from '@/router'; // Stelle sicher, dass du den Router importierst

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isLoggedIn: false,
    accessToken: null,
  }),

  actions: {
    /**
     * Startet den Login-Prozess.
     * Leitet einfach nur an das PHP-Backend weiter.
     */
    async login() {
      // Wir leiten den Browser komplett um, damit PHP die Session und Cookies setzen kann
      window.location.href = 'https://localhost:443/oauth/login';
    },

    /**
     * Wird aufgerufen, wenn die App lädt (z.B. im Dashboard).
     * Prüft, ob das Backend uns Daten per URL-Parameter zurückgegeben hat.
     */
    async checkAuthOnLoad() {
      const params = new URLSearchParams(window.location.search);
      const token = params.get('access_token');
      const userRaw = params.get('user');

      // Fall A: Wir kommen gerade frisch vom Login zurück
      if (token && userRaw) {
        try {
          this.accessToken = token;
          this.user = JSON.parse(decodeURIComponent(userRaw)); // URL-Encoding beachten
          this.isLoggedIn = true;

          // URL bereinigen (Token nicht in der Browser-Leiste stehen lassen)
          window.history.replaceState({}, document.title, window.location.pathname);
          
          return true;
        } catch (e) {
          console.error('Fehler beim Parsen der Login-Daten:', e);
          return false;
        }
      }

      // Fall B: Wir haben bereits Daten im LocalStorage (durch persist)
      if (this.accessToken && this.user) {
         this.isLoggedIn = true;
         return true;
      }

      return false;
    },

    /**
     * Ruft Nutzerdaten von Microsoft Graph ab.
     */
    async fetchUserData() {
      if (!this.accessToken) return null;
      
      const response = await fetch('https://graph.microsoft.com/v1.0/me', {
        headers: { Authorization: `Bearer ${this.accessToken}` },
      });
      
      if (!response.ok) {
        // Wenn Token ungültig (z.B. abgelaufen), ausloggen
        if (response.status === 401) this.logout();
        throw new Error(`Graph API error: ${response.status}`);
      }
      return response.json();
    },

    /**
     * Meldet den Nutzer ab.
     * Optional: Rufe auch einen Backend-Logout-Endpoint auf, um die PHP-Session zu killen.
     */
    async logout() {
      // 1. Lokalen State leeren
      this.user = null;
      this.isLoggedIn = false;
      this.accessToken = null;

      // 2. Optional: Backend benachrichtigen (Best Practice)
      // await fetch('https://localhost:443/oauth/logout');

      // 3. Zur Startseite
      router.push('/');
    },

    /**
     * Gibt das aktuelle Token zurück.
     * Da wir kein Silent Renew im Frontend mehr haben (MSAL ist weg),
     * müssen wir bei Ablauf den User neu zum Login schicken.
     */
    async getAccessToken() {
      if (!this.accessToken) {
         // Kein Token da? Login erzwingen
         this.logout(); 
         throw new Error('No access token available');
      }
      
      // Hier könnte man noch prüfen, ob das Token abgelaufen ist (via jwt-decode)
      // Wenn abgelaufen -> this.login() aufrufen.
      
      return this.accessToken;
    },
  },

  persist: {
    enabled: true,
    strategies: [
      {
        storage: localStorage,
        // Wir speichern jetzt auch das Token, da wir es nicht mehr "silent" im Hintergrund holen können
        paths: ['user', 'accessToken', 'isLoggedIn'], 
      },
    ],
  },
});