<script setup>
  import { ref, computed } from 'vue';
  import { useDocumentStore } from '@/stores/documents/documents';
  import DocumentOptionsMain from '@/components/documents/documentOptionsMenu/DocumentOptionsMain.vue';

  const props = defineProps({
    parentId: String,
    depth: { type: Number, default: 0 },
    rootId: String
  });

  const store = useDocumentStore();
  const items = computed(() => store.getTree(props.parentId));
  const openStates = ref({}); // Speichert, welche Ordner offen sind
  const selectedItem = ref(null);
  const showOptions = ref(false);

  const toggle = (id) => {
    openStates.value[id] = !openStates.value[id];
  };

  // TODO: Optional: Für Mobile onClick handler für die Icons, um Pfad anzuzeigen. 
  const getBreadcrumbTooltip = (item) => {
    const pathArray = store.getPath(item.ms_id, props.rootId);
    return pathArray.map(p => p.name_original).join(' > ');
  };

  const openOptions = (item) => {
    selectedItem.value = item;
    showOptions.value = true;
  };

  const closeOptions = () => {
    showOptions.value = false;
    selectedItem.value = null;
  };
</script>

<template>
  <div class="document-tree" :style="{ paddingLeft: depth > 0 ? '20px' : '0' }">
    <div v-for="item in items" :key="item.ms_id" class="item-container">
      
      <div v-if="item.is_folder" class="folder-row" @click="toggle(item.ms_id)">
        <span 
          class="icon"
          :title="getBreadcrumbTooltip(item)"
        >
          {{ openStates[item.ms_id] ? '📂' : '📁' }}
        </span>
        <span class="name">{{ item.name_original }}</span>
        <a :href="item.share_url" target="_blank" class="ms-link">🔗</a>
        <span class="options" @click.stop="openOptions(item)">...</span>
      </div>

      <div v-else class="file-row">
        <span 
          class="icon"
          :title="getBreadcrumbTooltip(item)"
        >
            📄
        </span>
        <a :href="item.share_url" target="_blank" class="name">{{ item.name_original }}</a>
        <span class="options" @click.stop="openOptions(item)">...</span>
      </div>

      <DocumentTree 
        v-if="Number(item.is_folder) === 1 && openStates[item.ms_id]" 
        :parent-id="item.ms_id" 
        :depth="depth + 1"
        :root-id="rootId"
      />
    </div>
    <DocumentOptionsMain 
      v-if="showOptions && selectedItem" 
      :item="selectedItem" 
      @close="closeOptions"
    />
  </div>
</template>



<style scoped>
  /* Container für die gesamte Zeile */
  .item-container {
    margin-bottom: 8px;
    font-family: sans-serif;
  }

  /* ORDNER: Rechteckig laut deinem Bild */
  .folder-row {
    display: flex;
    align-items: center;
    cursor: pointer;
    background-color: #ff9800; /* Beispielfarbe für "Ergebnisse und Wirkungen" */
    color: white;
    padding: 10px 15px;
    border-radius: 4px; /* Rechteckig mit leichten Abrundungen */
    font-weight: bold;
    transition: background 0.2s;
  }

  .folder-row:hover {
    filter: brightness(1.1);
  }

  /* DATEI: Oval laut deinem Bild */
  .file-row {
    display: flex;
    align-items: center;
    background-color: white;
    border: 2px solid #0e64a6;
    color: #333;
    padding: 8px 20px;
    margin-top: 4px;
    margin-left: 10px;
    border-radius: 50px; /* Oval / Capsule-Shape */
    text-decoration: none;
  }

  /* Hilfsklassen für Icons und Links */
  .icon { 
    margin-right: 10px;
    font-size: 1.2rem;
    cursor: help;
   }
  .name { flex-grow: 1; text-decoration: none; color: inherit; }
  .ms-link, .favorite, .options {
    margin-left: 10px;
    cursor: pointer;
    opacity: 0.7;
  }
  .ms-link:hover, .favorite:hover, .options:hover { opacity: 1; }

  /* Einrücken der Unterebenen */
  .document-tree {
    border-left: 1px dashed #ccc;
    margin-left: 5px;
  }
</style>