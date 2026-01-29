<script setup>
import { ref, onMounted } from 'vue';
import { useDocumentStore } from '@/stores/documents/documents';

const props = defineProps({
  item: Object
});

const store = useDocumentStore();
const aliases = ref([]);
const newAliasText = ref('');
const loading = ref(false);
const isProcessing = ref(false); // Die Klick-Sperre

const loadAliases = async () => {
  loading.value = true;
  try {
    aliases.value = await store.fetchAliases(props.item.ms_id);
  } finally {
    loading.value = false;
  }
};

const handleVote = async (alias) => {
  if (isProcessing.value) return; // Verhindert Mehrfachklicks
  
  isProcessing.value = true;
  try {
    await store.toggleAliasVote(alias.id);
    // Lokales Update für sofortiges Feedback (Optimistic UI)
    if (alias.user_voted) {
      alias.vote_count--;
      alias.user_voted = 0;
    } else {
      alias.vote_count++;
      alias.user_voted = 1;
    }
  } catch (error) {
    console.error("Voting fehlgeschlagen", error);
    await loadAliases(); // Bei Fehler Liste neu laden
  } finally {
    isProcessing.value = false;
  }
};

const submitNewAlias = async () => {
  const text = newAliasText.value.trim();
  if (!text || isProcessing.value) return;

  isProcessing.value = true;
  try {
    await store.suggestAlias(props.item.ms_id, text);
    newAliasText.value = '';
    await loadAliases(); // Hier laden wir neu, um die ID vom Server zu erhalten
  } finally {
    isProcessing.value = false;
  }
};

onMounted(loadAliases);
</script>

<template>
  <div class="alias-voting">
    <div v-if="loading" class="loader">Lade Vorschläge...</div>

    <div v-else class="alias-list">
      <div v-for="alias in aliases" :key="alias.id" class="alias-item">
        <span class="alias-text">{{ alias.alias_text }}</span>
        <button 
          @click="handleVote(alias)" 
          :disabled="isProcessing"
          :class="['vote-btn', { 'voted': Number(alias.user_voted) === 1, 'processing': isProcessing }]"
        >
          {{ Number(alias.user_voted) === 1 ? '✅' : '👍' }} {{ alias.vote_count }}
        </button>
      </div>
    </div>

    <div class="add-alias">
      <input 
        v-model="newAliasText" 
        type="text" 
        :disabled="isProcessing"
        placeholder="Namensvorschlag..." 
        @keyup.enter="submitNewAlias"
      />
      <button @click="submitNewAlias" :disabled="!newAliasText || isProcessing">
        {{ isProcessing ? '...' : 'Senden' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.alias-voting { padding: 10px; }
h3 { margin-bottom: 5px; font-size: 1.1rem; }
.subtitle { font-size: 0.85rem; color: #666; margin-bottom: 15px; }

.alias-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 20px;
}

.alias-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8f9fa;
  padding: 8px 12px;
  border-radius: 6px;
  border: 1px solid #eee;
}

.vote-btn {
  background: white;
  border: 1px solid #ddd;
  padding: 4px 8px;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
}

.vote-btn.voted {
  background: #0e64a6;
  color: white;
  border-color: #0e64a6;
}

.add-alias {
  display: flex;
  gap: 8px;
  margin-top: 15px;
}

.add-alias input {
  flex-grow: 1;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.add-alias button {
  background: #0e64a6;
  color: white;
  border: none;
  padding: 8px 15px;
  border-radius: 4px;
  cursor: pointer;
}

.vote-btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.vote-btn.processing {
  filter: grayscale(1);
}
</style>