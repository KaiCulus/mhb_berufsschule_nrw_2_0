<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useDocumentStore } from '@/stores/documents/documents';
import DocumentTree from '@/components/documents/DocumentTree.vue';
import SyncFolderButton from '@/components/adminComponents/SyncFolderButton.vue';
import DocumentSearch from '@/components/documents/DocumentSearch.vue';
import AnleitungMain from '@/components/anleitung/anleitungMain.vue';
import Verwaltungsdokumenteanleitung from '@/components/anleitung/anleitungsbereiche/verwaltungsdokumenteanleitung.vue';

/**
 * mhbpage — Verwaltungsdokumente
 *
 * Zeigt den vollständigen Dokumentenbaum des Verwaltungs-Scopes an.
 * Lädt beim Mounten Dokumente und Favoriten des eingeloggten Users.
 * Leert die Suche beim Verlassen, damit andere Seiten mit sauberem
 * Such-State starten.
 *
 * Wiederverwendung für andere Scopes:
 *   VITE_FOLDER_ID_* in .env ergänzen, scope-String in fetchDocuments
 *   und syncType/label im SyncFolderButton anpassen.
 */

const store = useDocumentStore();
const rootFolderId = import.meta.env.VITE_FOLDER_ID_VERWALTUNG;

onMounted(async () => {
  await store.fetchDocuments('verwaltung');
  await store.fetchFavorites();
});

onUnmounted(() => {
  store.clearSearch();
});
</script>

<template>
  <div class="mhb-container">
    <div class="centered-header">
      <h1>MHB (Verwaltungsdokumente)</h1>
      <SyncFolderButton
        syncType="verwaltung"
        label="Verwaltungsdokumente synchronisieren"
      />
    </div>

    <DocumentSearch />
    <AnleitungMain
        label="Erklärung: MHB (Verwaltungsdokumente)"
      >
        <Verwaltungsdokumenteanleitung />
    </AnleitungMain>

    <div v-if="store.loading" class="loader">Lade Dokumente...</div>

    <div v-else class="tree-wrapper">
      <DocumentTree
        parent-id="root"
        :root-id="rootFolderId"
        :depth="0"
        scope="verwaltung"
      />
    </div>
  </div>
</template>

<style scoped>
.mhb-container {
  padding: 20px;
  max-width: 1400px;
  width: 95%;
  margin: 0 auto;
  overflow-x: hidden;
}

@media (max-width: 900px) {
  .mhb-container {
    padding: 12px 10px;
    width: 100%;
  }
}

.centered-header {
  text-align: center;
}

.tree-wrapper {
  background: transparent;
  padding: 15px 0;
}
</style>