<script setup>

</script>

<template>
  <div id="header-left">
    <router-link to="/">
        <img src="@/assets/images/logo/logo-tbs1.png" alt="Logo" class="logo" />
    </router-link>
  </div>
</template>

<style scoped>
  #header-left {
    display: flex;
    align-items: center;
  }

  .logo {
    height: 50px;
  }
</style>