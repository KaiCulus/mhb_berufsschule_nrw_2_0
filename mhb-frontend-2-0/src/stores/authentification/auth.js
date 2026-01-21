import { defineStore } from 'pinia';
import axios from '@/scripts/axios.js';

// Nutzen der URL aus der .env (Vite-Syntax)
const BACKEND_URL = import.meta.env.VITE_MHB_BACKEND_URL;

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    dbId: null,
    isLoggedIn: false,
    accessToken: null, // MS Graph Access
    idToken: null,     // Backend ID Token
    permissions: {},   // NEU: Hält die Rollen wie { verwaltung: true, paedagogik: false }
  }),

  actions: {
    async login() {
      // Nutzt jetzt die dynamische URL für die Migration
      window.location.href = `${BACKEND_URL}/oauth/login`;
    },

    /**
     * Holt die Berechtigungen vom Backend ab
     */
    async fetchPermissions() {
      if (!this.isLoggedIn) return;
      
      try {
        const response = await axios.get(`${BACKEND_URL}/api/sync/get-permissions`, {
          withCredentials: true // WICHTIG für PHP Session-ID
        });
        this.permissions = response.data.permissions;
      } catch (error) {
        console.error('Berechtigungen konnten nicht geladen werden:', error);
        this.permissions = {};
      }
    },

    async checkAuthOnLoad() {
      const params = new URLSearchParams(window.location.search);
      const accessToken = params.get('access_token');
      const idToken = params.get('id_token');
      const dbId = params.get('db_id');
      const userRaw = params.get('user');

      if (accessToken && idToken && userRaw) {
        try {
          this.accessToken = accessToken;
          this.idToken = idToken;
          this.dbId = dbId;
          this.user = JSON.parse(decodeURIComponent(userRaw));
          this.isLoggedIn = true;

          // Nach erfolgreichem URL-Login sofort Berechtigungen laden
          await this.fetchPermissions();

          window.history.replaceState({}, document.title, window.location.pathname);
          return true;
        } catch (e) {
          console.error('Fehler beim Parsen der Login-Daten:', e);
          return false;
        }
      }

      // Falls bereits eingeloggt (via Persist), Permissions aktualisieren
      if (this.idToken && this.user) {
         this.isLoggedIn = true;
         this.fetchPermissions(); // Läuft im Hintergrund
         return true;
      }

      return false;
    },

    async logout() {
      this.user = null;
      this.dbId = null;
      this.isLoggedIn = false;
      this.accessToken = null;
      this.idToken = null;
      this.permissions = {};

      window.location.href = `${BACKEND_URL}/oauth/logout`;
    },
  },

  persist: {
    enabled: true,
    strategies: [
      {
        storage: localStorage,
        // accessToken mit aufzunehmen ist sinnvoll, um Refresh-Logik zu minimieren
        paths: ['user', 'dbId', 'isLoggedIn', 'idToken', 'accessToken', 'permissions'], 
      },
    ],
  },
});