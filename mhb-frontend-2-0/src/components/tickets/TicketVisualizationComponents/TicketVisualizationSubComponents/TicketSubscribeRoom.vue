<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from '@/scripts/axios';
import { useAuthStore } from '@/stores/authentification/auth';
import { TICKET_CONFIG } from '@/components/tickets/config/ticketConfig';

/**
 * TicketSubscribeRoom — Raum-Abonnement-Verwaltung
 *
 * Erlaubt dem User, Räume zu abonnieren, um bei neuen Tickets für
 * diese Räume benachrichtigt zu werden.
 *
 * Validierung erfolgt gegen TICKET_CONFIG, sodass nur bekannte Räume
 * abonniert werden können. Beim Entfernen wird die Validierung
 * übersprungen — der Server kennt das Abo bereits.
 *
 * Die Abonnement-Liste wird optimistisch im Frontend aktualisiert
 * ohne erneuten Fetch nach jeder Änderung.
 */

const authStore = useAuthStore();

const roomInput = ref('');
const subscribedRooms = ref([]);
const loading = ref(false);

const isValid = computed(() => TICKET_CONFIG.isValidRoom(roomInput.value));

const fetchSubscriptions = async () => {
  try {
    const res = await axios.get(`/api/tickets/subscribe-room/${authStore.dbId}`);
    subscribedRooms.value = res.data;
  } catch (error) {
    console.error('Fehler beim Laden der Raum-Abonnements:', error);
  }
};

/**
 * Toggelt das Abo für einen Raum.
 * @param {string|null} roomName — Wenn übergeben: Abo entfernen (kein Validierungs-Check).
 *                                 Wenn null: roomInput-Wert verwenden und validieren.
 */
const toggleSub = async (roomName = null) => {
  const room = (roomName || roomInput.value).toUpperCase().trim();
  if (!room) return;

  // Validierung nur beim Hinzufügen — beim Entfernen ist der Raum bereits bekannt
  if (!roomName && !TICKET_CONFIG.isValidRoom(room)) {
    alert('Ungültiger Raum');
    return;
  }

  loading.value = true;
  try {
    const res = await axios.post('/api/tickets/subscribe-room', { room });

    if (res.data.subscription === 'subscribed') {
      if (!subscribedRooms.value.includes(room)) {
        subscribedRooms.value.push(room);
      }
      roomInput.value = '';
    } else {
      subscribedRooms.value = subscribedRooms.value.filter(r => r !== room);
    }
  } catch (error) {
    console.error('Raum-Abo fehlgeschlagen:', error);
    alert('Aktion fehlgeschlagen.');
  } finally {
    loading.value = false;
  }
};

onMounted(fetchSubscriptions);
</script>

<template>
  <div class="room-manager">
    <h4>Meine Raum-Abonnements</h4>
    <p class="info-text">Du wirst automatisch benachrichtigt, wenn Tickets für diese Räume erstellt werden.</p>

    <div class="input-group">
      <input
        v-model="roomInput"
        placeholder="Raum hinzufügen (z.B. R15)"
        :class="{ 'invalid': roomInput && !isValid }"
        @keyup.enter="toggleSub()"
      />
      <button @click="toggleSub()" :disabled="!isValid || loading" class="add-btn">
        {{ loading ? '...' : 'Hinzufügen' }}
      </button>
    </div>

    <div class="room-tags">
      <div v-for="room in subscribedRooms" :key="room" class="room-tag">
        <span>{{ room }}</span>
        <button @click="toggleSub(room)" title="Abo kündigen" class="remove-tag">×</button>
      </div>
      <p v-if="subscribedRooms.length === 0" class="empty-hint">Noch keine Räume abonniert.</p>
    </div>
  </div>
</template>

<style scoped>
.room-manager {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  margin-bottom: 25px;
}

.info-text {
  font-size: 0.85rem;
  color: #666;
  margin-bottom: 15px;
}

.input-group {
  display: flex;
  gap: 10px;
  margin-bottom: 15px;
}

input {
  flex-grow: 1;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 6px;
}

input.invalid {
  border-color: #e74c3c;
  background: #fff5f5;
}

.add-btn {
  background: #0e64a6;
  color: white;
  border: none;
  padding: 0 20px;
  border-radius: 6px;
  cursor: pointer;
}

.add-btn:disabled { background: #ccc; }

.room-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.room-tag {
  display: flex;
  align-items: center;
  background: #f0f4f8;
  padding: 5px 12px;
  border-radius: 20px;
  border: 1px solid #d1d9e0;
  font-weight: bold;
  color: #0e64a6;
}

.remove-tag {
  background: none;
  border: none;
  color: #e74c3c;
  margin-left: 8px;
  font-size: 1.2rem;
  cursor: pointer;
  line-height: 1;
}

.empty-hint {
  font-size: 0.85rem;
  color: #999;
  font-style: italic;
}
</style>