<script setup>
import { ref, onMounted } from 'vue';
import axios from '@/scripts/axios';
import { useAuthStore } from '@/stores/authentification/auth';

/**
 * RoomSubscriptionToggle — Kompakter Raum-Abo-Button
 *
 * Inline-Button zum schnellen Abonnieren eines einzelnen Raums,
 * z.B. direkt in der Ticket-Detailansicht unter dem Ort.
 *
 * Beim Mounten wird der aktuelle Abo-Status geprüft, indem alle
 * Raum-Abos des Users geladen und der aktuelle Raum darin gesucht wird.
 *
 * Props:
 *   roomName — Name des Raums (z.B. 'R15'); muss im TICKET_CONFIG bekannt sein
 */

const props = defineProps({
  roomName: { type: String, required: true },
});

const authStore = useAuthStore();
const isSubscribed = ref(false);
const loading = ref(false);

const checkStatus = async () => {
  if (!props.roomName) return;
  try {
    const res = await axios.get(`/api/tickets/subscribe-room/${authStore.dbId}`);
    isSubscribed.value = res.data.includes(props.roomName.toUpperCase().trim());
  } catch (error) {
    console.error('Raum-Abo-Status konnte nicht geladen werden:', error);
  }
};

const toggle = async () => {
  loading.value = true;
  try {
    const res = await axios.post('/api/tickets/subscribe-room', { room: props.roomName });
    isSubscribed.value = res.data.subscription === 'subscribed';
  } catch (error) {
    alert('Fehler beim Aktualisieren des Raum-Abos.');
  } finally {
    loading.value = false;
  }
};

onMounted(checkStatus);
</script>

<template>
  <button
    class="room-sub-btn"
    :class="{ 'is-active': isSubscribed }"
    @click.stop="toggle"
    :disabled="loading || !roomName"
    :title="isSubscribed ? 'Diesem Raum nicht mehr folgen' : 'Diesem Raum folgen'"
  >
    <span class="icon">{{ isSubscribed ? '🔔' : '🔕' }}</span>
  </button>
</template>

<style scoped>
.room-sub-btn {
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-left: 5px;
  vertical-align: middle;
}

.room-sub-btn:hover    { background: #f0f0f0; transform: scale(1.1); }
.room-sub-btn.is-active { color: #f1c40f; }
.room-sub-btn:disabled  { opacity: 0.5; cursor: not-allowed; }

.icon { font-size: 1.1rem; }
</style>