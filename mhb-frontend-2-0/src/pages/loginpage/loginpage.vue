<script setup>
import LoginButton from '@/components/LoginStatus/LoginButton.vue';

/**
 * loginpage
 *
 * Einstiegspunkt für nicht eingeloggte User.
 * Delegiert die gesamte Login-Logik (OAuth-Redirect) an LoginButton.
 */
</script>

<template>
  <div class="login-container">
    <LoginButton />
  </div>
</template>

<style scoped>
.login-container {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 4rem 1rem;
}


</style>