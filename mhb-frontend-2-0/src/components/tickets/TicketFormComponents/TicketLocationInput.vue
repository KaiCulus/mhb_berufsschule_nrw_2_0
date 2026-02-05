<script setup>
import { computed } from 'vue';
import { TICKET_CONFIG } from '@/components/tickets/config/ticketConfig';

const props = defineProps({
  type: String,
  building: String,
  room: String,
  isReadonly: Boolean
});
const emit = defineEmits(['update:type', 'update:building', 'update:room']);

const buildings = TICKET_CONFIG.buildings;

const roomsByBuilding = {
  'Hauptgebäude (R)': ['R10', 'R11', 'R15', 'R20'],
  'Chemie/Turn (S)': ['S01', 'S12', 'S20'],
  'Technologiezentrum (T)': ['T100', 'T105'],
  'Landwirtschaft (L)': ['L01']
};

// Prüft, ob der Raum valide ist
const isInvalidRoom = computed(() => {
  if (props.isReadonly || props.type !== 'building' || !props.room || !props.building) {
    return false;
  }
  const allowed = TICKET_CONFIG.roomsByBuilding[props.building] || [];
  return !allowed.includes(props.room.toUpperCase().trim());
});

const updateType = (newType) => {
  if (props.isReadonly) return;
  emit('update:type', newType);
  if (newType === 'other') {
    emit('update:building', '');
    emit('update:room', '');
  }
};

const handleRoomInput = (e) => {
  const val = e.target.value;
  // Wir emittieren den bereinigten Wert für das Gebäude-Mapping
  emit('update:room', props.type === 'building' ? val.toUpperCase().trim() : val);
};
</script>

<template>
<div class="location-container" :class="{ 'readonly': isReadonly }">
    <label>Ort des Problems</label>
    
    <div v-if="isReadonly" class="location-display">
      <span v-if="type === 'building'" class="loc-badge">
        🏫 {{ building }} — Raum: {{ room }}
      </span>
      <span v-else class="loc-badge">
        📍 Sonstiger Ort: {{ room }}
      </span>
    </div>

    <template v-else>
      <div class="type-toggle">
        <button type="button" :class="{ active: type === 'building' }" @click="updateType('building')">
          🏫 Gebäude / Raum
        </button>
        <button type="button" :class="{ active: type === 'other' }" @click="updateType('other')">
          📍 Sonstiger Ort
        </button>
      </div>

      <div v-if="type === 'building'" class="location-fields animate-slide">
        <div class="field-group">
          <select :value="building" @change="emit('update:building', $event.target.value)" required>
            <option value="" disabled>Gebäude wählen...</option>
            <option v-for="b in buildings" :key="b" :value="b">{{ b }}</option>
          </select>
        </div>
        <div class="field-group">
          <input 
            type="text" 
            :value="room" 
            @input="handleRoomInput" 
            placeholder="Raumnummer" 
            :class="{ 'input-error': isInvalidRoom }"
            required 
          />
          <small v-if="isInvalidRoom" class="error-text">Raum im gewählten Gebäude nicht bekannt.</small>
        </div>
      </div>

      <div v-else class="location-fields animate-slide">
        <div class="field-group">
          <input 
            type="text" 
            :value="room" 
            @input="emit('update:room', $event.target.value)" 
            placeholder="Wo genau?" 
            required 
          />
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.location-container {
  margin-bottom: 20px;
  padding: 15px;
  background: #f9f9f9;
  border-radius: 8px;
}

label { font-size: 0.9rem; font-weight: bold; display: block; margin-bottom: 10px; }

.type-toggle {
  display: flex;
  gap: 10px;
  margin-bottom: 15px;
}

.type-toggle button {
  flex: 1;
  padding: 10px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.type-toggle button.active {
  background: #0e64a6;
  color: white;
  border-color: #0e64a6;
  box-shadow: 0 2px 5px rgba(14, 100, 166, 0.3);
}

.location-fields {
  display: flex;
  gap: 10px;
}

.field-group { flex: 1; }

input, select, .other-input {
  width: 100%;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
  box-sizing: border-box;
}

.animate-slide {
  animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
  from { opacity: 0; transform: translateX(-10px); }
  to { opacity: 1; transform: translateX(0); }
}

.location-container.readonly { background: transparent; padding: 0; }
.location-display { padding: 5px 0; }
.loc-badge { 
  display: inline-block; 
  padding: 8px 12px; 
  background: #f0f4f8; 
  border-radius: 6px; 
  color: #2c3e50;
  font-weight: 500;
  border-left: 4px solid #0e64a6;
}
input.input-error {
  border-color: #e74c3c;
  background-color: #fdf2f2;
  outline-color: #e74c3c;
}

.error-text {
  color: #e74c3c;
  font-size: 0.75rem;
  margin-top: 4px;
  display: block;
}
</style>