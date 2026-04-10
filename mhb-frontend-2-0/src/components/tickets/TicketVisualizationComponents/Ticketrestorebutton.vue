<script setup>
import { ref } from 'vue';
import axios from '@/scripts/axios';

/**
 * TicketRestoreButton — Wiederherstellen-Button für archivierte Tickets
 *
 * Kapselt den Restore-Request und den zugehörigen Ladezustand.
 * Gibt nach erfolgreichem Restore ein 'restored'-Event ab, damit
 * die Elternkomponente die Liste neu laden kann.
 *
 * Props:
 *   ticketId — ID des archivierten Tickets
 *
 * Emits:
 *   restored(ticketId)  — Restore war erfolgreich
 *   error(message)      — Restore ist fehlgeschlagen
 */

const props = defineProps({
  ticketId: { type: Number, required: true },
});

const emit = defineEmits(['restored', 'error']);

const isRestoring = ref(false);

const restore = async () => {
  isRestoring.value = true;
  try {
    await axios.post('/api/tickets/restore', { ticketId: props.ticketId });
    emit('restored', props.ticketId);
  } catch (error) {
    emit('error', `Fehler beim Wiederherstellen von Ticket #${props.ticketId}.`);
  } finally {
    isRestoring.value = false;
  }
};
</script>

<template>
  <button
    class="restore-btn"
    :disabled="isRestoring"
    @click="restore"
  >
    {{ isRestoring ? 'Wird wiederhergestellt…' : '↩ Wiederherstellen' }}
  </button>
</template>

<style scoped>
.restore-btn {
  width: 100%;
  padding: 8px 12px;
  background: #27ae60;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.9rem;
  transition: background 0.2s;
}

.restore-btn:hover:not(:disabled) {
  background: #1e8449;
}

.restore-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>