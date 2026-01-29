<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from '@/scripts/axios';
import { useAuthStore } from '@/stores/authentification/auth';

const props = defineProps({
  mode: { 
    type: String, 
    default: 'all', // 'all' für die Ticketseite, 'personal' für das Dashboard
    validator: (value) => ['all', 'personal'].includes(value)
  }
});

const authStore = useAuthStore();
const tickets = ref([]);
const loading = ref(true);
const searchQuery = ref('');

const fetchTickets = async () => {
  loading.value = true;
  try {
    const url = props.mode === 'personal' 
      ? `/api/tickets/user/${authStore.dbId}` 
      : '/api/tickets';
    const response = await axios.get(url);
    tickets.value = response.data;
  } catch (error) {
    console.error("Fehler beim Laden der Tickets:", error);
  } finally {
    loading.value = false;
  }
};

// Status-Mapping für Farben und Labels
const statusConfig = {
  open: { label: 'Offen', color: '#e74c3c' },
  in_progress: { label: 'In Bearbeitung', color: '#3498db' },
  processing_paused: { label: 'Pausiert', color: '#95a5a6' },
  waiting_for_external_response: { label: 'Wartet auf Extern', color: '#f1c40f' },
  resolved_by_staff: { label: 'Erledigt (Fachkraft)', color: '#2ecc71' },
  closed: { label: 'Geschlossen', color: '#27ae60' }
};

const filteredTickets = computed(() => {
  if (!searchQuery.value) return tickets.value;
  const q = searchQuery.value.toLowerCase();
  return tickets.value.filter(t => 
    t.title.toLowerCase().includes(q) || 
    t.room?.toLowerCase().includes(q) ||
    t.creator_name?.toLowerCase().includes(q)
  );
});

onMounted(fetchTickets);
</script>

<template>
  <div class="visualization-wrapper">
    <div class="controls">
      <input 
        v-model="searchQuery" 
        type="text" 
        placeholder="Tickets durchsuchen (Titel, Raum, Name)..." 
        class="search-bar"
      />
      <button @click="fetchTickets" class="refresh-btn">🔄</button>
    </div>

    <div v-if="loading" class="loading">Lade Tickets...</div>

    <div v-else class="ticket-grid">
      <div v-for="ticket in filteredTickets" :key="ticket.id" class="ticket-card">
        <div class="card-header" :style="{ borderTopColor: statusConfig[ticket.status].color }">
          <span class="id">#{{ ticket.id }}</span>
          <span class="status-badge" :style="{ backgroundColor: statusConfig[ticket.status].color }">
            {{ statusConfig[ticket.status].label }}
          </span>
        </div>

        <div class="card-body">
          <h3>{{ ticket.title }}</h3>
          <p class="location">📍 {{ ticket.building }} - {{ ticket.room }}</p>
          <p class="meta">Von: {{ ticket.creator_name }} | {{ new Date(ticket.created_at).toLocaleDateString() }}</p>
        </div>

        <div class="card-footer">
          <router-link :to="`/tickets/detail/${ticket.id}`" class="detail-btn">
            Details ansehen
          </router-link>
        </div>
      </div>
    </div>

    <div v-if="!loading && filteredTickets.length === 0" class="no-data">
      Keine Tickets gefunden.
    </div>
  </div>
</template>

<style scoped>
.visualization-wrapper { width: 100%; }

.controls { display: flex; gap: 10px; margin-bottom: 20px; }
.search-bar { flex-grow: 1; padding: 10px; border-radius: 8px; border: 1px solid #ddd; }

.ticket-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
}

.ticket-card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  display: flex;
  flex-direction: column;
  transition: transform 0.2s;
}
.ticket-card:hover { transform: translateY(-3px); }

.card-header {
  padding: 10px 15px;
  border-top: 5px solid #ccc;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.status-badge { color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }

.card-body { padding: 15px; flex-grow: 1; }
.card-body h3 { margin: 0 0 10px 0; font-size: 1.1rem; }
.location { font-size: 0.9rem; color: #555; margin-bottom: 5px; }
.meta { font-size: 0.75rem; color: #888; }

.card-footer { padding: 10px 15px; border-top: 1px solid #eee; }
.detail-btn { 
  display: block; text-align: center; color: #0e64a6; 
  text-decoration: none; font-weight: bold; font-size: 0.9rem;
}
</style>