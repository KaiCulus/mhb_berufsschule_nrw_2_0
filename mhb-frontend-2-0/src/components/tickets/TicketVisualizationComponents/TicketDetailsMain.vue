<script setup>
import { ref, onMounted } from 'vue';
import axios from '@/scripts/axios';
import TicketNotes from '@/components/tickets/TicketVisualizationComponents/TicketVisualizationSubComponents/TicketNotes.vue';
import TicketSubscription from '@/components/tickets/TicketVisualizationComponents/TicketVisualizationSubComponents/TicketSubscription.vue';
import TicketDescriptionInput from '@/components/tickets/TicketFormComponents/TicketDescriptionInput.vue';
import TicketLocationInput from '@/components/tickets/TicketFormComponents/TicketLocationInput.vue';
import TicketPriorityInfo from '@/components/tickets/TicketFormComponents/TicketPriorityInfo.vue';
import TicketTitleInput from '@/components/tickets/TicketFormComponents/TicketTitleInput.vue';
import TicketTypeSelect from '@/components/tickets/TicketFormComponents/TicketTypeSelect.vue';
import TicketStatusSelect from '@/components/tickets/TicketVisualizationComponents/TicketVisualizationSubComponents/TicketStatusSelect.vue';

const props = defineProps({
  ticketId: { type: Number, required: true }
});

const emit = defineEmits(['close', 'refresh']);
const ticket = ref(null);
const loading = ref(true);

const fetchDetails = async () => {
  try {
    const response = await axios.get(`/api/tickets/detail/${props.ticketId}`);
    ticket.value = response.data;
  } catch (error) {
    console.error("Details konnten nicht geladen werden", error);
  } finally {
    loading.value = false;
  }
};

const updateField = async (field, value) => {
  try {
    await axios.post('/api/tickets/update-field', {
      ticketId: props.ticketId,
      field,
      value
    });
    await fetchDetails(); // Refresh für die Historie
  } catch (error) {
    alert("Änderung fehlgeschlagen");
  }
};

const resolveTicket = async () => {
  if (!confirm("Status wirklich ändern / Ticket löschen?")) return;
  await axios.post('/api/tickets/resolve', { ticketId: props.ticketId });
  emit('refresh');
  emit('close');
};

const cleanupTickets = async () => {
  if (!confirm("Alle Tickets, die älter als 7 Tage und als erledigt markiert sind, werden endgültig gelöscht. Fortfahren?")) return;
  try {
    const response = await axios.post('/api/tickets/cleanup');
    alert(`${response.data.deleted_count} Tickets wurden gelöscht.`);
    emit('refresh');
    emit('close');
  } catch (error) {
    alert("Fehler bei der Bereinigung.");
  }
};

onMounted(fetchDetails);
</script>

<template>
  <div class="modal-overlay" @click.self="emit('close')">
    <div class="modal-content" v-if="ticket">
      <header class="modal-header">
        <div class="header-left">
          <span class="ticket-id">#{{ ticket.id }}</span>
          <h2>{{ ticket.title }}</h2>
        </div>
        <div class="header-actions">
          <TicketSubscription :ticket-id="ticket.id" />
          <button @click="emit('close')" class="close-btn">✕</button>
        </div>
      </header>

      <div v-if="loading" class="loading-state">Lade Ticket-Details...</div>

      <div class="modal-body">
        <div class="details-grid">
        <section class="info-section">
            <TicketStatusSelect 
              v-model="ticket.status"
              :is-readonly="!ticket.can_edit_status"
              @update:modelValue="updateField('status', $event)"
            />
            <div class="meta-info">
            <p><strong>Erstellt von:</strong> {{ ticket.creator_name }}</p>
            <p><strong>Datum:</strong> {{ new Date(ticket.created_at).toLocaleString('de-DE') }}</p>
            </div>

            <TicketTitleInput 
            v-model="ticket.title" 
            :is-readonly="!ticket.can_edit_status"
            @update:modelValue="updateField('title', $event)" 
            />

            <div class="editable-area">
            <TicketTypeSelect 
                v-model:category="ticket.category"
                v-model:subCategory="ticket.sub_category"
                :is-readonly="!ticket.can_edit_status"
                @update:category="updateField('category', $event)"
                @update:subCategory="updateField('sub_category', $event)"
            />
            </div>

            <TicketDescriptionInput 
            v-model="ticket.description" 
            :is-readonly="!ticket.can_edit_status"
            @update:modelValue="updateField('description', $event)"
            />

            <TicketLocationInput 
            v-model:type="ticket.location_type"
            v-model:building="ticket.building"
            v-model:room="ticket.room"
            :is-readonly="!ticket.can_edit_status"
            @update:type="updateField('location_type', $event)"
            @update:building="updateField('building', $event)"
            @update:room="updateField('room', $event)"
            />

            <TicketPriorityInfo 
            v-model="ticket.priority" 
            :is-readonly="!ticket.can_edit_status"
            @update:modelValue="updateField('priority', $event)"
            />

            <div v-if="ticket.last_editor_name" class="history-label">
            ✎ Letzte Änderung durch: {{ ticket.last_editor_name }}
            </div>
        </section>
        <section class="interaction-section">
            <TicketNotes 
              :ticket-id="ticket.id" 
              :comments="ticket.comments" 
              @refresh="fetchDetails" 
            />
          </section>
        </div>
    </div>

      <footer class="modal-footer">
        <button v-if="ticket.can_edit_status" @click="cleanupTickets" class="cleanup-btn">
          🧹 Alttickets bereinigen
        </button>

        <div class="main-footer-actions">
          <button 
            v-if="ticket.can_edit_status" 
            @click="resolveTicket" 
            class="resolve-btn"
          >
            ✅ Als erledigt markieren
          </button>
          <button 
            v-else 
            @click="resolveTicket" 
            class="delete-btn"
          >
            🗑️ Ticket zurückziehen
          </button>
        </div>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.modal-overlay {
  position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
  background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(3px);
  display: flex; align-items: center; justify-content: center; z-index: 2000;
}

.modal-content {
  background: #fff; width: 95%; max-width: 1000px; max-height: 90vh;
  border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.3);
  display: flex; flex-direction: column;
}

.modal-header {
  padding: 20px 25px; border-bottom: 1px solid #eee;
  display: flex; justify-content: space-between; align-items: center;
}

.ticket-id {
  background: #eee; padding: 4px 8px; border-radius: 6px;
  font-weight: bold; font-family: monospace; margin-right: 15px;
}

.modal-body { padding: 25px; overflow-y: auto; flex-grow: 1; }

.details-grid {
  display: grid; grid-template-columns: 1.2fr 1fr; gap: 30px;
}

.info-section { display: flex; flex-direction: column; gap: 20px; }

.history-label {
  font-size: 0.75rem; color: #888; margin-top: 5px; font-style: italic;
}

.description-container label {
  display: block; font-weight: bold; margin-bottom: 8px; color: #555;
}

.description-text {
  background: #f9f9f9; padding: 15px; border-radius: 8px;
  border: 1px solid #eee; line-height: 1.5; white-space: pre-wrap;
}

.modal-footer {
  padding: 15px 25px; border-top: 1px solid #eee; background: #fcfcfc;
  display: flex; justify-content: space-between; align-items: center;
}

.resolve-btn { background: #27ae60; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; }
.delete-btn { background: #e74c3c; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
.cleanup-btn { background: transparent; border: 1px solid #ccc; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; }

@media (max-width: 800px) {
  .details-grid { grid-template-columns: 1fr; }
}
</style>