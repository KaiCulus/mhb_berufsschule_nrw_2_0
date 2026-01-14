<script setup>
  import { useAuthStore } from '@/stores/authentification/auth'
  import { useRouter } from 'vue-router'

  const auth = useAuthStore()
  const router = useRouter()  

  //TODO: unbedingt ändern
  const loginmethode = () => {
    auth.login()
    router.push('/dashboard')
  }

</script>

<template>
  <div>
    <button v-on:click="loginmethode">
      Hier einloggen
    </button>
  </div>
</template>

<style scoped>

</style>