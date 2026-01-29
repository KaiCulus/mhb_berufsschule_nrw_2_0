<script setup>
import { ref, watch } from 'vue';
import { useDocumentStore } from '@/stores/documents/documents';
import DocumentOptionsMain from '@/components/documents/documentOptionsMenu/DocumentOptionsMain.vue';

const store = useDocumentStore();
const selectedItemForOptions = ref(null);
const showOptions = ref(false);

watch(() => store.searchQuery, () => {
  store.searchSelectedIndex = -1;
});

// Zentrale Funktion zum Öffnen des Dokuments
const openDocument = (doc) => {
  if (doc && doc.share_url) {
    window.open(doc.share_url, '_blank');
  }
};

const handleKeydown = (e) => {
  const results = store.filteredDocuments;
  if (e.key === 'Escape') {
    clearSearch();
    return;
  }
  if (!results.length) return;

  if (e.key === 'ArrowDown') {
    store.searchSelectedIndex = Math.min(store.searchSelectedIndex + 1, results.length - 1);
    e.preventDefault();
  } else if (e.key === 'ArrowUp') {
    store.searchSelectedIndex = Math.max(store.searchSelectedIndex - 1, 0);
    e.preventDefault();
  } else if (e.key === 'Enter' && store.searchSelectedIndex >= 0) {
    openDocument(results[store.searchSelectedIndex]);
  }
};

const openOptions = (doc) => {
  selectedItemForOptions.value = doc;
  showOptions.value = true;
};

// Tooltip-Logik (wie im DocumentTree)
const getBreadcrumbTooltip = (doc) => {
  const pathArray = store.getPath(doc.ms_id, null);
  return pathArray.map(p => p.name_original).join(' > ');
};

const clearSearch = () => {
  store.searchQuery = '';
};
</script>

<template>
  <div class="search-wrapper" @keydown="handleKeydown">
    <div class="search-container">
      <div class="search-input-wrapper">
        <span class="search-icon">🔍</span>
        <input 
          v-model="store.searchQuery" 
          type="text" 
          placeholder="Suche... (Pfeiltasten zum Navigieren)"
          class="search-input"
          @focus="store.searchSelectedIndex = -1"
        />
        <button v-if="store.searchQuery" @click="clearSearch" class="clear-btn">✕</button>
      </div>

      <div v-if="store.searchQuery && store.filteredDocuments.length > 0" class="results-overlay">
        <div 
          v-for="(doc, index) in store.filteredDocuments" 
          :key="doc.ms_id" 
          class="result-item"
          :class="{ 'is-selected': index === store.searchSelectedIndex }"
          @mouseenter="store.searchSelectedIndex = index"
          @click="openDocument(doc)" 
        >
          <div class="result-content">
            <div class="item-main">
              <span class="icon" :title="getBreadcrumbTooltip(doc)">
                {{ doc.is_folder ? '📁' : '📄' }}
              </span>
              <span class="name">{{ doc.name_original }}</span>
            </div>
            
            <div v-if="doc.aliases && doc.aliases.length > 0" class="alias-tags">
              <span v-for="alias in doc.aliases" :key="alias" class="alias-tag">
                🏷️ {{ alias }}
              </span>
            </div>
          </div>

          <div class="actions">
            <span class="options-trigger" @click.stop="openOptions(doc)">...</span>
          </div>
        </div>
      </div>

      <div v-else-if="store.searchQuery" class="no-results">
        Keine Treffer gefunden.
      </div>
    </div>

    <DocumentOptionsMain 
      v-if="showOptions && selectedItemForOptions" 
      :item="selectedItemForOptions" 
      @close="showOptions = false"
    />
  </div>
</template>

<style scoped>
/* Zentrierung der gesamten Komponente */
.search-wrapper {
  display: flex;
  justify-content: center;
  width: 100%;
  margin-bottom: 30px;
}

.search-container {
  position: relative;
  width: 100%;
  max-width: 800px; /* Breite der Suchbox */
}

/* Die große ovale Box */
.search-input-wrapper {
  display: flex;
  align-items: center;
  background: white;
  border: 2px solid #0e64a6;
  border-radius: 50px; /* Voll-Oval */
  padding: 10px 25px;
  box-shadow: 0 4px 15px rgba(14, 100, 166, 0.1);
  transition: box-shadow 0.3s ease;
}

.search-input-wrapper:focus-within {
  box-shadow: 0 4px 20px rgba(14, 100, 166, 0.25);
}

.search-icon { margin-right: 15px; font-size: 1.2rem; }

.search-input {
  border: none;
  outline: none;
  flex-grow: 1;
  padding: 8px 0;
  font-size: 1.1rem;
}

.clear-btn {
  background: none;
  border: none;
  cursor: pointer;
  color: #999;
  font-size: 1.2rem;
  padding: 5px;
}

/* Ergebnisliste Styling */
.results-overlay {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #ccc;
  border-radius: 15px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.15);
  z-index: 1000;
  max-height: 400px;
  overflow-y: auto;
  margin-top: 10px;
}

.result-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 20px;
  border-bottom: 1px solid #eee;
  cursor: pointer;
  transition: background 0.2s;
}

.result-item:last-child { border-bottom: none; }

.result-item.is-selected {
  background-color: #f0f7ff;
  border-left: 5px solid #0e64a6;
}

.result-content { flex-grow: 1; text-align: left; }

.item-main { display: flex; align-items: center; gap: 10px; font-weight: 500; color: #333; }

.breadcrumb {
  font-size: 0.75rem;
  color: #777;
  margin-top: 3px;
  padding-left: 28px; /* Alignment mit dem Namen */
}

.options-trigger {
  padding: 0 10px;
  font-weight: bold;
  font-size: 1.5rem;
  color: #0e64a6;
  line-height: 1;
}

.no-results {
  position: absolute;
  top: 100%;
  width: 100%;
  background: white;
  padding: 15px;
  border-radius: 15px;
  margin-top: 10px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
  color: #666;
  text-align: center;
}

.alias-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  margin-top: 5px;
  padding-left: 28px;
}

.alias-tag {
  font-size: 0.7rem;
  background: #f0f0f0;
  color: #555;
  padding: 2px 8px;
  border-radius: 10px;
  border: 1px solid #ddd;
}

.icon {
  cursor: help; /* Signalisiert, dass es hier Infos per Hover gibt */
}

/* Fix für Click-Area: Sicherstellen, dass der Content nicht den Klick schluckt */
.result-content {
  flex-grow: 1;
  pointer-events: none; /* Klicks gehen durch auf .result-item */
}

.result-item * {
  pointer-events: auto; /* Buttons und Links innerhalb sollen wieder klickbar sein */
}
</style>