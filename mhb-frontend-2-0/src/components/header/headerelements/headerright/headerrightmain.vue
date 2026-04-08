<script setup>
import LogoutButton from '@/components/LoginStatus/LogoutButton.vue';

/**
 * headerrightmain — Header-Rechtsbereich
 *
 * Zeigt den Logout-Button an. Wird nur gerendert wenn der User
 * eingeloggt ist (gesteuert durch die v-if-Bedingung in headermain).
 */
</script>

<template>
  <div id="header-right">
    <LogoutButton />
  </div>
</template>

<style scoped>
#header-right {
  display: flex;
  align-items: center;
}
</style>