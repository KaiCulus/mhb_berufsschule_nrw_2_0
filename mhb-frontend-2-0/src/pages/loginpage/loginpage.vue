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