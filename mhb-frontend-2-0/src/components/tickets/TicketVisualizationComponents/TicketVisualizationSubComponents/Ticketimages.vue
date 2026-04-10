<script setup>
import { ref, watch } from 'vue';
import axios from '@/scripts/axios';

/**
 * TicketImages — Bildanzeige und -upload in der Ticket-Detailansicht
 *
 * Zeigt alle vorhandenen Bilder eines Tickets als Thumbnail-Galerie.
 * Berechtigte User (Ersteller oder Processor) können weitere Bilder
 * hochladen oder vorhandene löschen.
 *
 * Der Upload läuft als separater Multipart-Request — unabhängig vom
 * restlichen Ticket-Formular, genau wie im TicketFormMain.
 *
 * Props:
 *   ticketId   — ID des Tickets
 *   images     — Bild-Array aus getDetail() (id, url, original_name, uploaded_at)
 *   canEdit    — Darf der aktuelle User Bilder hinzufügen / löschen?
 *
 * Emits:
 *   refresh    — Signalisiert TicketDetailsMain, die Ticket-Daten neu zu laden
 */

const MAX_IMAGES     = 5;
const MAX_SIZE_MB    = 5;
const MAX_SIZE_BYTES = MAX_SIZE_MB * 1024 * 1024;
const ALLOWED_TYPES  = ['image/jpeg', 'image/png', 'image/webp'];

const props = defineProps({
  ticketId: { type: Number, required: true },
  images:   { type: Array,  default: () => [] },
  canEdit:  { type: Boolean, default: false },
});

const emit = defineEmits(['refresh']);

const isUploading    = ref(false);
const uploadError    = ref('');
const deletingId     = ref(null);
const lightboxImage  = ref(null); // { url, original_name } oder null
const fileInputRef   = ref(null);
const isDragging     = ref(false);
const pendingFiles   = ref([]); // { file, preview }[]

// =========================================================================
// Datei-Handling
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
  uploadError.value = '';
  const errors = [];
  const currentTotal = props.images.length + pendingFiles.value.length;

  for (const file of files) {
    if (currentTotal + pendingFiles.value.length >= MAX_IMAGES) {
      errors.push(`Maximal ${MAX_IMAGES} Bilder pro Ticket erlaubt.`);
      break;
    }
    const isDuplicate = pendingFiles.value.some(
      (f) => f.file.name === file.name && f.file.size === file.size
    );
    if (isDuplicate) continue;

    const error = validateFile(file);
    if (error) { errors.push(error); continue; }

    pendingFiles.value.push({ file, preview: URL.createObjectURL(file) });
  }

  if (errors.length) uploadError.value = errors.join(' ');
};

const onFileInputChange = (event) => {
  addFiles(Array.from(event.target.files));
  event.target.value = '';
};

const onDrop = (event) => {
  isDragging.value = false;
  addFiles(Array.from(event.dataTransfer.files));
};

const removePending = (index) => {
  URL.revokeObjectURL(pendingFiles.value[index].preview);
  pendingFiles.value.splice(index, 1);
};

// =========================================================================
// Upload
// =========================================================================

