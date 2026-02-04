<script setup>
import RoomAvaiabilityMain from '@/components/roomavaiability/RoomAvaiabilityMain.vue';
</script>

<template>
  <div class="page-container">
    <header class="page-header">
      <h1>🏫 Raumbuchungsübersicht</h1>
      <p>Hier sehen Sie die aktuelle Belegung der buchbaren Räume.</p>
    </header>
    
    <main>
      <RoomAvaiabilityMain />
    </main>
  </div>
</template>

<style scoped>
.page-container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
.page-header { margin-bottom: 40px; }
.page-header h1 { color: #0e64a6; font-size: 2rem; }
</style>