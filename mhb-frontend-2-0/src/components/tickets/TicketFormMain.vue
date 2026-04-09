<script setup>
import { ref, reactive, computed } from 'vue';
import axios from '@/scripts/axios';
import TicketDescriptionInput from '@/components/tickets/TicketFormComponents/TicketDescriptionInput.vue';
import TicketLocationInput from '@/components/tickets/TicketFormComponents/TicketLocationInput.vue';
import TicketPriorityInfo from '@/components/tickets/TicketFormComponents/TicketPriorityInfo.vue';
import TicketTitleInput from '@/components/tickets/TicketFormComponents/TicketTitleInput.vue';
import TicketTypeSelect from '@/components/tickets/TicketFormComponents/TicketTypeSelect.vue';

/**
 * TicketFormMain — Neues Ticket erstellen
 *
 * Sammelt alle Ticket-Felder über spezialisierte Unterkomponenten.
 * Das Formular sendet erst wenn alle Pflichtfelder befüllt sind (isFormValid).
 * Nach erfolgreichem Submit wird das Formular zurückgesetzt und das
 * 'created'-Event gefeuert, damit die Elternseite das Formular schließen kann.
 *
 * Emits:
 *   created — Ticket wurde erfolgreich angelegt
 */

const emit = defineEmits(['created']);

const isSubmitting = ref(false);
const message = ref({ text: '', type: '' });

const ticketData = reactive({
  title:         '',
  description:   '',
  category:      'it_support',
  sub_category:  '',
  priority:      'medium',
  location_type: 'building',
  building:      '',
  room:          '',
});

/**
 * Formular ist gültig wenn:
 *   - Titel und Beschreibung befüllt sind
 *   - Eine Unter-Kategorie gewählt wurde
 *   - Im Gebäude-Modus: Gebäude UND Raum befüllt
 *   - Im Sonstiger-Ort-Modus: Ortsbeschreibung befüllt (wird im 'room'-Feld gespeichert)
 */
const isFormValid = computed(() => {
  const hasTitle       = ticketData.title.trim().length > 0;
  const hasDescription = ticketData.description.trim().length > 0;
  const hasSubCategory = ticketData.sub_category.trim().length > 0;
  const hasLocation    =
    (ticketData.location_type === 'building' &&
      ticketData.building.trim().length > 0 &&
      ticketData.room.trim().length > 0) ||
    (ticketData.location_type === 'other' &&
      ticketData.room.trim().length > 0);

  return hasTitle && hasDescription && hasSubCategory && hasLocation;
});

const submitTicket = async () => {
  if (!isFormValid.value) return;

  isSubmitting.value = true;
  try {
    const response = await axios.post('/api/tickets', ticketData);
    message.value = { text: `Ticket erfolgreich erstellt! ID: #${response.data.ticket_id}`, type: 'success' };

    // Formular zurücksetzen
    Object.assign(ticketData, {
      title:         '',
      description:   '',
      sub_category:  '',
      building:      '',
      room:          '',
      category:      'it_support',
      priority:      'medium',
      location_type: 'building',
    });

    // Kurze Verzögerung damit die Erfolgsmeldung sichtbar bleibt
    setTimeout(() => emit('created'), 500);
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
      <TicketTitleInput v-model="ticketData.title" :is-readonly="false" />

      <TicketTypeSelect
        v-model:category="ticketData.category"
        v-model:subCategory="ticketData.sub_category"
        :is-readonly="false"
      />

      <TicketDescriptionInput v-model="ticketData.description" :is-readonly="false" />

      <TicketLocationInput
        v-model:type="ticketData.location_type"
        v-model:building="ticketData.building"
        v-model:room="ticketData.room"
        :is-readonly="false"
      />

      <TicketPriorityInfo v-model="ticketData.priority" :is-readonly="false" />

      <button type="submit" :disabled="isSubmitting || !isFormValid" class="submit-btn">
        {{ isSubmitting ? 'Wird gesendet...' : 'Ticket abschicken' }}
      </button>
      <p v-if="!isFormValid" class="validation-hint">Bitte alle Felder ausfüllen.</p>
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
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.alert {
  padding: 10px;
  border-radius: 5px;
  margin-bottom: 15px;
}

.success { background: #d4edda; color: #155724; }
.error   { background: #f8d7da; color: #721c24; }

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

.validation-hint {
  color: #e67e22;
  font-size: 0.8rem;
  margin-top: 5px;
  text-align: center;
}
</style>