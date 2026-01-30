<script setup>
import { ref, onMounted } from 'vue';
import axios from '@/scripts/axios';
import { useAuthStore } from '@/stores/authentification/auth';

const props = defineProps({
  ticketId: { type: Number, required: true }
});

const isSubscribed = ref(false);
const authStore = useAuthStore();

const checkStatus = async () => {
  // Wir laden die Ticket-Details kurz, um zu sehen ob der User in den Subs steht
  // Alternativ: Der Controller liefert das im getDetail() direkt mit.
  try {
    const response = await axios.get(`/api/tickets/detail/${props.ticketId}`);
    // Einfache Prüfung: Ist die eigene ID in der (noch zu liefernden) Abonnentenliste?
    // Für diesen Entwurf triggern wir einfach den Toggle-Status vom Server.
  } catch (e) { /* ... */ }
};

const toggleSub = async () => {
  try {
    const response = await axios.post('/api/tickets/subscribe', { 
      ticketId: props.ticketId 
    });
    isSubscribed.value = response.data.subscription === 'subscribed';
  } catch (error) {
    console.error("Subscription fehlgeschlagen");
  }
};
</script>

<template>
  <button 
    @click="toggleSub" 
    :class="['sub-btn', { 'is-active': isSubscribed }]"
    title="Ticket folgen (Benachrichtigung bei Änderungen)"
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