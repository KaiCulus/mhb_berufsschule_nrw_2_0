<script setup>
import { useAuthStore } from '@/stores/authentification/auth';
import { storeToRefs } from 'pinia';

/**
 * LogoutButton
 *
 * Meldet den aktuellen User ab und leitet zurück zur Login-Seite.
 * Während des Vorgangs ist der Button deaktiviert.
 *
 * Hinweis: isLoggedIn wird nicht aktiv genutzt — der Button wird nur
 * angezeigt, wenn der User eingeloggt ist (gesteuert durch headermain).
 */

const auth = useAuthStore();
const { isLoading } = storeToRefs(auth);

const logout = async () => {
  try {
    await auth.logout();
  } catch (error) {
    alert('Abmeldung fehlgeschlagen. Bitte versuche es erneut.');
  }
};
</script>

<template>
  <button @click="logout" :disabled="isLoading" class="login-flow-button">
    {{ isLoading ? 'Lädt...' : 'Abmelden' }}
  </button>
</template>

<style scoped>
.login-flow-button {
  padding: 0.55rem 1.75rem;
  font-size: 1rem;
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