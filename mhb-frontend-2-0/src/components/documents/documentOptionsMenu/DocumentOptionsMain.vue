<script setup>
import DocumentOptionsAliasVoting from '@/components/documents/documentOptionsMenu/documentOptionsMenuSubelements/DocumentOptionsAliasVoting.vue';
import DocumentOptionsFavorites from '@/components/documents/documentOptionsMenu/documentOptionsMenuSubelements/DocumentOptionsFavorites.vue';

/**
 * DocumentOptionsMain
 *
 * Modal-Overlay mit Dokument-Aktionen (Favorit, Alias-Voting).
 * Wird von DocumentTree und DocumentSearch geöffnet.
 *
 * Props:
 *   item — Das Dokument-Objekt, auf das sich die Aktionen beziehen.
 * Emits:
 *   close — Schließt das Modal (Klick auf Overlay oder Schließen-Button).
 */

const props = defineProps({
  item: Object
});

const emit = defineEmits(['close']);
</script>

<template>
  <div class="options-overlay" @click.self="emit('close')">
    <div class="options-card">
      <header>
        <h3>Optionen für {{ item.name_original }}</h3>
        <button @click="emit('close')" aria-label="Schließen">✕</button>
      </header>

      <div class="options-content">
        <DocumentOptionsFavorites :item="item" />
        <hr />
        <DocumentOptionsAliasVoting :item="item" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.options-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}

.options-card {
  background: white;
  padding: 20px;
  border-radius: 8px;
  width: 350px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.options-content {
  display: flex;
  flex-direction: column;
  gap: 15px;
}
</style>