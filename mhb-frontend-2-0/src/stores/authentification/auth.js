import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    dbId: null,
    isLoggedIn: false,
    accessToken: null, // Für Microsoft Graph
    idToken: null,     // Für DEIN PHP-Backend 
  }),

  actions: {
    async login() {
      window.location.href = 'https://localhost:443/oauth/login';
    },

    async checkAuthOnLoad() {
      const params = new URLSearchParams(window.location.search);
      const accessToken = params.get('access_token');
      const idToken = params.get('id_token'); // NEU
      const dbId = params.get('db_id');       // NEU
      const userRaw = params.get('user');

      if (accessToken && idToken && userRaw) {
        try {
          this.accessToken = accessToken;
          this.idToken = idToken;
          this.dbId = dbId;
          this.user = JSON.parse(decodeURIComponent(userRaw));
          this.isLoggedIn = true;

          // URL bereinigen
          window.history.replaceState({}, document.title, window.location.pathname);
          return true;
        } catch (e) {
          console.error('Fehler beim Parsen der Login-Daten:', e);
          return false;
        }
      }

      // Prüfen, ob wir bereits durch "persist" Daten haben
      if (this.idToken && this.user) {
         this.isLoggedIn = true;
         return true;
      }

      return false;
    },

    /**
     * Hilfsmethode für API-Calls an DEIN Backend
     */
    getAuthHeader() {
      if (!this.idToken) throw new Error('Nicht authentifiziert');
      return { 'Authorization': `Bearer ${this.idToken}` };
    },

    async fetchUserData() {
      if (!this.accessToken) return null;
      const response = await fetch('https://graph.microsoft.com/v1.0/me', {
        headers: { Authorization: `Bearer ${this.accessToken}` },
      });
      if (response.status === 401) this.logout();
      return response.json();
    },

    async logout() {
      // Lokalen State sofort leeren
      this.user = null;
      this.dbId = null;
      this.isLoggedIn = false;
      this.accessToken = null;
      this.idToken = null;

      // Das Pinia-Persist-Plugin löscht nun automatisch den LocalStorage.
      // Dann Umleitung zum Backend-Logout (welches zu MS weiterleitet)
      window.location.href = "https://localhost:443/oauth/logout";
    },
  },

  persist: {
    enabled: true,
    strategies: [
      {
        storage: localStorage,
        // TODO: Überlegen, ob man das Access Token reinnimmt. Vorteil: derzeit muss sich der User immer wenn er mit Graph kommuniziert, muss er sich neu anmelden, sobald er die Seite neu lädt.
        paths: ['user', 'dbId', 'isLoggedIn', 'idToken'], 
      },
    ],
  },
});