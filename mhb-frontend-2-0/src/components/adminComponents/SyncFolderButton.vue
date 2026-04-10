<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/authentification/auth';
import { useDocumentStore } from '@/stores/documents/documents';
import axios from '@/scripts/axios.js';

/**
 * SyncFolderButton — Admin-Button für die manuelle Ordnersynchronisation
 *
 * Löst einen Microsoft-Graph-Sync für den angegebenen Scope aus
 * und lädt anschließend den Dokumentenbaum des Scopes neu.
 *
 * Props:
 *   syncType — Scope-Bezeichner für den Sync-Endpoint und die Berechtigungsprüfung
 *   label    — Beschriftung des Buttons, default: 'Ordner synchronisieren'
 */

const props = defineProps({
  syncType: { type: String, required: true },
  label:    { type: String, default: 'Ordner synchronisieren' }
});

const authStore = useAuthStore();
const documentStore = useDocumentStore();
const loading = ref(false);

const startSync = async () => {
  loading.value = true;
  try {
    await axios.post(`/api/sync/execute/${props.syncType}`);
    await documentStore.refreshDocuments(props.syncType);
    alert(`${props.label} erfolgreich abgeschlossen.`);
  } catch (error) {
    alert('Fehler: ' + (error.response?.data?.error || error.message));
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div v-if="authStore.permissions[syncType]" class="sync-wrapper">
    <button @click="startSync" :disabled="loading" class="btn-sync">
      {{ loading ? 'Synchronisiere...' : label }}
    </button>
  </div>
</template>