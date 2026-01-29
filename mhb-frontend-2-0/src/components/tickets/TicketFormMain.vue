<script setup>
import { ref, reactive } from 'vue';
import axios from '@/scripts/axios';
import TicketTypeSelect from './TicketFormComponents/TicketTypeSelect.vue';
import TicketLocationInput from './TicketFormComponents/TicketLocationInput.vue';
import TicketPriorityInfo from './TicketFormComponents/TicketPriorityInfo.vue';

const isSubmitting = ref(false);
const message = ref({ text: '', type: '' });

const ticketData = reactive({
  title: '',
  description: '',
  category: 'it_support',
  sub_category: '',
  priority: 'medium',
  location_type: 'building',
  building: '',
  room: ''
});

const submitTicket = async () => {
  isSubmitting.value = true;
  try {
    const response = await axios.post('/api/tickets', ticketData);
    message.value = { text: 'Ticket erfolgreich erstellt! ID: #' + response.data.ticket_id, type: 'success' };
    // Formular zurücksetzen
    Object.assign(ticketData, { title: '', description: '', building: '', room: '' });
  } catch (error) {
    message.value = { text: 'Fehler beim Senden.', type: 'error' };
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div class="ticket-form-main">
    <h2>Neues Ticket erstellen</h2>
    
    <div v-if="message.text" :class="['alert', message.type]">{{ message.text }}</div>

    <form @submit.prevent="submitTicket">
      <div class="form-section">
        <label>Kurztitel</label>
        <input v-model="ticketData.title" required placeholder="Was ist passiert?" />
      </div>

      <TicketTypeSelect 
        v-model:category="ticketData.category" 
        v-model:subCategory="ticketData.sub_category" 
      />

      <div class="form-section">
        <label>Problembeschreibung</label>
        <textarea v-model="ticketData.description" required rows="4"></textarea>
      </div>

      <TicketLocationInput 
        v-model:type="ticketData.location_type"
        v-model:building="ticketData.building"
        v-model:room="ticketData.room"
      />

      <TicketPriorityInfo v-model="ticketData.priority" />

      <button type="submit" :disabled="isSubmitting" class="submit-btn">
        {{ isSubmitting ? 'Wird gesendet...' : 'Ticket abschicken' }}
      </button>
    </form>
  </div>
</template>

<style scoped>
.ticket-form-main {
  max-width: 600px;
  margin: 20px auto;
  padding: 20px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.form-section { margin-bottom: 15px; display: flex; flex-direction: column; }
.alert { padding: 10px; border-radius: 5px; margin-bottom: 15px; }
.success { background: #d4edda; color: #155724; }
.error { background: #f8d7da; color: #721c24; }
.submit-btn {
  width: 100%;
  padding: 12px;
  background: #0e64a6;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
}
.submit-btn:disabled { opacity: 0.6; }
</style>