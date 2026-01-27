<script setup>
  import { ref, onMounted, onUnmounted  } from 'vue';

  import MenuEntry from './headermiddleSubcomponents/menuEntry.vue';

  //Visibility Logic
  const isVisible = ref(false)
  const toggleVisibility = () => {
    isVisible.value = !isVisible.value
    //Timeout für CSS-Transition
    setTimeout(() =>{
      const mainMenu = document.getElementById('mainMenu')
      if (mainMenu) {
        mainMenu.classList.toggle('visible', isVisible.value)
      }
    }, 10)
  }
  const closeMenuOnOutsideClick = (event) => {
    if (!event.target.closest('#header-middle') && !event.target.closest('#mainMenu')) {
      isVisible.value = false
    }
  }
  onMounted(() => {
    window.addEventListener('click', closeMenuOnOutsideClick)
  })
  onUnmounted(() => {
    window.removeEventListener('click', closeMenuOnOutsideClick)
  })

  // Menüeinträge hier anpassen
  const menuEntries = [
    {name: 'Dashboard', route: '/dashboard'},
    {name: 'Verwaltungsdokumente', route: '/mhb'},
    {name: 'Raumbuchungsübersicht', route: '/rooms'},
    {name: 'Tickets/Schadensmeldung', route: '/tickets'},
  ]
</script>

<template>
  <div id="header-middle" v-on:click="toggleVisibility">
    <span>☰</span>
  </div>
  <div v-if="isVisible" id="mainMenu">
    <div class="dropdownmenu-main">
      <MenuEntry
        v-for="(entry, index) in menuEntries"
        :key="index"
        :name="entry.name"
        :route="entry.route"
        @close-menu="isVisible = false"
      />
    </div>
  </div>


</template>

<style scoped>
  #header-middle {
    display: flex;
    align-items: center;
  }

  #header-middle span {
    font-size: 2em;
  }

  #header-middle:hover{
    cursor: pointer;
  }
  /* Dropdown-Menü-Styling */
  #mainMenu {
    position: absolute;
    top: 100%;
    left: 0;
    width: 100vw;
    background-color: #333;
    z-index: 1001;
    transition: opacity 0.3s, transform 0.3s;
  }
  #mainMenu:not(.visible) {
    opacity: 0;
    transform: translateY(-10px);
    pointer-events: none;
  }

  .dropdownmenu-main {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 10px 0;
  }
</style>