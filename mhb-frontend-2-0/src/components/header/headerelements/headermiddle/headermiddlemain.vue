<script setup>
  import { ref } from 'vue';

  import menueEntry from './headermiddleSubcomponents/menueEntry.vue';

  const isVisible = ref(false)
  const toggleVisibility = () => {
    isVisible.value = !isVisible.value
  }

</script>

<template>
  <div id="header-middle" v-on:click="toggleVisibility">
    <span>☰</span>
  </div>
  <div v-if="isVisible" id="mainMenu">
    <!--TODO: Hier weiterarbeiten. MenueEntry.vue mit props einfügen. CSS Styling anpassen.-->
    <div class="flexcontainer">
      <div>test</div>
      <div>test</div>
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
</style>