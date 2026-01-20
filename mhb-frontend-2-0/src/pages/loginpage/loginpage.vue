<script setup>
  import { useAuthStore } from '@/stores/authentification/auth'
  import { useRouter } from 'vue-router'
  import LoginButton from '@/components/LoginStatus/LoginButton.vue'

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
    <LoginButton />
  </div>
</template>

<style scoped>

</style>