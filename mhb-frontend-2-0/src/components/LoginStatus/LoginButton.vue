<script setup>
import { useAuthStore } from '@/stores/authentification/auth';
import { storeToRefs } from 'pinia';

/**
 * LoginButton
 *
 * Löst den Microsoft OAuth-Login-Flow aus.
 * Während des Redirects ist der Button deaktiviert, um Doppelklicks zu verhindern.
 */

const auth = useAuthStore();
const { isLoading } = storeToRefs(auth);

const login = async () => {
  try {
    await auth.login();
  } catch (error) {
    alert('Anmeldung fehlgeschlagen. Bitte versuche es erneut.');
  }
};
</script>

<template>
  <button @click="login" :disabled="isLoading" class="login-flow-button">
    {{ isLoading ? 'Lädt...' : 'Mit Microsoft 365 anmelden' }}
  </button>
</template>

<style scoped>
.login-flow-button {
  padding: 0.85rem 2.5rem;
  font-size: 1.1rem;
  font-weight: 600;
  color: white;
  background-color: #0e64a6;
  border: none;
  border-radius: 50px;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(14, 100, 166, 0.35);
  transition: background-color 0.2s, box-shadow 0.2s, transform 0.1s;
}

.login-flow-button:hover {
  background-color: #0a4f87;
  box-shadow: 0 6px 20px rgba(14, 100, 166, 0.45);
}

.login-flow-button:active {
  transform: scale(0.97);
}
</style>