<script setup>
  //Vue.js Imports
  import { ref, onMounted, onUnmounted } from 'vue'
  import { useAuthStore } from '@/stores/authentification/auth'
  import { storeToRefs } from 'pinia'
  //Komponentenimports
  import HeaderLeft from './headerelements/headerleft/headerleftmain.vue'
  import HeaderMiddle from './headerelements/headermiddle/headermiddlemain.vue'
  import HeaderRight from './headerelements/headerright/headerrightmain.vue'

  
  const auth = useAuthStore()
  const { isLoggedIn } = storeToRefs(auth)

  const header = ref(null)

  // Methoden
  const handleScroll = () => {
    if (window.scrollY > 0) {
      header.value.classList.add('scrolled')
    } else {
      header.value.classList.remove('scrolled')
    }
  }

  onMounted(() => {
    window.addEventListener('scroll', handleScroll)
  })

  onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll)
  })
</script>

<template>
  <div id="Mainheader" ref="header">
    <div class="flexcontainer">
      <HeaderLeft />
      <div class="noStructureChange" v-if="isLoggedIn">
        <HeaderMiddle />
        <HeaderRight />
      </div>
    </div>
  </div>
</template>


<style scoped>
  #Mainheader {
    width: 100%;
    background-color: #333;
    color: white;
    padding: 5px 0;
    position: sticky;
    top: 0;
    z-index: 1000;
    transition: background-color 0.3s ease;
  }

  #Mainheader.scrolled {
    background-color: rgba(0, 0, 0, 0.5);
  }

  @media (max-width: 768px) {
    #header-left, #header-middle, #header-right {
      margin: 0;
    }
  }
</style>