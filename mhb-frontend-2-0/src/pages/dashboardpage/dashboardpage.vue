<script setup>
import FavoriteDocumentsDashboard from '@/components/userComponents/userDocuments/documentsDashboard/FavoriteDocumentsDashboard.vue';
  
</script>

<template>
  <div class="dashboard-container">
    <h1>Dashboard</h1>
    
    <section class="dashboard-section">
      <h2>⭐ Meine Verwaltungsfavoriten</h2>
      <FavoriteDocumentsDashboard 
        scope="verwaltung"
      />
    </section>
  </div>
</template>

<style scoped>
.dashboard-container {
  padding: 30px;
  max-width: 1200px;
  margin: 0 auto;
}

.dashboard-section {
  background: #fff;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.05);
  margin-top: 20px;
}

h1 { color: #000000; margin-bottom: 5px; }
h2 { font-size: 1.2rem; color: #34495e; border-bottom: 2px solid #eee; padding-bottom: 10px; }
</style>