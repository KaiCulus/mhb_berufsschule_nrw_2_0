<script setup>
import { ref } from 'vue';

/**
 * TicketImageUpload — Dateiauswahl-Komponente für das Ticket-Erstellungsformular
 *
 * Verwaltet die lokale Dateiauswahl (Drag & Drop + Klick) mit Vorschau.
 * Führt keinen eigenen API-Request durch — das übernimmt TicketFormMain
 * nach erfolgreichem Ticket-Submit.
 *
 * Die ausgewählten Dateien werden per defineExpose nach außen zugänglich gemacht:
 *   - getFiles()  → gibt das aktuelle File-Array zurück (für den Upload-Request)
 *   - reset()     → leert die Auswahl und gibt Object-URLs frei
 *
 * Fehler (Typ, Größe, Anzahl) werden intern als Slot-lose Inline-Meldung angezeigt,
 * damit TicketFormMain keinen eigenen Error-State dafür braucht.
 */

const MAX_IMAGES     = 5;
const MAX_SIZE_MB    = 5;
const MAX_SIZE_BYTES = MAX_SIZE_MB * 1024 * 1024;
const ALLOWED_TYPES  = ['image/jpeg', 'image/png', 'image/webp'];

const selectedFiles = ref([]); // { file: File, preview: string }[]
const isDragging    = ref(false);
const errorMessage  = ref('');
const fileInputRef  = ref(null);

// =========================================================================
// Validierung & Datei-Handling
// =========================================================================

const validateFile = (file) => {
  if (!ALLOWED_TYPES.includes(file.type)) {
    return `"${file.name}": ungültiger Typ. Erlaubt: JPEG, PNG, WEBP.`;
  }
  if (file.size > MAX_SIZE_BYTES) {
    return `"${file.name}": zu groß (max. ${MAX_SIZE_MB} MB).`;
  }
  return null;
};

const addFiles = (files) => {
  errorMessage.value = '';
  const errors = [];

  for (const file of files) {
    if (selectedFiles.value.length >= MAX_IMAGES) {
      errors.push(`Maximal ${MAX_IMAGES} Bilder erlaubt.`);
      break;
    }

    // Duplikat-Check anhand von Name + Größe
    const isDuplicate = selectedFiles.value.some(
      (f) => f.file.name === file.name && f.file.size === file.size
    );
    if (isDuplicate) continue;

    const error = validateFile(file);
    if (error) { errors.push(error); continue; }

    selectedFiles.value.push({ file, preview: URL.createObjectURL(file) });
  }

  if (errors.length) errorMessage.value = errors.join(' ');
};

const onFileInputChange = (event) => {
  addFiles(Array.from(event.target.files));
  event.target.value = ''; // Reset damit dieselbe Datei erneut wählbar ist
};

const onDrop = (event) => {
  isDragging.value = false;
  addFiles(Array.from(event.dataTransfer.files));
};

const removeFile = (index) => {
  URL.revokeObjectURL(selectedFiles.value[index].preview);
  selectedFiles.value.splice(index, 1);
};

// =========================================================================
// Öffentliche API für TicketFormMain (via Template-Ref)
// =========================================================================

/**
 * Gibt die ausgewählten File-Objekte zurück.
 * TicketFormMain ruft dies nach erfolgreichem Ticket-Submit auf.
 */
const getFiles = () => selectedFiles.value.map(({ file }) => file);

/**
 * Leert die Auswahl und gibt alle Object-URLs frei.
 * TicketFormMain ruft dies nach erfolgreichem Upload auf.
 */
const reset = () => {
  selectedFiles.value.forEach(({ preview }) => URL.revokeObjectURL(preview));
  selectedFiles.value = [];
  errorMessage.value  = '';
};

defineExpose({ getFiles, reset });
</script>

<template>
  <div class="image-upload-section">
    <label class="section-label">
      Bilder anhängen
      <span class="optional-hint">(optional, max. {{ MAX_IMAGES }} Bilder, {{ MAX_SIZE_MB }} MB je Datei)</span>
    </label>

    <!-- Inline-Fehlermeldung -->
    <div v-if="errorMessage" class="upload-error">{{ errorMessage }}</div>

    <!-- Drag & Drop Zone -->
    <div
      class="drop-zone"
      :class="{ 'is-dragging': isDragging, 'is-full': selectedFiles.length >= MAX_IMAGES }"
      @dragenter.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @dragover.prevent
      @drop.prevent="onDrop"
      @click="selectedFiles.length < MAX_IMAGES && fileInputRef.click()"
    >
      <input
        ref="fileInputRef"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        multiple
        class="hidden-input"
        @change="onFileInputChange"
      />
      <span v-if="selectedFiles.length < MAX_IMAGES" class="drop-hint">
        📎 Bilder hier ablegen oder klicken zum Auswählen
      </span>
      <span v-else class="drop-hint drop-hint--full">
        ✅ Maximale Anzahl erreicht ({{ MAX_IMAGES }}/{{ MAX_IMAGES }})
      </span>
    </div>

    <!-- Vorschau der ausgewählten Bilder -->
    <div v-if="selectedFiles.length > 0" class="preview-grid">
      <div
        v-for="(item, index) in selectedFiles"
        :key="item.file.name + item.file.size"
        class="preview-item"
      >
        <img :src="item.preview" :alt="item.file.name" class="preview-thumb" />
        <span class="preview-name" :title="item.file.name">{{ item.file.name }}</span>
        <button type="button" class="remove-btn" @click="removeFile(index)" title="Entfernen">✕</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.image-upload-section {
  margin: 16px 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.section-label {
  display: block;
  font-weight: 600;
  color: #333;
}

.optional-hint {
  font-weight: 400;
  font-size: 0.8rem;
  color: #777;
  margin-left: 6px;
}

.upload-error {
  background: #f8d7da;
  color: #721c24;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 0.85rem;
}

.drop-zone {
  border: 2px dashed #b0c4de;
  border-radius: 8px;
  padding: 20px;
  text-align: center;
  cursor: pointer;
  transition: background 0.2s, border-color 0.2s;
  background: #f8fbff;
}

.drop-zone:hover:not(.is-full) {
  background: #eaf3fb;
  border-color: #0e64a6;
}

.drop-zone.is-dragging {
  background: #d6eaf8;
  border-color: #0e64a6;
}

.drop-zone.is-full {
  cursor: default;
  opacity: 0.7;
}

.hidden-input { display: none; }

.drop-hint        { font-size: 0.9rem; color: #555; }
.drop-hint--full  { color: #27ae60; }

/* Bildvorschau */
.preview-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.preview-item {
  position: relative;
  width: 90px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.preview-thumb {
  width: 90px;
  height: 70px;
  object-fit: cover;
  border-radius: 6px;
  border: 1px solid #ddd;
}

.preview-name {
  font-size: 0.65rem;
  color: #555;
  width: 90px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-align: center;
}

.remove-btn {
  position: absolute;
  top: -6px;
  right: -6px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: none;
  background: #e74c3c;
  color: white;
  font-size: 0.65rem;
  cursor: pointer;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.remove-btn:hover { background: #c0392b; }
</style>