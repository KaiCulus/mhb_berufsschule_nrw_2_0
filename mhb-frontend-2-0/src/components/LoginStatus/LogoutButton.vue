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
  <button @click="logout" :disabled="isLoading">
    {{ isLoading ? 'Lädt...' : 'Abmelden' }}
  </button>
</template>