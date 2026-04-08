<script setup>
import { computed } from 'vue';
import { useDocumentStore } from '@/stores/documents/documents';

/**
 * DocumentOptionsFavorites
 *
 * Zeigt den aktuellen Favoritenstatus eines Dokuments an und erlaubt
 * das Toggeln per Klick. Reagiert reaktiv auf Änderungen im Store.
 *
 * Props:
 *   item — Das Dokument-Objekt; item.ms_id wird als Favoriten-Identifier genutzt.
 */

const props = defineProps({
  item: Object
});

const store = useDocumentStore();

const isFavorite = computed(() => store.favorites.includes(props.item.ms_id));

const toggleFav = async () => {
  await store.toggleFavorite(props.item.ms_id);
};
</script>

<template>
  <div class="option-item" @click="toggleFav" role="button" :aria-label="isFavorite ? 'Aus Favoriten entfernen' : 'Zu Favoriten hinzufügen'">
    <span class="fav-star" :class="{ 'active': isFavorite }">
      {{ isFavorite ? '★' : '☆' }}
    </span>
    <span class="label">
      {{ isFavorite ? 'Aus Favoriten entfernen' : 'Zu Favoriten hinzufügen' }}
    </span>
  </div>
</template>

<style scoped>
.option-item {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  padding: 8px;
  border-radius: 4px;
  transition: background 0.2s;
}

.option-item:hover {
  background: #f0f0f0;
}

.fav-star {
  font-size: 1.5rem;
  color: #ccc;
}

.fav-star.active {
  color: #f1c40f;
}

.label {
  font-size: 0.95rem;
}
</style>