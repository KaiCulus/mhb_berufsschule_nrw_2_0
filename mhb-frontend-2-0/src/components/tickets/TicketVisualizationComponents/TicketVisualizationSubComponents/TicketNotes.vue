<script setup>
import { ref } from 'vue';
import axios from '@/scripts/axios';

const props = defineProps({
  ticketId: { type: Number, required: true },
  comments: { type: Array, default: () => [] }
});

const emit = defineEmits(['refresh']);
const newComment = ref('');
const isSubmitting = ref(false);

const addNote = async () => {
  if (!newComment.value.trim()) return;
  
  isSubmitting.value = true;
  try {
    await axios.post('/api/tickets/comment', {
      ticketId: props.ticketId,
      comment: newComment.value
      // isInternal wurde hier entfernt
    });
    newComment.value = '';
    emit('refresh');
  } catch (error) {
    alert("Notiz konnte nicht gespeichert werden.");
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div class="notes-container">
    <h3>📝 Notizen & Verlauf</h3>
    
    <div class="notes-list">
      <div v-for="note in comments" :key="note.id" class="note-item">
        <div class="note-header">
          <strong>{{ note.author_name }}</strong>
          <span>{{ new Date(note.created_at).toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' }) }}</span>
        </div>
        <p class="note-text">{{ note.comment }}</p>
      </div>
      
      <div v-if="comments.length === 0" class="no-notes">
        Noch keine Notizen zu diesem Ticket vorhanden.
      </div>
    </div>

    <div class="note-input-area">
      <textarea 
        v-model="newComment" 
        placeholder="Neue Notiz hinzufügen..." 
        rows="3"
      ></textarea>
      <button 
        @click="addNote" 
        :disabled="isSubmitting || !newComment.trim()"
        class="add-note-btn"
      >
        {{ isSubmitting ? 'Speichert...' : 'Notiz speichern' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.notes-container {
  display: flex;
  flex-direction: column;
  gap: 15px;
  background: #f8f9fa;
  padding: 15px;
  border-radius: 8px;
  height: 100%;
}

.notes-list {
  max-height: 300px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.note-item {
  background: white;
  padding: 10px;
  border-radius: 6px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  border-left: 4px solid #0e64a6;
}

.note-header {
  display: flex;
  justify-content: space-between;
  font-size: 0.75rem;
  color: #777;
  margin-bottom: 5px;
}

.note-text {
  margin: 0;
  font-size: 0.9rem;
  white-space: pre-wrap;
}

.note-input-area {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

textarea {
  width: 100%;
  padding: 8px;
  border-radius: 6px;
  border: 1px solid #ddd;
  resize: none;
}

.add-note-btn {
  align-self: flex-end;
  padding: 8px 16px;
  background: #0e64a6;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}

.add-note-btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>