<script setup>
  import { useAuthStore } from '@/stores/authentification/auth';
  import { storeToRefs } from 'pinia';

  const auth = useAuthStore();
  const { isLoggedIn, isLoading } = storeToRefs(auth);

  const logout = async () => {
    try {
      await auth.logout();
    } catch (error) {
      alert('Anmeldung fehlgeschlagen. Bitte versuche es erneut.');
    }
  };
</script>

<template>
  <button @click="logout" :disabled="isLoading">
    {{ isLoading ? 'Lädt...' : 'Abmelden' }}
  </button>
</template>


