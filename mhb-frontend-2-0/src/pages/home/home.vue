<script setup>
import { useAuthStore } from '@/stores/authentification/auth';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';

/**
 * home
 *
 * Startseite der Anwendung.
 * Eingeloggte User sehen eine Übersicht/Einführung,
 * nicht eingeloggte User werden zum Login weitergeleitet.
 */

const auth = useAuthStore();
const router = useRouter();
const { isLoggedIn } = storeToRefs(auth);

const goToLogin = () => {
  router.push('/login');
};
</script>

<template>
  <!-- TODO: Einführungstext und Übersicht für eingeloggte User ergänzen -->
  <div v-if="isLoggedIn">
    Hier kommt eine Beschreibung, wie man alles auf dieser Seite macht.
  </div>
  <div v-else>
    <button @click="goToLogin">
      Zum Loginbereich
    </button>
  </div>
</template>