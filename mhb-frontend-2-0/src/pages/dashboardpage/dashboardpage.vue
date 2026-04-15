<script setup>
import { ref } from 'vue';
import FavoriteDocumentsDashboard from '@/components/documents/documentsDashboard/FavoriteDocumentsDashboard.vue';
import TicketVisualization from '@/components/tickets/TicketVisualization.vue';
import Dashboardanleitung from '@/components/anleitung/anleitungsbereiche/dashboardanleitung.vue';
import AnleitungMain from '@/components/anleitung/anleitungMain.vue';
import Changelog from '@/components/anleitung/changelog.vue';
/**
 * dashboardpage
 *
 * Persönliche Übersichtsseite des eingeloggten Users.
 * Zeigt Verwaltungsfavoriten und eigene Tickets in aufklappbaren Sektionen.
 */

const showVerwaltungsFavorites = ref(true);
const showWissensFavorites = ref(true);
const showTickets = ref(true);
</script>

<template>
  <div class="dashboard-container">
    <h1>Dashboard</h1>
    <AnleitungMain
      label="Erklärung: Dashboard"
    >
    <Dashboardanleitung />
    </AnleitungMain>
    <div class="dashboard-grid">
      <section class="dashboard-section">
        <h2 @click="showVerwaltungsFavorites = !showVerwaltungsFavorites" class="clickable-header">
          <span>⭐ Meine Verwaltungsfavoriten</span>
          <span class="toggle-icon">{{ showVerwaltungsFavorites ? '−' : '+' }}</span>
        </h2>
        <div v-show="showVerwaltungsFavorites" class="section-content">
          <FavoriteDocumentsDashboard scope="verwaltung" />
        </div>

        <h2 @click="showWissensFavorites = !showWissensFavorites" class="clickable-header">
          <span>⭐ Meine Wissensfavoriten</span>
          <span class="toggle-icon">{{ showWissensFavorites ? '−' : '+' }}</span>
        </h2>
        <div v-show="showWissensFavorites" class="section-content">
          <FavoriteDocumentsDashboard scope="common" />
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
  max-width: 1400px;
  margin: 0 auto;
}

h1 {
  color: #000;
  margin-bottom: 20px;
  text-align: center;
}

/* Mobile First: einspaltig */
.dashboard-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  width: 100%;
}

/* Ab 1024px: zwei gleichbreite Spalten nebeneinander */
@media (min-width: 1024px) {
  .dashboard-grid {
    grid-template-columns: 1fr 1fr;
    align-items: start;
  }
}

.dashboard-section {
  background: #fff;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  border: 1px solid #eee;
  min-width: 0;
  width: 100%;
  overflow: hidden;
}

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
  user-select: none;
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
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(5px); }
  to   { opacity: 1; transform: translateY(0); }
}
</style>