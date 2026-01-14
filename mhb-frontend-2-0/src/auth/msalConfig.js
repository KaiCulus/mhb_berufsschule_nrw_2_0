import { PublicClientApplication } from '@azure/msal-browser';

const msalConfig = {
  auth: {
    clientId: import.meta.env.VUE_APP_MSAL_CLIENT_ID,
    authority: `https://login.microsoftonline.com/${import.meta.env.VUE_APP_MSAL_TENANT_ID}`,
    redirectUri: import.meta.env.VUE_APP_MSAL_REDIRECT_URI,
  },
  cache: {
    cacheLocation: 'localStorage',
    storeAuthStateInCookie: false,
  },
};

const msalInstance = new PublicClientApplication(msalConfig);

// Scopes für Graph API
const loginRequest = {
  scopes: ['openid', 'profile', 'email', 'User.Read'],
};

export { msalInstance, loginRequest };
