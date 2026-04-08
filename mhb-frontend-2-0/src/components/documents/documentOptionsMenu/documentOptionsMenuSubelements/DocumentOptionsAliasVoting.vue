<script setup>
import { ref, onMounted } from 'vue';
import { useDocumentStore } from '@/stores/documents/documents';

/**
 * DocumentOptionsAliasVoting
 *
 * Zeigt alle Alias-Vorschläge für ein Dokument an und erlaubt:
 *   - Abstimmen für bestehende Aliase (Optimistic UI)
 *   - Neue Aliase vorschlagen
 *
 * Aliase werden lokal gehalten — sie sind flüchtig und werden nicht
 * in den globalen Store geschrieben.
 *
 * Props:
 *   item — Das Dokument-Objekt; item.scope und item.ms_id werden für API-Calls benötigt.
 */

const props = defineProps({
  item: Object
});

const store = useDocumentStore();
const aliases = ref([]);
const newAliasText = ref('');
const loading = ref(false);
const errorMessage = ref('');
const isProcessing = ref(false);

const loadAliases = async () => {
  loading.value = true;
  try {
    aliases.value = await store.fetchAliases(props.item.scope, props.item.ms_id);
  } finally {
    loading.value = false;
  }
};

/**
 * Toggelt die Stimme des Users für einen Alias.
 * Aktualisiert vote_count und user_voted sofort lokal (Optimistic UI)
 * und lädt bei Fehler die Liste neu.
 */
const handleVote = async (alias) => {
  if (isProcessing.value) return;

  isProcessing.value = true;
  try {
    await store.toggleAliasVote(alias.id);
    if (alias.user_voted) {
      alias.vote_count--;
      alias.user_voted = 0;
    } else {
      alias.vote_count++;
      alias.user_voted = 1;
    }
  } catch (error) {
    console.error('Voting fehlgeschlagen', error);
    await loadAliases();
  } finally {
    isProcessing.value = false;
  }
};

/** Sendet einen neuen Alias-Vorschlag und lädt die Liste danach neu. */
const submitNewAlias = async () => {
  const text = newAliasText.value.trim();
  if (!text || isProcessing.value) return;

  isProcessing.value = true;
  errorMessage.value = '';
  try {
    await store.suggestAlias(props.item.ms_id, text);
    newAliasText.value = '';
    await loadAliases();
  } catch (error) {
    errorMessage.value = 'Vorschlag konnte nicht gespeichert werden. Bitte erneut versuchen.';
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

    <p v-if="errorMessage" class="error-message">{{ errorMessage }}</p>
  </div>
</template>

<style scoped>
.alias-voting {
  padding: 10px;
}

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

.vote-btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.vote-btn.processing {
  filter: grayscale(1);
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

.error-message {
  color: #c0392b;
  font-size: 0.85rem;
  margin-top: 8px;
}
</style>