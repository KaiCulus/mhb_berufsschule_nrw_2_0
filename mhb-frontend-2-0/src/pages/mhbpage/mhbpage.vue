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
    <h1>MHB (Verwaltungsdokumente)</h1> 
    <SyncFolderButton 
      syncType="verwaltung"
      label="Verwaltungsdokumente synchronisieren"
    />
    <DocumentSearch />

    <div v-if="store.loading" class="loader">Lade Dokumente...</div>
    
    <div v-else class="tree-wrapper">
      <DocumentTree 
        :parent-id="rootFolderId" 
        :root-id="rootFolderId"
        :depth="0" 
      />
    </div>
  </div>
</template>

<style scoped>
.mhb-container {
  padding: 20px;
  max-width: 1000px;
  margin: 0 auto;
}
.tree-wrapper {
  background: white;
  border-radius: 8px;
  padding: 15px;
}
</style>