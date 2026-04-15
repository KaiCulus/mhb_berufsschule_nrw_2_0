<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useDocumentStore } from '@/stores/documents/documents';
import DocumentTree from '@/components/documents/DocumentTree.vue';
import SyncFolderButton from '@/components/adminComponents/SyncFolderButton.vue';
import DocumentSearch from '@/components/documents/DocumentSearch.vue';
import AnleitungMain from '@/components/anleitung/anleitungMain.vue';
import Wissenssammlunganleitung from '@/components/anleitung/anleitungsbereiche/wissenssammlunganleitung.vue';

/**
 * Wissenssammlungspage — Allgemeine Wissenssammlung
 *
 * Zeigt den vollständigen Dokumentenbaum des Common-Scopes an.
 * Lädt beim Mounten Dokumente und Favoriten des eingeloggten Users.
 * Leert die Suche beim Verlassen, damit andere Seiten mit sauberem
 * Such-State starten.
 *
 * Wiederverwendung für andere Scopes:
 *   VITE_FOLDER_ID_* in .env ergänzen, scope-String in fetchDocuments
 *   und syncType/label im SyncFolderButton anpassen.
 */

const store = useDocumentStore();
const rootFolderId = import.meta.env.VITE_FOLDER_ID_COMMON;

onMounted(async () => {
  await store.fetchDocuments('common');
  await store.fetchFavorites();
});

onUnmounted(() => {
  store.clearSearch();
});
</script>

<template>
  <div class="wissenssammlung-container">
    <div class="centered-header">
      <h1>Allgemeine Wissenssammlung</h1>
      
      <SyncFolderButton
        syncType="common"
        label="Wissenssammlung synchronisieren"
      />
    </div>

    <DocumentSearch />
    <div class="notCentered">
      <AnleitungMain
        label="Erklärung: Wissenssammlung"
      >
        <Wissenssammlunganleitung /> 
      </AnleitungMain>
    </div>

    <div v-if="store.loading" class="loader">Lade Dokumente...</div>

    <div v-else class="tree-wrapper">
      <DocumentTree
        parent-id="root"
        :root-id="rootFolderId"
        :depth="0"
        scope="common"
      />
    </div>
  </div>
</template>

<style scoped>
.wissenssammlung-container {
  padding: 20px;
  max-width: 1400px;
  width: 95%;
  margin: 0 auto;
}

.centered-header {
  text-align: center;
}

.tree-wrapper {
  background: transparent;
  padding: 15px 0;
}
</style>