import { defineStore } from 'pinia';
import { msalInstance, loginRequest } from '@/auth/msalConfig';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isLoggedIn: false,
  }),
  actions: {
    async login() {
      try {
        const response = await msalInstance.loginPopup(loginRequest);
        const graphResponse = await msalInstance.acquireTokenSilent({
          scopes: ['User.Read'],
          account: response.account,
        });
        const userData = await this.fetchUserData(graphResponse.accessToken);
        this.user = userData;
        this.isLoggedIn = true;
      } catch (error) {
        console.error('Login failed:', error);
        throw error;
      }
    },
    async fetchUserData(accessToken) {
      const response = await fetch('https://graph.microsoft.com/v1.0/me', {
        headers: { Authorization: `Bearer ${accessToken}` },
      });
      if (!response.ok) throw new Error('Failed to fetch user data');
      return response.json();
    },
    async logout() {
      await msalInstance.logout();
      this.user = null;
      this.isLoggedIn = false;
    },
  },
  persist: {
    enabled: true,
    strategies: [{ storage: localStorage, paths: ['user'] }],
  },
});
