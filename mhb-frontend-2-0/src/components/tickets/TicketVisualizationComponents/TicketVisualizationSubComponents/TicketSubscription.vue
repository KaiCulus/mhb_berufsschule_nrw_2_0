<script setup>
import { ref } from 'vue';
import axios from '@/scripts/axios';

/**
 * TicketSubscription — Ticket folgen / nicht mehr folgen
 *
 * Toggelt das Abonnement des eingeloggten Users für ein Ticket.
 * Der initiale Status wird beim Toggle vom Server zurückgegeben und
 * lokal gespeichert — kein separater Status-Check beim Mounten nötig,
 * da der Server den aktuellen Zustand pro Toggle-Request zurückliefert.
 *
 * Props:
 *   ticketId — ID des Tickets
 */

const props = defineProps({
  ticketId: { type: Number, required: true },
});

const isSubscribed = ref(false);

const toggleSub = async () => {
  try {
    const response = await axios.post('/api/tickets/subscribe', {
      ticketId: props.ticketId,
    });
    isSubscribed.value = response.data.subscription === 'subscribed';
  } catch (error) {
    console.error('Subscription fehlgeschlagen:', error);
  }
};
</script>

<template>
  <button
    @click="toggleSub"
    :class="['sub-btn', { 'is-active': isSubscribed }]"
    :title="isSubscribed ? 'Ticket nicht mehr folgen' : 'Ticket folgen (Benachrichtigung bei Änderungen)'"
  >
    <span v-if="isSubscribed">🔔 Folge ich</span>
    <span v-else>🔕 Folgen</span>
  </button>
</template>

<style scoped>
.sub-btn {
  padding: 6px 12px;
  border-radius: 20px;
  border: 1px solid #ddd;
  background: white;
  cursor: pointer;
  font-size: 0.85rem;
  transition: all 0.2s;
}

.sub-btn.is-active {
  background: #f1c40f;
  border-color: #f39c12;
  color: #000;
}
</style>