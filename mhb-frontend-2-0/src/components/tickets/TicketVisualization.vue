<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from '@/scripts/axios';
import { useAuthStore } from '@/stores/authentification/auth';
import TicketDetailsMain from '@/components/tickets/TicketVisualizationComponents/TicketDetailsMain.vue';
import TicketSubscribeRoom from '@/components/tickets/TicketVisualizationComponents/TicketVisualizationSubComponents/TicketSubscribeRoom.vue';
import TicketRestoreButton from '@/components/tickets/TicketVisualizationComponents/Ticketrestorebutton.vue';

/**
 * TicketVisualization — Ticket-Übersichtsliste
 *
 * Zeigt alle, eigene oder archivierte Tickets als filterbares Grid.
 * Ein Klick auf "Details" öffnet TicketDetailsMain als Modal.
 *
 * Modi:
 *   'all'      — Alle aktiven Tickets (Ticketseite, für Admins/Bearbeiter)
 *   'personal' — Nur eigene Tickets (Dashboard); zeigt zusätzlich die
 *                Raum-Abo-Verwaltung für Tickets (TicketSubscribeRoom)
 *   'archive'  — Archivierte Tickets mit Wiederherstellungs-Option
 *                (nur für Processors sichtbar/zugänglich)
 *
 * Props:
 *   mode — 'all' | 'personal' | 'archive', default: 'all'
 */

const props = defineProps({
  mode: {
    type: String,
    default: 'all',
    validator: (value) => ['all', 'personal', 'archive'].includes(value),
  },
});

const authStore        = useAuthStore();
const tickets          = ref([]);
const loading          = ref(true);
const searchQuery      = ref('');
const selectedTicketId = ref(null);
const selectedCategory = ref('all');
const restoreMessage   = ref({ text: '', type: '' });

const categories = [
  { value: 'all',        label: 'Alle Kategorien' },
  { value: 'network',    label: 'Netzwerk' },
  { value: 'it_support', label: 'IT-Support' },
  { value: 'facility',   label: 'Gebäude/Hausmeister' },
];

/** Farben und Labels für alle möglichen Ticket-Status-Werte. */
const statusConfig = {
  open:                          { label: 'Offen',                color: '#e74c3c' },
  in_progress:                   { label: 'In Bearbeitung',       color: '#3498db' },
  processing_paused:             { label: 'Pausiert',             color: '#95a5a6' },
  waiting_for_external_response: { label: 'Wartet auf Extern',    color: '#f1c40f' },
  resolved_by_staff:             { label: 'Erledigt (Fachkraft)', color: '#2ecc71' },
  closed:                        { label: 'Geschlossen',          color: '#27ae60' },
};

const fetchTickets = async () => {
  loading.value = true;
  restoreMessage.value = { text: '', type: '' };
  try {
    let url;
    if (props.mode === 'archive') {
      url = '/api/tickets/archive';
    } else if (props.mode === 'personal') {
      url = `/api/tickets/user/${authStore.dbId}`;
    } else {
      url = '/api/tickets';
    }
    const response = await axios.get(url);
    tickets.value = response.data;
  } catch (error) {
    console.error('Fehler beim Laden der Tickets:', error);
  } finally {
    loading.value = false;
  }
};

/** Kombinierter Filter: erst Kategorie, dann Freitextsuche über Titel, Raum und Ersteller. */
const filteredTickets = computed(() => {
  let result = tickets.value;

  if (selectedCategory.value !== 'all') {
    result = result.filter(t => t.category === selectedCategory.value);
  }

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    result = result.filter(t =>
      t.title.toLowerCase().includes(q) ||
      t.room?.toLowerCase().includes(q) ||
      t.creator_name?.toLowerCase().includes(q)
    );
  }

  return result;
});

/** Callback von TicketRestoreButton — Liste neu laden und Feedback anzeigen. */
const onRestored = async (ticketId) => {
  restoreMessage.value = {
    text: `Ticket #${ticketId} wurde erfolgreich wiederhergestellt.`,
    type: 'success',
  };
  await fetchTickets();
};

/** Callback von TicketRestoreButton bei Fehler. */
const onRestoreError = (message) => {
  restoreMessage.value = { text: message, type: 'error' };
};

onMounted(fetchTickets);

// Neu laden wenn der Tab-Modus wechselt (z.B. all → archive)
watch(() => props.mode, fetchTickets);
</script>

