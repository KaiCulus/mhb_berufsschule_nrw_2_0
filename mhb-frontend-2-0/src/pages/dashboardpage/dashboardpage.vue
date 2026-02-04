<script setup>
import { ref } from 'vue';
import FavoriteDocumentsDashboard from '@/components/documents/documentsDashboard/FavoriteDocumentsDashboard.vue';
import TicketVisualization from '@/components/tickets/TicketVisualization.vue';

// Status für die Sichtbarkeit der Sektionen
const showFavorites = ref(true);
const showTickets = ref(true);
</script>

<template>
  <div class="dashboard-container">
    <h1>Dashboard</h1>
    
    <div class="dashboard-grid">
      <section class="dashboard-section">
        <h2 @click="showFavorites = !showFavorites" class="clickable-header">
          <span>⭐ Meine Verwaltungsfavoriten</span>
          <span class="toggle-icon">{{ showFavorites ? '−' : '+' }}</span>
        </h2>
        
        <div v-show="showFavorites" class="section-content">
          <FavoriteDocumentsDashboard scope="verwaltung" />
        </div>
      </section>

      <section class="dashboard-section">
        <h2 @click="showTickets = !showTickets" class="clickable-header">
          <span>🎫 Meine Tickets</span>
          <span class="toggle-icon">{{ showTickets ? '−' : '+' }}</span>
        </h2>
        
        <div v-show="showTickets" class="section-content">
          <TicketVisualization mode="personal" />
        </div>
      </section>

      </div>
  </div>
</template>

<style scoped>
.dashboard-container {
  padding: 20px;
  width: 100%;
  max-width: 1400px; /* Etwas breiter für das Grid-Layout */
  margin: 0 auto;
  
}

h1 { 
  color: #000; 
  margin-bottom: 20px; 
  text-align: center;
}

/* 1. Mobile First: Das Grid ist standardmäßig einspaltig */
.dashboard-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  width: 100%;
}

/* 2. Desktop Ansicht: Ab 1024px nebeneinander */
@media (min-width: 1024px) {
  .dashboard-grid {
    /* Erzeugt automatisch so viele Spalten wie Platz ist, mindestens 500px breit */
    grid-template-columns: 1fr 1fr; /* Erzwingt genau 2 Spalten */
    align-items: start; /* Verhindert, dass kleine Sektionen unschön gestreckt werden */
  }
}

.dashboard-section {
  background: #fff;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.05);
  border: 1px solid #eee;
  min-width: 0; 
  width: 100%;
  overflow: hidden;
}

/* Header Styling für den Toggle-Effekt */
.clickable-header {
  font-size: 1.2rem;
  color: #34495e;
  border-bottom: 2px solid #eee;
  padding-bottom: 10px;
  margin-bottom: 15px;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: color 0.2s;
  user-select: none; /* Verhindert Markierung beim schnellen Klicken */
}

.clickable-header:hover {
  color: #0e64a6;
}

.toggle-icon {
  font-family: monospace;
  font-size: 1.5rem;
  color: #95a5a6;
}

.section-content {
  /* Optional: Kleiner Fade-In Effekt */
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(5px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>