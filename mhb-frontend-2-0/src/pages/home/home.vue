<script setup>
  import { useAuthStore } from '@/stores/authentification/auth'
  import { useRouter } from 'vue-router'
  import { storeToRefs } from 'pinia'

  const auth = useAuthStore()
  const router = useRouter()
  const { isLoggedIn } = storeToRefs(auth)

  const goToLogin = () => {
    router.push('/login')
  } 

</script>

<template>
  <div v-if="isLoggedIn">
    Hier kommt eine Beschreibung, wie man alles auf dieser Seite macht.
  </div>
  <div v-else>
    <button v-on:click="goToLogin">
      Zum Loginbereich
    </button>
  </div>
</template>

<style scoped>

  
  
</style>