const uploadPending = async () => {
  if (!pendingFiles.value.length) return;

  isUploading.value = true;
  uploadError.value = '';

  try {
    const formData = new FormData();
    pendingFiles.value.forEach(({ file }) => formData.append('images[]', file));

    await axios.post(`/api/tickets/images/${props.ticketId}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    pendingFiles.value.forEach(({ preview }) => URL.revokeObjectURL(preview));
    pendingFiles.value = [];
    emit('refresh');
  } catch (error) {
    uploadError.value = error.response?.data?.message ?? 'Upload fehlgeschlagen.';
  } finally {
    isUploading.value = false;
  }
};

// =========================================================================
// Löschen
// =========================================================================

const deleteImage = async (imageId) => {
  if (!confirm('Bild wirklich löschen?')) return;

  deletingId.value = imageId;
  try {
    await axios.delete(`/api/tickets/images/delete/${imageId}`);
    emit('refresh');
  } catch (error) {
    uploadError.value = 'Löschen fehlgeschlagen.';
  } finally {
    deletingId.value = null;
  }
};

// Pending-Previews aufräumen wenn Komponente neu geladen wird
watch(() => props.images, () => {
  if (lightboxImage.value) {
    // Lightbox schließen falls das angezeigte Bild gelöscht wurde
    const stillExists = props.images.some(img => img.url === lightboxImage.value.url);
    if (!stillExists) lightboxImage.value = null;
  }
});
</script>

<template>
  <div class="ticket-images">
    <h4 class="section-title">Bilder</h4>

    <!-- Fehlermeldung -->
    <div v-if="uploadError" class="upload-error">{{ uploadError }}</div>

    <!-- Vorhandene Bilder -->
    <div v-if="images.length > 0" class="image-grid">
      <div
        v-for="image in images"
        :key="image.id"
        class="image-item"
      >
        <img
          :src="image.url"
          :alt="image.original_name"
          class="thumbnail"
          @click="lightboxImage = image"
          title="Klicken zum Vergrößern"
        />
        <span class="image-name" :title="image.original_name">{{ image.original_name }}</span>

        <button
          v-if="canEdit"
          class="delete-img-btn"
          :disabled="deletingId === image.id"
          @click.stop="deleteImage(image.id)"
          title="Bild löschen"
        >
          {{ deletingId === image.id ? '…' : '✕' }}
        </button>
      </div>
    </div>

    <p v-else-if="!canEdit" class="no-images">Keine Bilder vorhanden.</p>

    <!-- Upload-Bereich (nur für berechtigte User und wenn noch Platz ist) -->
    <template v-if="canEdit && (images.length + pendingFiles.length) < MAX_IMAGES">
      <div
        class="drop-zone"
        :class="{ 'is-dragging': isDragging }"
        @dragenter.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @dragover.prevent
        @drop.prevent="onDrop"
        @click="fileInputRef.click()"
      >
        <input
          ref="fileInputRef"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          multiple
          class="hidden-input"
          @change="onFileInputChange"
        />
        <span class="drop-hint">📎 Bilder ablegen oder klicken</span>
        <span class="drop-counter">{{ images.length + pendingFiles.length }}/{{ MAX_IMAGES }}</span>
      </div>
    </template>
    <p v-else-if="canEdit" class="max-reached">✅ Maximale Bildanzahl erreicht ({{ MAX_IMAGES }}/{{ MAX_IMAGES }})</p>

    <!-- Pending-Vorschau + Upload-Button -->
    <template v-if="pendingFiles.length > 0">
      <div class="pending-grid">
        <div v-for="(item, index) in pendingFiles" :key="item.file.name" class="pending-item">
          <img :src="item.preview" :alt="item.file.name" class="thumbnail" />
          <span class="image-name" :title="item.file.name">{{ item.file.name }}</span>
          <button class="delete-img-btn" @click="removePending(index)" title="Entfernen">✕</button>
        </div>
      </div>

      <button
        class="upload-btn"
        :disabled="isUploading"
        @click="uploadPending"
      >
        {{ isUploading ? 'Wird hochgeladen…' : `${pendingFiles.length} Bild${pendingFiles.length > 1 ? 'er' : ''} hochladen` }}
      </button>
    </template>

    <!-- Lightbox -->
    <Teleport to="body">
      <div v-if="lightboxImage" class="lightbox-overlay" @click.self="lightboxImage = null">
        <div class="lightbox-content">
          <button class="lightbox-close" @click="lightboxImage = null">✕</button>
          <img :src="lightboxImage.url" :alt="lightboxImage.original_name" class="lightbox-img" />
          <p class="lightbox-name">{{ lightboxImage.original_name }}</p>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.ticket-images {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 600;
  color: #333;
  border-bottom: 1px solid #eee;
  padding-bottom: 6px;
}

.upload-error {
  background: #f8d7da;
  color: #721c24;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 0.85rem;
}

/* Bild-Grid */
.image-grid,
.pending-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.image-item,
.pending-item {
  position: relative;
  width: 90px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.thumbnail {
  width: 90px;
  height: 70px;
  object-fit: cover;
  border-radius: 6px;
  border: 1px solid #ddd;
  cursor: pointer;
  transition: opacity 0.15s;
}

.thumbnail:hover {
  opacity: 0.85;
}

.image-name {
  font-size: 0.65rem;
  color: #666;
  width: 90px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-align: center;
}

.delete-img-btn {
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
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}

.delete-img-btn:hover:not(:disabled) { background: #c0392b; }
.delete-img-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Drop-Zone */
.drop-zone {
  border: 2px dashed #b0c4de;
  border-radius: 8px;
  padding: 14px 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  background: #f8fbff;
  transition: background 0.2s, border-color 0.2s;
  font-size: 0.85rem;
}

.drop-zone:hover,
.drop-zone.is-dragging {
  background: #d6eaf8;
  border-color: #0e64a6;
}

.hidden-input { display: none; }

.drop-hint { color: #555; }
.drop-counter { font-size: 0.75rem; color: #999; }

.max-reached,
.no-images {
  font-size: 0.82rem;
  color: #888;
  margin: 0;
}

/* Upload-Button */
.upload-btn {
  width: 100%;
  padding: 9px;
  background: #0e64a6;
  color: white;
  border: none;
  border-radius: 7px;
  cursor: pointer;
  font-size: 0.88rem;
  transition: background 0.2s;
}

.upload-btn:hover:not(:disabled) { background: #0a4d82; }
.upload-btn:disabled { opacity: 0.6; cursor: not-allowed; }

/* Lightbox */
.lightbox-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.85);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 3000;
}

.lightbox-content {
  position: relative;
  max-width: 90vw;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}

.lightbox-img {
  max-width: 90vw;
  max-height: 80vh;
  object-fit: contain;
  border-radius: 8px;
}

.lightbox-name {
  color: #ddd;
  font-size: 0.85rem;
  margin: 0;
}

.lightbox-close {
  position: absolute;
  top: -14px;
  right: -14px;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  border: none;
  background: #e74c3c;
  color: white;
  font-size: 0.9rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>