<script setup>
import Headermain from '@/components/header/headermain.vue';
import Footer from '@/components/footer/footer.vue';
import { useAuthStore } from '@/stores/authentification/auth';

/**
 * App.vue — Wurzelkomponente
 *
 * Bindet Layout-Rahmen (Header, Footer) ein und rendert
 * die aktive Route via <router-view />.
 * Der AuthStore wird hier früh initialisiert, damit alle
 * Kind-Komponenten sofort auf den Auth-State zugreifen können.
 */
const auth = useAuthStore(); // Frühe Initialisierung für globale Verfügbarkeit
</script>

<template>
  <Headermain />
  <main>
    <router-view />
  </main>
  <Footer />
</template>