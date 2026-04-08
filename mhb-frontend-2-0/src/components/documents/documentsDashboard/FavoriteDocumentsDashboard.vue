<script setup>
import { onMounted, computed } from 'vue';
import { useDocumentStore } from '@/stores/documents/documents';
import { useAuthStore } from '@/stores/authentification/auth';

/**
 * FavoriteDocumentsDashboard
 *
 * Zeigt die Favoriten des eingeloggten Users als klickbare Liste an.
 * Kann optional auf einen Scope gefiltert werden — ohne Scope-Prop
 * werden alle Favoriten über alle Scopes hinweg angezeigt.
 *
 * Props:
 *   scope — Optionaler Dokumenten-Scope (z.B. 'verwaltung').
 *           Wenn gesetzt, werden nur Favoriten dieses Scopes angezeigt
 *           und beim initialen Laden wird genau dieser Scope vom Backend geladen.
 */

const props = defineProps({
  scope: { type: String, default: null }
});

const store = useDocumentStore();
const authStore = useAuthStore();

const displayedFavorites = computed(() => {
  if (props.scope) {
    return store.favoriteItemsByScope(props.scope);
  }
  return store.favoriteItems;
});

onMounted(async () => {
  if (store.documents.length === 0 && props.scope) {
    await store.fetchDocuments(props.scope);
  }

  if (store.favorites.length === 0 && authStore.dbId) {
    await store.fetchFavorites();
  }
});
</script>

<template>
  <div class="favorites-list">
    <div v-if="store.loading" class="empty-state">
      Lade Dokumente...
    </div>

    <div v-else-if="displayedFavorites.length === 0" class="empty-state">
      <template v-if="store.favorites.length > 0">
        Favoriten vorhanden, aber nicht im Bereich "{{ props.scope }}".
      </template>
      <template v-else>
        Noch keine Favoriten markiert.
      </template>
    </div>

    <template v-else>
      <div v-for="item in displayedFavorites" :key="item.ms_id" class="fav-item">
        <span class="icon">{{ item.is_folder ? '📁' : '📄' }}</span>
        <a :href="item.share_url" target="_blank" class="name">
          {{ item.name_original }}
        </a>
        <button class="remove-btn" @click="store.toggleFavorite(item.ms_id)" title="Aus Favoriten entfernen">
          ★
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.favorites-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 15px;
}

.fav-item {
  display: flex;
  align-items: center;
  background: white;
  padding: 12px 15px;
  border-radius: 8px;
  border: 1px solid #e0e0e0;
  transition: transform 0.2s, box-shadow 0.2s;
}

.fav-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.icon {
  margin-right: 12px;
  font-size: 1.2rem;
}

.name {
  flex-grow: 1;
  text-decoration: none;
  color: #2c3e50;
  font-weight: 500;
}

.remove-btn {
  background: none;
  border: none;
  color: #f1c40f;
  cursor: pointer;
  font-size: 1.2rem;
  padding: 5px;
}

.empty-state {
  color: #7f8c8d;
  font-style: italic;
  padding: 20px;
  text-align: center;
  background: #f9f9f9;
  border-radius: 8px;
}
</style>