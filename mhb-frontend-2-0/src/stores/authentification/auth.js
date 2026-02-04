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

    async checkSession() {
      try {
        // Wir rufen einen neuen Endpunkt auf, den wir gleich im PHP erstellen
        const response = await axios.get(`${BACKEND_URL}/api/me`, {
          withCredentials: true
        });
        
        if (response.data.id) {
          this.user = response.data.user;
          this.dbId = response.data.id;
          this.isLoggedIn = true;
          this.permissions = response.data.permissions;
          return true;
        }
      } catch (error) {
        this.isLoggedIn = false;
        return false;
      }
      return false;
    },

    async checkAuthOnLoad() {
      // 1. Erstmal schauen, ob wir noch Daten im LocalStorage (Persist) haben
      if (this.isLoggedIn && this.user) return true;

      // 2. Wenn nicht, das Backend nach der Session fragen
      return await this.checkSession();
    },

    async logout() {
      this.user = null;
      this.dbId = null;
      this.isLoggedIn = false;
      this.accessToken = null;
      this.idToken = null;
      this.permissions = {};
      this.$reset(); 
      localStorage.clear();

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