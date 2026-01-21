

<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/authentification/auth';
import axios from '@/scripts/axios.js';

// Wir definieren Props für die Flexibilität
const props = defineProps({
  syncType: { type: String, required: true }, // z.B. 'verwaltung' oder 'paedagogik'
  label: { type: String, default: 'Ordner synchronisieren' }
});

const authStore = useAuthStore();
const loading = ref(false);

const startSync = async () => {
  loading.value = true;
  try {
    // Hier wird der syncType dynamisch in die URL eingebaut!
    await axios.post(`/api/sync/execute/${props.syncType}`, {}, { withCredentials: true });
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
      <span v-if="loading">...</span>
      {{ loading ? 'Synchronisiere...' : label }}
    </button>
  </div>
</template>

