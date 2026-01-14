<script setup>
    import { useAuthStore } from '@/stores/auth';
    const auth = useAuthStore();
    const login = () => auth.login();
</script>

<template>
  <button @click="login">Mit Microsoft 365 anmelden</button>
</template>


