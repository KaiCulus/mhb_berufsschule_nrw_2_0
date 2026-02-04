<script setup>
  import { useAuthStore } from '@/stores/authentification/auth';
  import { storeToRefs } from 'pinia';

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
  <button @click="login" :disabled="isLoading">
    {{ isLoading ? 'Lädt...' : 'Mit Microsoft 365 anmelden' }}
  </button>
</template>


