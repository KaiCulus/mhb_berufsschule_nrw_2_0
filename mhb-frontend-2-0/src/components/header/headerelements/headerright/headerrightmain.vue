<script setup>
  import { ref } from 'vue'
  import { useAuthStore } from '@/stores/authentification/auth'
  import { useRouter } from 'vue-router'

  const auth = useAuthStore()
  const router = useRouter()  

  const emoji = ref('😊')

  //TODO: unbedingt ändern
  const logoutmethode = () => {
    auth.logout()
    router.push('/')
  }
  
</script>

<template>
  <div id="header-right" v-on:click="logoutmethode">
    <span>{{ emoji }}</span>
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