<script setup>
import { ref, computed, onMounted } from 'vue';
import { useDocumentStore } from '@/stores/documents/documents';
import DocumentOptionsMain from '@/components/documents/documentOptionsMenu/DocumentOptionsMain.vue';

/**
 * DocumentTree
 *
 * Rekursive Baumkomponente für die Dokumentenstruktur.
 * Jede Instanz rendert die direkten Kinder von `parentId`; Unterordner
 * instanziieren wiederum eine DocumentTree-Komponente (Rekursion).
 *
 * Props:
 *   parentId       — ms_id des Elternknotens; null/undefined → Root-Ebene
 *   depth          — Rekursionstiefe, steuert das visuelle Styling
 *   rootId         — ms_id des übergeordneten Wurzelknotens (für Breadcrumb-Pfad)
 *   inheritedColor — Akzentfarbe des übergeordneten Ordners, wird an alle
 *                    Unterebenen weitergegeben für konsistentes Farbschema
 */

const props = defineProps({
  parentId: String,
  depth: { type: Number, default: 0 },
  rootId: String,
  inheritedColor: String,
  scope: { type: String, default: null }
});

const store = useDocumentStore();
const items = computed(() => store.getTree(props.parentId, props.scope));
const openStates = ref({});
const selectedItem = ref(null);
const showOptions = ref(false);

const presetColors = [
  '#ff9800', '#e51c23', '#8bc34a', '#009688', '#03a9f4', '#001f3f'
];

/**
 * Gibt eine stabile Akzentfarbe für ein Element zurück.
 * Auf Tiefe 0 wird die Farbe anhand des Index aus den Presets gewählt
 * (oder per Hash aus der ms_id generiert, wenn Presets erschöpft).
 * Auf tieferen Ebenen wird die inheritedColor des Elternordners genutzt,
 * damit der gesamte Ast einheitlich eingefärbt bleibt.
 */
const getStableColor = (item, index) => {
  if (props.depth > 0) return props.inheritedColor;

  if (index < presetColors.length) return presetColors[index];

  let hash = 0;
  const str = item.ms_id || '';
  for (let i = 0; i < str.length; i++) {
    hash = str.charCodeAt(i) + ((hash << 5) - hash);
  }
  const c = (hash & 0x00FFFFFF).toString(16).toUpperCase();
  return '#' + '00000'.substring(0, 6 - c.length) + c;
};

onMounted(() => {
  // Auf Desktop (>= 1024px) alle Root-Ordner beim ersten Laden aufklappen.
  // Kein reaktiver Resize-Listener — das initiale Layout reicht hier aus.
  const isDesktop = window.innerWidth >= 1024;
  if (props.depth === 0 && isDesktop) {
    items.value.forEach(item => {
      if (item.is_folder) openStates.value[item.ms_id] = true;
    });
  }
});

const toggle = (id) => {
  openStates.value[id] = !openStates.value[id];
};

/** Erzeugt den Breadcrumb-Pfad als Tooltip-String für das Dokument-Icon. */
const getBreadcrumbTooltip = (item) => {
  return store.getPath(item.ms_id).map(p => p.name_original).join(' > ');
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
  <div class="document-tree" :class="{ 'root-level': depth === 0 }">
    <div
      v-for="(item, index) in items"
      :key="item.ms_id"
      class="item-container"
      :style="{ '--item-color': getStableColor(item, index) }"
    >
      <div
        v-if="item.is_folder"
        class="folder-row"
        :class="['depth-' + depth]"
        :style="depth === 0
          ? { backgroundColor: 'var(--item-color)', color: 'white' }
          : { border: '3px solid var(--item-color)' }"
        @click="toggle(item.ms_id)"
      >
        <span class="icon" :title="getBreadcrumbTooltip(item)">
          {{ openStates[item.ms_id] ? '📂' : '📁' }}
        </span>
        <span class="name">{{ item.name_original }}</span>
        <a :href="item.share_url" target="_blank" class="ms-link">🔗</a>
        <span class="options" @click.stop="openOptions(item)">...</span>
      </div>

      <div
        v-else
        class="file-row"
        :style="{ border: '3px solid var(--item-color)' }"
      >
        <span class="icon" :title="getBreadcrumbTooltip(item)">📄</span>
        <a :href="item.share_url" target="_blank" class="name">{{ item.name_original }}</a>
        <span class="options" @click.stop="openOptions(item)">...</span>
      </div>

      <DocumentTree
        v-if="item.is_folder && openStates[item.ms_id]"
        :parent-id="item.ms_id"
        :depth="depth + 1"
        :root-id="rootId"
        :scope="scope"
        :inherited-color="getStableColor(item, index)"
        class="nested-tree"
      />
    </div>

    <DocumentOptionsMain v-if="showOptions && selectedItem" :item="selectedItem" @close="closeOptions" />
  </div>
</template>

<style scoped>
.document-tree {
  margin-left: 0;
  width: 100%;
}

@media (min-width: 1024px) {
  .root-level {
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
    align-items: flex-start;
  }

  .root-level > .item-container {
    flex: 1 1 300px;
    max-width: 450px;
  }
}

.item-container {
  margin-bottom: 12px;
  font-family: sans-serif;
}

.folder-row {
  display: flex;
  align-items: center;
  cursor: pointer;
  padding: 10px 15px;
  border-radius: 4px;
  font-weight: bold;
  transition: all 0.2s;
  border: 2px solid transparent;
}

/*
 * Untergeordnete Ordner (depth > 0) erhalten weißen Hintergrund.
 * !important überschreibt bewusst das inline :style-Binding, das nur
 * für depth-0-Ordner (farbiger Hintergrund) gesetzt wird.
 */
.folder-row:not(.depth-0) {
  background-color: white !important;
  color: #333;
  margin-top: 8px;
  font-weight: 500;
}

.file-row {
  display: flex;
  align-items: center;
  background-color: white;
  color: #333;
  padding: 8px 20px;
  margin-top: 8px;
  border-radius: 50px;
  text-decoration: none;
  font-size: 0.95rem;
  border: 2px solid transparent;
}

.nested-tree {
  margin-top: 5px;
  padding-left: 15px;
  border-left: 1px dashed #ddd;
}

.icon {
  margin-right: 10px;
  font-size: 1.1rem;
  cursor: help;
}

.name {
  flex-grow: 1;
  text-decoration: none;
  color: inherit;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ms-link,
.options {
  margin-left: 10px;
  cursor: pointer;
  opacity: 0.6;
  text-decoration: none;
}

.ms-link:hover,
.options:hover {
  opacity: 1;
}

.folder-row:hover,
.file-row:hover {
  filter: brightness(0.95);
}
</style>