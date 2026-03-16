<script setup>
//Seite kann für andere Dokumentenanzeigenseiten mit Änderungen an const rootFolderId und onMounted() genutzt werden.
import { onMounted, onUnmounted } from 'vue';
import { useDocumentStore } from '@/stores/documents/documents';
import DocumentTree from '@/components/documents/DocumentTree.vue';
import SyncFolderButton from '@/components/adminComponents/SyncFolderButton.vue';
import DocumentSearch from '@/components/documents/DocumentSearch.vue';

const store = useDocumentStore();
//Beim kopieren des Codes VERWALTUNG ersetzen.
const rootFolderId = import.meta.env.VITE_FOLDER_ID_VERWALTUNG;

onMounted(async () => {
  //beim kopieren des Codes 'verwaltung ersetzen'
  await store.fetchDocuments('verwaltung');
  await store.fetchFavorites();
});
onUnmounted(() => {
  store.searchQuery = ''; // Suche beim Verlassen leeren
});
</script>

<template>
  <div class="mhb-container">
    <div class="centeredTxt">
    <h1>MHB (Verwaltungsdokumente)</h1> 
    
      <SyncFolderButton 
        syncType="verwaltung"
        label="Verwaltungsdokumente synchronisieren"
      />
    </div>
    <DocumentSearch />

    <div v-if="store.loading" class="loader">Lade Dokumente...</div>
    
    <div v-else class="tree-wrapper">
      <DocumentTree 
      parent-id="root" 
      root-id="root"
      :depth="0" 
    />
    </div>
  </div>
</template>

<style scoped>

.centeredTxt{
  text-align: center;
}
.mhb-container {
  padding: 20px;
  /* Erhöht von 1000px auf 1400px oder 95% für echtes Breitbild */
  max-width: 1400px; 
  width: 95%;
  margin: 0 auto;
}

.tree-wrapper {
  /* Hintergrund auf transparent oder die Farbe der Page setzen, 
     damit die Spalten nicht in einem weißen Kasten eingesperrt wirken */
  background: transparent; 
  padding: 15px 0;
}
</style>