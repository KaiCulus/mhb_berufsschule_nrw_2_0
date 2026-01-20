<script setup>
  import { useAuthStore } from '@/stores/authentification/auth'
  import { useRouter } from 'vue-router'

   import LogoutButton from '@/components/LoginStatus/LogoutButton.vue'

  const auth = useAuthStore()
  const router = useRouter()  
  
</script>

<template>
  <div>
    <LogoutButton />
  </div>
</template>

<style scoped>
  #header-right {
    display: flex;
    align-items: center;
  }

  #header-right span {
    font-size: 2em;
  }

  #header-right:hover{
    cursor: pointer;
  }
</style>