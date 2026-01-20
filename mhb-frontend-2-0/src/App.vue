<script setup>
  import Headermain from '@/components/header/headermain.vue';
  import Footer from '@/components/footer/footer.vue';
  import { useAuthStore } from '@/stores/authentification/auth';
  import { onMounted } from 'vue';

  //Auth Store direkt initialisieren
  const auth = useAuthStore();
  /*onMounted(() => {
    auth.initializeFromCallback();
  });*/
  
</script>

<template>
  <Headermain/>
  <main>
    <router-view />
  </main>
  <Footer />

</template>

<style scoped>

  
  
</style>