<template>
  <div class="visualization-wrapper">
    <!-- Raum-Abo-Verwaltung nur im persönlichen Modus (Dashboard) -->
    <TicketSubscribeRoom v-if="mode === 'personal'" />

    <!-- Archiv-Banner -->
    <div v-if="mode === 'archive'" class="archive-banner">
      🗄️ Archivierte Tickets — Nur Ticketbearbeiter können Tickets wiederherstellen.
    </div>

    <!-- Feedback-Nachricht für Wiederherstellung -->
    <div v-if="restoreMessage.text" :class="['restore-alert', restoreMessage.type]">
      {{ restoreMessage.text }}
    </div>

    <div class="controls">
      <input
        v-model="searchQuery"
        type="text"
        :placeholder="mode === 'archive' ? 'Archiv durchsuchen (Titel, Raum, Name)...' : 'Tickets durchsuchen (Titel, Raum, Name)...'"
        class="search-bar"
      />
      <select v-model="selectedCategory" class="category-select">
        <option v-for="cat in categories" :key="cat.value" :value="cat.value">
          {{ cat.label }}
        </option>
      </select>
      <button @click="fetchTickets" class="refresh-btn" title="Neu laden">🔄</button>
    </div>

    <div v-if="loading" class="loading">
      {{ mode === 'archive' ? 'Lade Archiv...' : 'Lade Tickets...' }}
    </div>

    <div v-else class="ticket-grid">
      <div
        v-for="ticket in filteredTickets"
        :key="ticket.id"
        class="ticket-card"
        :class="{ 'archive-card': mode === 'archive' }"
      >
        <div
          class="card-header"
          :style="{ borderTopColor: mode === 'archive' ? '#95a5a6' : (statusConfig[ticket.status]?.color ?? '#ccc') }"
        >
          <span class="id">#{{ ticket.id }}</span>
          <span
            v-if="mode !== 'archive'"
            class="status-badge"
            :style="{ backgroundColor: statusConfig[ticket.status]?.color ?? '#ccc' }"
          >
            {{ statusConfig[ticket.status]?.label ?? ticket.status }}
          </span>
          <span v-else class="status-badge archive-badge">Archiviert</span>
        </div>

        <div class="card-body">
          <h3>{{ ticket.title }}</h3>
          <p class="location">📍 {{ ticket.building }} – {{ ticket.room }}</p>
          <p class="meta">Von: {{ ticket.creator_name }} | {{ new Date(ticket.created_at).toLocaleDateString('de-DE') }}</p>

          <!-- Archiv-spezifische Metadaten -->
          <template v-if="mode === 'archive'">
            <p class="meta archive-meta">
              Archiviert am: {{ new Date(ticket.updated_at).toLocaleDateString('de-DE') }}
            </p>
            <p class="meta">Letzter Status: <em>{{ statusConfig[ticket.status]?.label ?? ticket.status }}</em></p>
          </template>
        </div>

        <div class="card-footer">
          <!-- Archiv-Modus: Wiederherstellen über ausgelagerte Komponente -->
          <TicketRestoreButton
            v-if="mode === 'archive'"
            :ticket-id="ticket.id"
            @restored="onRestored"
            @error="onRestoreError"
          />

          <!-- Normaler Modus: Details -->
          <button v-else @click="selectedTicketId = ticket.id" class="detail-btn-action">
            Details ansehen
          </button>
        </div>
      </div>
    </div>

    <div v-if="!loading && filteredTickets.length === 0" class="no-data">
      {{ mode === 'archive' ? 'Keine archivierten Tickets gefunden.' : 'Keine Tickets gefunden.' }}
    </div>

    <TicketDetailsMain
      v-if="selectedTicketId"
      :ticket-id="selectedTicketId"
      @close="selectedTicketId = null"
      @refresh="fetchTickets"
    />
  </div>
</template>

<style scoped>
.visualization-wrapper {
  width: 100%;
}

/* Archiv-Banner */
.archive-banner {
  background: #f0f3f7;
  border: 1px solid #c8d6e5;
  border-radius: 8px;
  padding: 10px 16px;
  margin-bottom: 16px;
  font-size: 0.9rem;
  color: #555;
}

/* Feedback-Nachrichten */
.restore-alert {
  padding: 10px 14px;
  border-radius: 6px;
  margin-bottom: 14px;
  font-size: 0.9rem;
}
.restore-alert.success { background: #d4edda; color: #155724; }
.restore-alert.error   { background: #f8d7da; color: #721c24; }

/* Mobile First: Controls untereinander */
.controls {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 20px;
}

.search-bar,
.category-select {
  width: 100%;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid #ddd;
  font-size: 16px;
}

.refresh-btn {
  padding: 10px;
  border-radius: 8px;
  border: 1px solid #ddd;
  background: #f8f9fa;
  cursor: pointer;
  align-self: flex-end;
}

/* Ticket Grid: einspaltig auf Mobile */
.ticket-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}

@media (min-width: 768px) {
  .controls {
    flex-direction: row;
    align-items: center;
  }

  .search-bar { flex-grow: 1; }

  .category-select {
    width: auto;
    min-width: 200px;
  }

  .ticket-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1200px) {
  .ticket-grid {
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  }
}

.ticket-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
  display: flex;
  flex-direction: column;
  transition: transform 0.2s ease;
}

.ticket-card:hover { transform: translateY(-4px); }

.archive-card { opacity: 0.88; }

.card-header {
  padding: 10px 15px;
  border-top: 5px solid #ccc;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.status-badge {
  color: white;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: bold;
}

.archive-badge { background: #7f8c8d; }

.card-body        { padding: 15px; flex-grow: 1; }
.card-body h3     { margin: 0 0 10px 0; font-size: 1.1rem; }
.location         { font-size: 0.9rem; color: #555; margin-bottom: 5px; }
.meta             { font-size: 0.75rem; color: #888; }
.archive-meta     { color: #7f8c8d; margin-top: 4px; }
.card-footer      { padding: 10px 15px; border-top: 1px solid #eee; }

.no-data {
  text-align: center;
  color: #888;
  margin-top: 40px;
}

.loading {
  text-align: center;
  color: #888;
  margin-top: 20px;
}
</style>