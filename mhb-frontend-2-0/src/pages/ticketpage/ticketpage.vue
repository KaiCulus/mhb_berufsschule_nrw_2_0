<script setup>
import { ref, computed } from 'vue';
import { useAuthStore } from '@/stores/authentification/auth';
import TicketFormMain from '@/components/tickets/TicketFormMain.vue';
import TicketVisualization from '@/components/tickets/TicketVisualization.vue';
import AnleitungMain from '@/components/anleitung/anleitungMain.vue';
import Ticketanleitung from '@/components/anleitung/anleitungsbereiche/ticketanleitung.vue';

/**
 * ticketpage — Ticket-System
 *
 * Zentrale Seite für das interne Meldesystem.
 * Neue Tickets werden über ein ein- und ausklappbares Formular erstellt.
 *
 * Tabs:
 *   'all'     — Alle aktiven Tickets (für jeden sichtbar)
 *   'archive' — Archivierte Tickets mit Wiederherstellungs-Option
 *               (nur für Processors sichtbar)
 *
 * Der Archiv-Tab wird nur gerendert wenn der User Processor-Berechtigung hat —
 * der Backend-Endpunkt prüft dies zusätzlich serverseitig.
 */

const authStore    = useAuthStore();
const showForm     = ref(false);
const activeTab    = ref('all');

const isProcessor = computed(() =>
  authStore.permissions?.is_processor === true
);
</script>

<template>
  <div class="ticket-page-container">
    <h1>Ticket-System</h1>

    <AnleitungMain
        label="Erklärung: Ticketsystem"
    >
        <Ticketanleitung />
    </AnleitungMain>
    <div class="header-section">
      <!-- Neues Ticket nur im aktiven-Modus anzeigen -->
      <button
        v-if="activeTab === 'all'"
        @click="showForm = !showForm"
        :class="['toggle-btn', { 'is-active': showForm }]"
      >
        {{ showForm ? '✖ Schließen' : '➕ Neues Problem melden' }}
      </button>
      <div v-else />

      <!-- Tab-Umschalter: Archiv nur für Processors -->
      <div v-if="isProcessor" class="tab-switcher">
        <button
          :class="['tab-btn', { 'is-active': activeTab === 'all' }]"
          @click="activeTab = 'all'; showForm = false"
        >
          Aktive Tickets
        </button>
        <button
          :class="['tab-btn', { 'is-active': activeTab === 'archive' }]"
          @click="activeTab = 'archive'; showForm = false"
        >
          🗄️ Archiv
        </button>
      </div>
    </div>

    <transition name="fade-slide">
      <div v-if="showForm && activeTab === 'all'" class="form-wrapper">
        <TicketFormMain @created="showForm = false" />
      </div>
    </transition>

    
    <hr class="separator" />

    <div class="list-section">
      <h2>{{ activeTab === 'archive' ? 'Archivierte Meldungen' : 'Aktuelle Meldungen' }}</h2>
      <TicketVisualization :mode="activeTab" />
    </div>
  </div>
</template>

<style scoped>
.ticket-page-container {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  gap: 16px;
}

/* Neues-Ticket-Button */
.toggle-btn {
  padding: 12px 24px;
  border-radius: 8px;
  border: none;
  background-color: #0e64a6;
  color: white;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s ease;
  white-space: nowrap;
}

.toggle-btn.is-active { background-color: #e74c3c; }

.toggle-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Tab-Umschalter */
.tab-switcher {
  display: flex;
  gap: 8px;
  background: #f0f3f7;
  padding: 4px;
  border-radius: 10px;
}

.tab-btn {
  padding: 8px 20px;
  border: none;
  border-radius: 7px;
  background: transparent;
  color: #555;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 500;
  transition: background 0.2s, color 0.2s;
  white-space: nowrap;
}

.tab-btn:hover { background: #dce6f0; }

.tab-btn.is-active {
  background: white;
  color: #0e64a6;
  font-weight: bold;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
}

.form-wrapper {
  background: #f9f9f9;
  padding: 20px;
  border-radius: 12px;
  border: 1px solid #eee;
  margin-bottom: 30px;
}

.separator {
  border: 0;
  height: 1px;
  background: #eee;
  margin: 40px 0;
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.4s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}
</style>