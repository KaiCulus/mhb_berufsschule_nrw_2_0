<script setup>

  //TODO:Test entfernen
  import TestDbView from '@/components/testcomponents/TestDbView.vue';
  import SyncFolderButton from '@/components/adminComponents/SyncFolderButton.vue';

</script>

<template>
  <div>
    <p>Dies wird das Dashboard.</p>
    <p>Entferne folgendes Später:</p>
    <TestDbView />
    <SyncFolderButton 
      syncType="verwaltung"
      label="Verwaltungsdokumente synchronisieren"
    />
  </div>
</template>

<style scoped>

</style>