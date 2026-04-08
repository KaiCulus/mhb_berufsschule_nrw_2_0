<script setup>
import { ref } from 'vue';
import Materialeingabe from '@/components/materialcomponents/Materialeingabe.vue';
import Materialsearch from '@/components/materialcomponents/Materialsearch.vue';
import Materialliste from '@/components/materialcomponents/Materialliste.vue';

/**
 * materialsearchpage — Ressourcen-Finder
 *
 * Verwaltungsseite für Materialien/Ressourcen mit zwei Modi:
 *   - Suchen: Durchsuchen und Bearbeiten bestehender Einträge
 *   - Eintragen: Neues Material hinzufügen
 *
 * Bearbeitung läuft über ein Modal, das von beiden Tabs aus geöffnet
 * werden kann. Nach dem Speichern wird die Liste automatisch aktualisiert.
 */

const activeTab = ref('search');
const listRef = ref(null);
const searchRef = ref(null);
const editingMaterial = ref(null);

/** Wird nach dem Speichern im Edit-Modal aufgerufen. */
const handleSaved = () => {
  editingMaterial.value = null;
  listRef.value?.refresh();
};

/** Wird nach dem Hinzufügen im "Eintragen"-Tab aufgerufen. */
const handleNewItemAdded = () => {
  listRef.value?.refresh();
};

const openEdit = (item) => {
  editingMaterial.value = item;
};
</script>

<template>
  <div class="page-wrapper">
    <header class="page-header">
      <h1>🛠️ Ressourcen-Finder</h1>
      <div class="tab-nav">
        <button :class="{ active: activeTab === 'search' }" @click="activeTab = 'search'">🔍 Suchen</button>
        <button :class="{ active: activeTab === 'add' }" @click="activeTab = 'add'">➕ Eintragen</button>
      </div>
    </header>

    <main class="content">
      <div v-if="activeTab === 'search'">
        <Materialsearch ref="searchRef" @edit="openEdit" @deleted="listRef?.refresh()" />
        <Materialliste ref="listRef" @edit="openEdit" />
      </div>

      <div v-if="activeTab === 'add'">
        <Materialeingabe @saved="handleNewItemAdded" />
      </div>

      <div v-if="editingMaterial" class="modal-overlay" @click.self="editingMaterial = null">
        <div class="modal-content">
          <button class="close-modal" @click="editingMaterial = null" aria-label="Schließen">✕</button>
          <Materialeingabe
            :edit-data="editingMaterial"
            @saved="handleSaved"
            @cancel="editingMaterial = null"
          />
        </div>
      </div>
    </main>
  </div>
</template>

<style scoped>
.page-wrapper {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  text-align: center;
  margin-bottom: 30px;
}

.tab-nav {
  display: inline-flex;
  background: #eee;
  padding: 5px;
  border-radius: 12px;
  margin-top: 20px;
}

.tab-nav button {
  padding: 10px 25px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: bold;
  transition: all 0.3s;
  background: transparent;
  color: #666;
}

.tab-nav button.active {
  background: white;
  color: #00b894;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 16px;
  position: relative;
  width: 95%;
  max-width: 650px;
  padding: 10px;
}

.close-modal {
  position: absolute;
  top: 20px;
  right: 20px;
  background: #eee;
  border: none;
  border-radius: 50%;
  width: 30px;
  height: 30px;
  cursor: pointer;
  z-index: 10;
}
</style>